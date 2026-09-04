<?php
if (!defined('ABSPATH')) { exit; }

class BCND_Settings {

    public static function read($req) {
        return rest_ensure_response(BCND_Core::get_settings());
    }

    public static function read_public($req) {
        $s = BCND_Core::get_settings();
        return rest_ensure_response([
            'points_norm' => $s['points_norm'],
            'consults_norms' => $s['consults_norms'],
            'deadline_day' => $s['deadline_day'],
            'deadline_month' => $s['deadline_month'],
        ]);
    }

    public static function update($req) {
        $updates = [];
        if ($req->get_param('points_norm') !== null) { $updates['points_norm'] = (int) $req->get_param('points_norm'); }
        if ($req->get_param('deadline_day') !== null) { $updates['deadline_day'] = (int) $req->get_param('deadline_day'); }
        if ($req->get_param('deadline_month') !== null) { $updates['deadline_month'] = (int) $req->get_param('deadline_month'); }
        if ($req->get_param('notifications_enabled') !== null) { $updates['notifications_enabled'] = (bool) $req->get_param('notifications_enabled'); }
        $cn = $req->get_param('consults_norms');
        if (is_array($cn)) {
            $clean = [];
            foreach (['1', '2', '3', '4'] as $k) { if (isset($cn[$k])) { $clean[$k] = (int) $cn[$k]; } }
            if ($clean) { $updates['consults_norms'] = $clean; }
        }
        $tpl = $req->get_param('email_templates');
        if (is_array($tpl)) {
            $clean = [];
            foreach ($tpl as $k => $v) { $clean[sanitize_key($k)] = sanitize_textarea_field($v); }
            $updates['email_templates'] = array_replace(BCND_Core::get_settings()['email_templates'], $clean);
        }
        BCND_Audit_Log::add('settings', 'global', 'instellingen_gewijzigd', ['new' => $updates]);
        return rest_ensure_response(BCND_Core::save_settings($updates));
    }
}
