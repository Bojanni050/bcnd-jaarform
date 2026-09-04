<?php
if (!defined('ABSPATH')) { exit; }

class BCND_Admin_Controller {

    public static function dashboard($req) {
        global $wpdb;
        $year = (int) $req->get_param('year') ?: (int) current_time('Y');
        $settings = BCND_Core::get_settings();
        $tt = BCND_Database::t('training');
        $af = BCND_Database::t('annual_forms');
        $mt = BCND_Database::t('members');

        $count = function ($sql, $args = []) use ($wpdb) {
            $sql = $args ? $wpdb->prepare($sql, $args) : $sql;
            return (int) $wpdb->get_var($sql);
        };

        $trainings_pending = $count("SELECT COUNT(*) FROM $tt WHERE status = 'ingediend'");
        $trainings_in_review = $count("SELECT COUNT(*) FROM $tt WHERE status = 'in_beoordeling'");
        $trainings_changes = $count("SELECT COUNT(*) FROM $tt WHERE status = 'aanpassing_gevraagd'");
        $forms_to_review = $count("SELECT COUNT(*) FROM $af WHERE status IN ('ingediend','in_beoordeling')");
        $forms_approved = $count("SELECT COUNT(*) FROM $af WHERE status = 'goedgekeurd' AND year = %d", [$year]);

        $members = $wpdb->get_results("SELECT * FROM $mt WHERE status = 'active'", ARRAY_A);
        $behind = []; $deadline_soon = []; $missing = [];
        $days = BCND_Core::days_until_deadline($settings, $year);
        foreach ($members as $mrow) {
            $m = BCND_Core::format_member($mrow);
            $ov = BCND_Core::build_year_overview($m, $year);
            if (!$ov['all_complete']) {
                $behind[] = ['member_id' => $m['id'], 'name' => $m['name'], 'points' => $ov['points'], 'consults' => $ov['consults']];
                $form = $wpdb->get_row($wpdb->prepare("SELECT status FROM $af WHERE member_id = %d AND year = %d", $m['id'], $year), ARRAY_A);
                $submitted = $form && in_array($form['status'], ['ingediend', 'in_beoordeling', 'goedgekeurd'], true);
                if ($days >= 0 && $days <= 45 && !$submitted) {
                    $deadline_soon[] = ['member_id' => $m['id'], 'name' => $m['name'], 'days' => $days];
                }
            }
            if ($ov['counts']['missing_documents'] > 0) {
                $missing[] = ['member_id' => $m['id'], 'name' => $m['name'], 'count' => $ov['counts']['missing_documents']];
            }
        }

        return rest_ensure_response([
            'year' => $year,
            'days_until_deadline' => $days,
            'trainings_pending' => $trainings_pending,
            'trainings_in_review' => $trainings_in_review,
            'trainings_changes' => $trainings_changes,
            'forms_to_review' => $forms_to_review,
            'forms_approved' => $forms_approved,
            'members_total' => count($members),
            'members_behind' => $behind,
            'members_deadline_soon' => $deadline_soon,
            'members_missing_docs' => $missing,
        ]);
    }

    public static function review_queue($req) {
        global $wpdb;
        $tt = BCND_Database::t('training');
        $rows = $wpdb->get_results("SELECT * FROM $tt WHERE status IN ('ingediend','in_beoordeling') ORDER BY submitted_at ASC", ARRAY_A);
        $out = array_map(['BCND_Core', 'format_training'], $rows);
        return rest_ensure_response($out);
    }
}
