<?php
if (!defined('ABSPATH')) { exit; }

class BCND_Notifications {

    public static function tpl($key, $vars = []) {
        $s = BCND_Core::get_settings();
        $tpl = isset($s['email_templates'][$key]) ? $s['email_templates'][$key] : '';
        foreach ($vars as $k => $v) {
            $tpl = str_replace('{' . $k . '}', (string) $v, $tpl);
        }
        return $tpl;
    }

    public static function notify($user_id, $type, $title, $message, $related = []) {
        global $wpdb;
        $wpdb->insert(BCND_Database::t('notifications'), [
            'user_id' => (int) $user_id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related' => wp_json_encode($related),
            'is_read' => 0,
            'created_at' => BCND_Core::now(),
        ]);
        $settings = BCND_Core::get_settings();
        if (!empty($settings['notifications_enabled'])) {
            $user = get_userdata($user_id);
            if ($user && $user->user_email) {
                wp_mail($user->user_email, '[BCND] ' . $title, $message);
            }
        }
    }

    public static function notify_admins($type, $title, $message, $related = []) {
        $admins = get_users(['role__in' => ['bcnd_admin', 'administrator'], 'fields' => ['ID']]);
        foreach ($admins as $a) {
            self::notify($a->ID, $type, $title, $message, $related);
        }
    }

    public static function list_for_user($user_id) {
        global $wpdb;
        $t = BCND_Database::t('notifications');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $t WHERE user_id = %d ORDER BY created_at DESC, id DESC LIMIT 100", $user_id), ARRAY_A);
        $items = []; $unread = 0;
        foreach ($rows as $r) {
            $read = (bool) $r['is_read'];
            if (!$read) { $unread++; }
            $items[] = [
                'id' => (int) $r['id'],
                'type' => $r['type'],
                'title' => $r['title'],
                'message' => $r['message'],
                'read' => $read,
                'created_at' => $r['created_at'],
            ];
        }
        return ['items' => $items, 'unread' => $unread];
    }

    /**
     * Daily WP-cron: remind members whose annual form is still open and deadline nears.
     */
    public static function run_daily_reminders() {
        global $wpdb;
        $settings = BCND_Core::get_settings();
        if (empty($settings['notifications_enabled'])) { return; }
        $year = (int) current_time('Y');
        $days = BCND_Core::days_until_deadline($settings, $year);
        if ($days < 0 || $days > 30) { return; }

        $mt = BCND_Database::t('members');
        $members = $wpdb->get_results("SELECT * FROM $mt WHERE status = 'active'", ARRAY_A);
        foreach ($members as $mrow) {
            $member = BCND_Core::format_member($mrow);
            $af = BCND_Database::t('annual_forms');
            $form = $wpdb->get_row($wpdb->prepare(
                "SELECT status FROM $af WHERE member_id = %d AND year = %d", $member['id'], $year), ARRAY_A);
            $submitted = $form && in_array($form['status'], ['ingediend', 'in_beoordeling', 'goedgekeurd'], true);
            if (!$submitted) {
                $msg = self::tpl('deadline_reminder', ['name' => $member['name'], 'days' => $days, 'year' => $year]);
                self::notify($member['user_id'], 'deadline_reminder', 'Deadline jaarformulier nadert', $msg, ['year' => $year]);
            }
        }
    }
}
