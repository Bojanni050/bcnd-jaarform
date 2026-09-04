<?php
if (!defined('ABSPATH')) { exit; }

class BCND_Consultations {

    private static function resolve_member($req) {
        if (BCND_Roles::is_admin_user()) {
            $mid = (int) $req->get_param('member_id');
            if (!$mid) { return new WP_Error('bcnd_invalid', 'member_id vereist', ['status' => 400]); }
            $m = BCND_Core::get_member($mid);
            if (!$m) { return new WP_Error('bcnd_not_found', 'Lid niet gevonden', ['status' => 404]); }
            return $m;
        }
        $m = BCND_Core::member_for_user(get_current_user_id());
        if (!$m) { return new WP_Error('bcnd_no_member', 'Geen lidprofiel', ['status' => 404]); }
        return $m;
    }

    public static function get($req) {
        global $wpdb;
        $m = self::resolve_member($req);
        if (is_wp_error($m)) { return $m; }
        $year = (int) $req->get_param('year');
        $t = BCND_Database::t('consultations');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE member_id = %d AND year = %d", $m['id'], $year), ARRAY_A);
        if (!$row) {
            return rest_ensure_response([
                'member_id' => $m['id'], 'year' => $year, 'total_consults' => 0,
                'first_consults' => 0, 'followup_consults' => 0, 'other_activities' => '',
            ]);
        }
        return rest_ensure_response(self::format($row));
    }

    public static function upsert($req) {
        global $wpdb;
        $m = self::resolve_member($req);
        if (is_wp_error($m)) { return $m; }
        $year = (int) $req->get_param('year');
        $first = (int) $req->get_param('first_consults');
        $follow = (int) $req->get_param('followup_consults');
        $total = (int) $req->get_param('total_consults');
        if (!$total && ($first || $follow)) { $total = $first + $follow; }
        $t = BCND_Database::t('consultations');
        $now = BCND_Core::now();
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id FROM $t WHERE member_id = %d AND year = %d", $m['id'], $year), ARRAY_A);
        $data = [
            'member_id' => $m['id'], 'year' => $year, 'total_consults' => $total,
            'first_consults' => $first, 'followup_consults' => $follow,
            'other_activities' => sanitize_textarea_field($req->get_param('other_activities')),
            'updated_at' => $now,
        ];
        if ($existing) {
            $wpdb->update($t, $data, ['id' => $existing['id']]);
        } else {
            $data['created_at'] = $now;
            $wpdb->insert($t, $data);
        }
        BCND_Audit_Log::add('consult', $m['id'] . ':' . $year, 'consulten_bijgewerkt', ['remark' => "Totaal $total consulten voor $year"]);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE member_id = %d AND year = %d", $m['id'], $year), ARRAY_A);
        return rest_ensure_response(self::format($row));
    }

    private static function format($row) {
        return [
            'id' => (int) $row['id'],
            'member_id' => (int) $row['member_id'],
            'year' => (int) $row['year'],
            'total_consults' => (int) $row['total_consults'],
            'first_consults' => (int) $row['first_consults'],
            'followup_consults' => (int) $row['followup_consults'],
            'other_activities' => $row['other_activities'],
        ];
    }
}
