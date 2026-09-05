<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Shared business logic: settings, norms, year overview, identity mapping.
 */
class BCND_Core {

    public static function default_settings() {
        return [
            'points_norm' => 8,
            'consults_norms' => ['1' => 10, '2' => 20, '3' => 30, '4' => 40],
            'deadline_day' => 31,
            'deadline_month' => 12,
            'notifications_enabled' => true,
            'email_templates' => [
                'training_submitted'  => 'Beste {name}, uw bijscholing \'{subject}\' is ontvangen en wordt beoordeeld.',
                'training_approved'   => 'Beste {name}, uw bijscholing \'{subject}\' is goedgekeurd met {points} punt(en).',
                'training_rejected'   => 'Beste {name}, uw bijscholing \'{subject}\' is afgekeurd. {remark}',
                'training_changes'    => 'Beste {name}, voor uw bijscholing \'{subject}\' is aanvullende informatie gevraagd: {remark}',
                'annual_submitted'    => 'Beste {name}, uw jaarformulier {year} is ingediend en wacht op beoordeling.',
                'annual_approved'     => 'Beste {name}, uw jaarformulier {year} is goedgekeurd.',
                'annual_correction'   => 'Beste {name}, uw jaarformulier {year} is teruggestuurd voor correctie: {remark}',
                'deadline_reminder'   => 'Beste {name}, u heeft nog {days} dagen om uw jaarformulier {year} in te dienen.',
            ],
        ];
    }

    public static function get_settings() {
        $s = get_option('bcnd_settings');
        if (!is_array($s)) { $s = []; }
        return array_replace_recursive(self::default_settings(), $s);
    }

    public static function save_settings($updates) {
        $s = self::get_settings();
        foreach ($updates as $k => $v) {
            if ($v !== null) { $s[$k] = $v; }
        }
        update_option('bcnd_settings', $s);
        return self::get_settings();
    }

    public static function now() {
        return current_time('mysql', true); // UTC datetime string
    }

    /* ---------- Identity ---------- */

    public static function role_of($user) {
        return BCND_Roles::is_admin_user($user) ? 'admin' : 'member';
    }

    public static function current_identity() {
        $u = wp_get_current_user();
        if (!$u || !$u->exists()) { return null; }
        $member = self::member_for_user($u->ID);
        return [
            'id' => (int) $u->ID,
            'email' => $u->user_email,
            'name' => $u->display_name,
            'role' => self::role_of($u),
            'member_id' => $member ? (int) $member['id'] : null,
        ];
    }

    public static function member_for_user($user_id) {
        global $wpdb;
        $t = BCND_Database::t('members');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE user_id = %d", $user_id), ARRAY_A);
        return $row ? self::format_member($row) : null;
    }

    public static function get_member($member_id) {
        global $wpdb;
        $t = BCND_Database::t('members');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $member_id), ARRAY_A);
        return $row ? self::format_member($row) : null;
    }

    /**
     * These live on the WordPress user (JetEngine meta fields on the member's
     * account), not in our own tables, so there's only one place to edit them.
     */
    public static function member_number_of($user_id) {
        return (string) get_user_meta($user_id, 'lidnummer', true);
    }

    public static function license_since_of($user_id) {
        $v = get_user_meta($user_id, 'license_since', true);
        return $v ? $v : null;
    }

    public static function street_of($user_id) {
        return (string) get_user_meta($user_id, 'straat', true);
    }

    public static function house_number_of($user_id) {
        return (string) get_user_meta($user_id, 'huisnummer', true);
    }

    public static function postal_code_of($user_id) {
        return (string) get_user_meta($user_id, 'postcode', true);
    }

    public static function city_of($user_id) {
        return (string) get_user_meta($user_id, 'plaats', true);
    }

    public static function phone_of($user_id) {
        return (string) get_user_meta($user_id, 'telefoon', true);
    }

    public static function format_member($row) {
        $uid = $row['user_id'];
        $street = self::street_of($uid);
        $house_number = self::house_number_of($uid);
        return [
            'id' => (int) $row['id'],
            'user_id' => (int) $uid,
            'name' => $row['name'],
            'email' => $row['email'],
            'street' => $street,
            'house_number' => $house_number,
            'address' => trim($street . ' ' . $house_number),
            'city' => self::city_of($uid),
            'postal_code' => self::postal_code_of($uid),
            'member_number' => self::member_number_of($uid),
            'license_since' => self::license_since_of($uid),
            'phone' => self::phone_of($uid),
            'status' => $row['status'],
            'notes' => isset($row['notes']) ? $row['notes'] : '',
        ];
    }

    /* ---------- Norms ---------- */

    public static function membership_year($license_since, $form_year) {
        $ly = (int) substr((string) $license_since, 0, 4);
        if ($ly <= 0) { $ly = (int) $form_year; }
        return max(1, (int) $form_year - $ly + 1);
    }

    public static function compute_norms($license_since, $form_year, $settings = null) {
        if ($settings === null) { $settings = self::get_settings(); }
        $my = self::membership_year($license_since, $form_year);
        $cn = $settings['consults_norms'];
        $key = (string) min($my, 4);
        $consults_norm = isset($cn[$key]) ? (int) $cn[$key] : 40;
        $points_norm = ($my === 1) ? 0 : (int) $settings['points_norm'];
        return [
            'membership_year' => $my,
            'points_norm' => $points_norm,
            'consults_norm' => $consults_norm,
        ];
    }

    public static function days_until_deadline($settings, $year) {
        $day = (int) $settings['deadline_day'];
        $month = (int) $settings['deadline_month'];
        $deadline = strtotime(sprintf('%04d-%02d-%02d', $year, $month, $day) . ' 23:59:59');
        $today = strtotime(current_time('Y-m-d'));
        return (int) floor(($deadline - $today) / DAY_IN_SECONDS);
    }

    public static function requires_own_certificate($activity_type) {
        return in_array($activity_type, ['externe_bijscholing', 'overige_activiteit'], true);
    }

    /* ---------- Year overview ---------- */

    public static function build_year_overview($member, $year) {
        global $wpdb;
        $year = (int) $year;
        $settings = self::get_settings();
        $norms = self::compute_norms($member['license_since'], $year, $settings);

        $tt = BCND_Database::t('training');
        $trainings = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $tt WHERE member_id = %d AND year = %d", $member['id'], $year), ARRAY_A);

        $approved = 0; $in_review = 0; $changes = 0; $achieved_points = 0.0;
        foreach ($trainings as $t) {
            if ($t['status'] === 'goedgekeurd') { $approved++; $achieved_points += (float) $t['points']; }
            elseif (in_array($t['status'], ['ingediend', 'in_beoordeling'], true)) { $in_review++; }
            elseif ($t['status'] === 'aanpassing_gevraagd') { $changes++; }
        }

        // documents present per training
        $dt = BCND_Database::t('training_documents');
        $doc_tids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT training_id FROM $dt WHERE member_id = %d AND is_deleted = 0 AND training_id IS NOT NULL", $member['id']));
        $doc_tids = array_map('intval', $doc_tids);

        $missing_docs = 0;
        foreach ($trainings as $t) {
            if (self::requires_own_certificate($t['activity_type']) && $t['status'] !== 'afgekeurd'
                && !in_array((int) $t['id'], $doc_tids, true)) {
                $missing_docs++;
            }
        }

        $ct = BCND_Database::t('consultations');
        $consult = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $ct WHERE member_id = %d AND year = %d", $member['id'], $year), ARRAY_A);
        $total_consults = $consult ? (int) $consult['total_consults'] : 0;

        $points_norm = $norms['points_norm'];
        $consults_norm = $norms['consults_norm'];
        $points_complete = ($points_norm === 0) || ($achieved_points >= $points_norm);
        $consults_complete = $total_consults >= $consults_norm;

        return [
            'year' => $year,
            'membership_year' => $norms['membership_year'],
            'points' => [
                'achieved' => $achieved_points + 0,
                'required' => $points_norm,
                'remaining' => max(0, $points_norm - $achieved_points),
                'percentage' => $points_norm === 0 ? 100 : min(100, (int) round($achieved_points / $points_norm * 100)),
                'complete' => $points_complete,
            ],
            'consults' => [
                'achieved' => $total_consults,
                'required' => $consults_norm,
                'remaining' => max(0, $consults_norm - $total_consults),
                'percentage' => $consults_norm ? min(100, (int) round($total_consults / $consults_norm * 100)) : 100,
                'complete' => $consults_complete,
                'first_consults' => $consult ? (int) $consult['first_consults'] : 0,
                'followup_consults' => $consult ? (int) $consult['followup_consults'] : 0,
                'other_activities' => $consult ? $consult['other_activities'] : '',
            ],
            'counts' => [
                'trainings_total' => count($trainings),
                'approved' => $approved,
                'in_review' => $in_review,
                'changes_requested' => $changes,
                'missing_documents' => $missing_docs,
            ],
            'all_complete' => $points_complete && $consults_complete,
            'days_until_deadline' => self::days_until_deadline($settings, $year),
        ];
    }

    public static function format_training($t) {
        return [
            'id' => (int) $t['id'],
            'member_id' => (int) $t['member_id'],
            'member_name' => $t['member_name'],
            'year' => (int) $t['year'],
            'date' => $t['date'],
            'hours' => (float) $t['hours'],
            'organization' => $t['organization'],
            'subject' => $t['subject'],
            'content_explanation' => $t['content_explanation'],
            'speaker' => $t['speaker'],
            'activity_type' => $t['activity_type'],
            'member_remarks' => $t['member_remarks'],
            'points' => $t['points'] === null ? null : (float) $t['points'],
            'admin_remark' => $t['admin_remark'],
            'status' => $t['status'],
            'reviewed_by' => $t['reviewed_by'],
            'reviewed_at' => $t['reviewed_at'],
            'submitted_at' => $t['submitted_at'],
            'created_at' => $t['created_at'],
            'updated_at' => $t['updated_at'],
        ];
    }

    public static function training_documents($training_id) {
        global $wpdb;
        $dt = BCND_Database::t('training_documents');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, original_filename, mime, size, doc_type, created_at FROM $dt WHERE training_id = %d AND is_deleted = 0", $training_id), ARRAY_A);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'original_filename' => $r['original_filename'],
                'content_type' => $r['mime'],
                'size' => (int) $r['size'],
                'doc_type' => $r['doc_type'],
                'created_at' => $r['created_at'],
            ];
        }
        return $out;
    }

    public static function format_annual($f) {
        return [
            'id' => (int) $f['id'],
            'member_id' => (int) $f['member_id'],
            'member_name' => $f['member_name'],
            'year' => (int) $f['year'],
            'status' => $f['status'],
            'deviation_reason' => $f['deviation_reason'],
            'submitted_at' => $f['submitted_at'],
            'submitted_by' => $f['submitted_by'],
            'reviewed_by' => $f['reviewed_by'],
            'reviewed_at' => $f['reviewed_at'],
            'admin_remark' => $f['admin_remark'],
            'pdf_document_id' => !empty($f['pdf_filename']) ? $f['pdf_filename'] : null,
            'applied_points_norm' => $f['applied_points_norm'] === null ? null : (int) $f['applied_points_norm'],
            'applied_consults_norm' => $f['applied_consults_norm'] === null ? null : (int) $f['applied_consults_norm'],
            'achieved_points' => $f['achieved_points'] === null ? null : (float) $f['achieved_points'],
            'achieved_consults' => $f['achieved_consults'] === null ? null : (int) $f['achieved_consults'],
            'created_at' => $f['created_at'],
            'updated_at' => $f['updated_at'],
        ];
    }
}
