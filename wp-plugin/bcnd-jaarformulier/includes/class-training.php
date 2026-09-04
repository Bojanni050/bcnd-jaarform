<?php
if (!defined('ABSPATH')) { exit; }

class BCND_Training {

    const MEMBER_EDITABLE = ['concept', 'aanpassing_gevraagd'];

    private static function year_of($date, $provided) {
        if ($provided) { return (int) $provided; }
        $y = (int) substr((string) $date, 0, 4);
        return $y ?: (int) current_time('Y');
    }

    private static function member_or_error() {
        $m = BCND_Core::member_for_user(get_current_user_id());
        if (!$m) { return new WP_Error('bcnd_no_member', 'Geen lidprofiel', ['status' => 404]); }
        return $m;
    }

    public static function create($req) {
        global $wpdb;
        $m = self::member_or_error();
        if (is_wp_error($m)) { return $m; }
        $status = $req->get_param('status');
        $status = in_array($status, ['concept', 'ingediend'], true) ? $status : 'ingediend';
        $date = sanitize_text_field($req->get_param('date'));
        $year = self::year_of($date, $req->get_param('year'));
        $now = BCND_Core::now();
        $wpdb->insert(BCND_Database::t('training'), [
            'member_id' => $m['id'],
            'member_name' => $m['name'],
            'year' => $year,
            'date' => $date ?: null,
            'hours' => (float) $req->get_param('hours'),
            'organization' => sanitize_text_field($req->get_param('organization')),
            'subject' => sanitize_text_field($req->get_param('subject')),
            'content_explanation' => sanitize_textarea_field($req->get_param('content_explanation')),
            'speaker' => sanitize_text_field($req->get_param('speaker')),
            'activity_type' => sanitize_text_field($req->get_param('activity_type')) ?: 'externe_bijscholing',
            'member_remarks' => sanitize_textarea_field($req->get_param('member_remarks')),
            'points' => null,
            'admin_remark' => '',
            'status' => $status,
            'submitted_at' => ($status === 'ingediend') ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int) $wpdb->insert_id;
        BCND_Audit_Log::add('training', $id, 'aangemaakt', ['to' => $status]);
        if ($status === 'ingediend') {
            BCND_Notifications::notify_admins('training_submitted', 'Nieuwe bijscholing ingediend',
                $m['name'] . " heeft '" . $req->get_param('subject') . "' ingediend.", ['training_id' => $id]);
        }
        return rest_ensure_response(self::get_row($id));
    }

    public static function list_all($req) {
        global $wpdb;
        $tt = BCND_Database::t('training');
        $is_admin = BCND_Roles::is_admin_user();
        $where = '1=1'; $args = [];
        if (!$is_admin) {
            $m = BCND_Core::member_for_user(get_current_user_id());
            if (!$m) { return rest_ensure_response([]); }
            $where .= ' AND member_id = %d'; $args[] = $m['id'];
        } else {
            $mid = $req->get_param('member_id');
            if ($mid) { $where .= ' AND member_id = %d'; $args[] = (int) $mid; }
        }
        $year = $req->get_param('year');
        if ($year) { $where .= ' AND year = %d'; $args[] = (int) $year; }
        $status = sanitize_text_field($req->get_param('status'));
        if ($status) { $where .= ' AND status = %s'; $args[] = $status; }
        $atype = sanitize_text_field($req->get_param('activity_type'));
        if ($atype) { $where .= ' AND activity_type = %s'; $args[] = $atype; }
        $org = sanitize_text_field($req->get_param('organization'));
        if ($org) { $where .= ' AND organization LIKE %s'; $args[] = '%' . $wpdb->esc_like($org) . '%'; }

        $sql = "SELECT * FROM $tt WHERE $where ORDER BY date DESC, id DESC";
        if ($args) { $sql = $wpdb->prepare($sql, $args); }
        $rows = $wpdb->get_results($sql, ARRAY_A);
        $out = [];
        foreach ($rows as $r) {
            $item = BCND_Core::format_training($r);
            $item['documents'] = BCND_Core::training_documents($item['id']);
            $out[] = $item;
        }
        return rest_ensure_response($out);
    }

    private static function get_row($id) {
        global $wpdb;
        $tt = BCND_Database::t('training');
        $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tt WHERE id = %d", $id), ARRAY_A);
        if (!$r) { return null; }
        $item = BCND_Core::format_training($r);
        $item['documents'] = BCND_Core::training_documents($id);
        return $item;
    }

    private static function checked($id) {
        $row = self::get_row($id);
        if (!$row) { return new WP_Error('bcnd_not_found', 'Bijscholing niet gevonden', ['status' => 404]); }
        if (!BCND_Roles::is_admin_user()) {
            $m = BCND_Core::member_for_user(get_current_user_id());
            if (!$m || (int) $row['member_id'] !== (int) $m['id']) {
                return new WP_Error('bcnd_forbidden', 'Geen toegang', ['status' => 403]);
            }
        }
        return $row;
    }

    public static function get_one($req) {
        $row = self::checked((int) $req['id']);
        return is_wp_error($row) ? $row : rest_ensure_response($row);
    }

    public static function history($req) {
        $row = self::checked((int) $req['id']);
        if (is_wp_error($row)) { return $row; }
        return rest_ensure_response(BCND_Audit_Log::history('training', (int) $req['id']));
    }

    public static function update($req) {
        global $wpdb;
        $row = self::checked((int) $req['id']);
        if (is_wp_error($row)) { return $row; }
        $is_admin = BCND_Roles::is_admin_user();
        if (!$is_admin && !in_array($row['status'], self::MEMBER_EDITABLE, true)) {
            return new WP_Error('bcnd_locked', 'Deze bijscholing kan niet meer worden gewijzigd', ['status' => 400]);
        }
        $fields = ['date', 'hours', 'organization', 'subject', 'content_explanation', 'speaker', 'activity_type', 'member_remarks'];
        $data = [];
        foreach ($fields as $f) {
            $v = $req->get_param($f);
            if ($v === null) { continue; }
            if ($f === 'hours') { $data[$f] = (float) $v; }
            elseif (in_array($f, ['content_explanation', 'member_remarks'], true)) { $data[$f] = sanitize_textarea_field($v); }
            else { $data[$f] = sanitize_text_field($v); }
        }
        if ($data) {
            if (isset($data['date'])) { $data['year'] = self::year_of($data['date'], null); }
            $data['updated_at'] = BCND_Core::now();
            $wpdb->update(BCND_Database::t('training'), $data, ['id' => (int) $req['id']]);
            BCND_Audit_Log::add('training', (int) $req['id'], 'gewijzigd', ['remark' => 'Gegevens bijgewerkt']);
        }
        return rest_ensure_response(self::get_row((int) $req['id']));
    }

    public static function submit($req) {
        global $wpdb;
        $row = self::checked((int) $req['id']);
        if (is_wp_error($row)) { return $row; }
        if (!in_array($row['status'], ['concept', 'aanpassing_gevraagd'], true)) {
            return new WP_Error('bcnd_invalid', 'Kan niet worden ingediend', ['status' => 400]);
        }
        $wpdb->update(BCND_Database::t('training'),
            ['status' => 'ingediend', 'submitted_at' => BCND_Core::now(), 'updated_at' => BCND_Core::now()],
            ['id' => (int) $req['id']]);
        BCND_Audit_Log::add('training', (int) $req['id'], 'ingediend', ['from' => $row['status'], 'to' => 'ingediend']);
        BCND_Notifications::notify_admins('training_submitted', 'Bijscholing ingediend',
            $row['member_name'] . " heeft '" . $row['subject'] . "' ingediend.", ['training_id' => (int) $req['id']]);
        return rest_ensure_response(self::get_row((int) $req['id']));
    }

    public static function review($req) {
        global $wpdb;
        $id = (int) $req['id'];
        $row = self::get_row($id);
        if (!$row) { return new WP_Error('bcnd_not_found', 'Bijscholing niet gevonden', ['status' => 404]); }
        $member = BCND_Core::get_member($row['member_id']);
        $action = sanitize_text_field($req->get_param('action'));
        $points = $req->get_param('points');
        $remark = sanitize_textarea_field($req->get_param('remark'));
        $from = $row['status'];
        $data = ['updated_at' => BCND_Core::now(), 'reviewed_by' => wp_get_current_user()->display_name, 'reviewed_at' => BCND_Core::now()];
        if ($points !== null && $points !== '') { $data['points'] = (float) $points; }
        if ($remark) { $data['admin_remark'] = $remark; }

        $tt = BCND_Database::t('training');
        if ($action === 'approve') {
            $data['status'] = 'goedgekeurd';
            if (!isset($data['points']) && $row['points'] === null) { $data['points'] = 0; }
            $wpdb->update($tt, $data, ['id' => $id]);
            BCND_Audit_Log::add('training', $id, 'goedgekeurd', ['from' => $from, 'to' => 'goedgekeurd', 'remark' => $remark, 'new' => ['points' => isset($data['points']) ? $data['points'] : $row['points']]]);
            if ($member) {
                $msg = BCND_Notifications::tpl('training_approved', ['name' => $member['name'], 'subject' => $row['subject'], 'points' => isset($data['points']) ? $data['points'] : $row['points']]);
                BCND_Notifications::notify($member['user_id'], 'training_approved', 'Bijscholing goedgekeurd', $msg, ['training_id' => $id]);
            }
        } elseif ($action === 'reject') {
            $data['status'] = 'afgekeurd';
            $wpdb->update($tt, $data, ['id' => $id]);
            BCND_Audit_Log::add('training', $id, 'afgekeurd', ['from' => $from, 'to' => 'afgekeurd', 'remark' => $remark]);
            if ($member) {
                $msg = BCND_Notifications::tpl('training_rejected', ['name' => $member['name'], 'subject' => $row['subject'], 'remark' => $remark]);
                BCND_Notifications::notify($member['user_id'], 'training_rejected', 'Bijscholing afgekeurd', $msg, ['training_id' => $id]);
            }
        } elseif ($action === 'request_changes') {
            $data['status'] = 'aanpassing_gevraagd';
            $wpdb->update($tt, $data, ['id' => $id]);
            BCND_Audit_Log::add('training', $id, 'aanpassing_gevraagd', ['from' => $from, 'to' => 'aanpassing_gevraagd', 'remark' => $remark]);
            if ($member) {
                $msg = BCND_Notifications::tpl('training_changes', ['name' => $member['name'], 'subject' => $row['subject'], 'remark' => $remark]);
                BCND_Notifications::notify($member['user_id'], 'training_changes', 'Aanvullende informatie gevraagd', $msg, ['training_id' => $id]);
            }
        } elseif ($action === 'assign_points') {
            if ($from === 'ingediend') { $data['status'] = 'in_beoordeling'; }
            $wpdb->update($tt, $data, ['id' => $id]);
            if (isset($data['points'])) {
                BCND_Audit_Log::add('training', $id, 'punten_toegekend', ['remark' => $data['points'] . ' punt(en) toegekend']);
            }
        } else {
            return new WP_Error('bcnd_invalid', 'Onbekende actie', ['status' => 400]);
        }
        return rest_ensure_response(self::get_row($id));
    }
}
