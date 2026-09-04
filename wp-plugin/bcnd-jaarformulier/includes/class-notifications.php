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
                $headers = [
                    'Content-Type: text/html; charset=UTF-8',
                    'From: ' . self::from_name() . ' <' . self::from_address() . '>',
                ];
                wp_mail($user->user_email, '[BCND] ' . $title, self::html_email($title, $message), $headers);
            }
        }
    }

    private static function from_name() {
        $name = get_bloginfo('name');
        return $name ? $name . ' — BCND' : 'BCND Nascholingsadministratie';
    }

    private static function from_address() {
        $admin = get_option('admin_email');
        return $admin ? $admin : ('noreply@' . wp_parse_url(home_url(), PHP_URL_HOST));
    }

    private static function html_email($title, $message) {
        $t = esc_html($title);
        $m = nl2br(esc_html($message));
        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:560px;margin:0 auto;border:1px solid #E2DFD3;border-radius:10px;overflow:hidden">'
            . '<div style="background:#1E3F33;color:#fff;padding:18px 24px;font-size:18px;font-weight:bold">BCND Nascholingsadministratie</div>'
            . '<div style="padding:24px">'
            . '<h2 style="color:#1E3F33;font-size:16px;margin:0 0 12px">' . $t . '</h2>'
            . '<p style="color:#4A463B;font-size:14px;line-height:1.6;margin:0">' . $m . '</p>'
            . '</div>'
            . '<div style="background:#F0EEE4;color:#7A7563;padding:14px 24px;font-size:11px">Dit is een automatisch bericht van de BCND Nascholingsadministratie.</div>'
            . '</div>';
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
