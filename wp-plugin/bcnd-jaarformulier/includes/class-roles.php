<?php
if (!defined('ABSPATH')) { exit; }

class BCND_Roles {

    public static function caps() {
        return [
            'bcnd_manage_members',
            'bcnd_manage_training',
            'bcnd_review_training',
            'bcnd_manage_consultations',
            'bcnd_review_annual_forms',
            'bcnd_manage_documents',
            'bcnd_manage_settings',
        ];
    }

    public static function install() {
        // BCND Administrator role.
        $admin_caps = ['read' => true];
        foreach (self::caps() as $c) { $admin_caps[$c] = true; }
        remove_role('bcnd_admin');
        add_role('bcnd_admin', 'BCND Administrator', $admin_caps);

        // BCND member (Licentielid).
        if (!get_role('bcnd_member')) {
            add_role('bcnd_member', 'BCND Licentielid', ['read' => true, 'bcnd_member' => true]);
        } else {
            $r = get_role('bcnd_member');
            $r->add_cap('read');
            $r->add_cap('bcnd_member');
        }

        // Give every capability to the WordPress administrator as well.
        $wp_admin = get_role('administrator');
        if ($wp_admin) {
            foreach (self::caps() as $c) { $wp_admin->add_cap($c); }
        }
    }

    public static function is_admin_user($user = null) {
        if ($user === null) { $user = wp_get_current_user(); }
        if (!$user || !$user->exists()) { return false; }
        return $user->has_cap('bcnd_review_training') || $user->has_cap('manage_options')
            || in_array('bcnd_admin', (array) $user->roles, true);
    }
}
