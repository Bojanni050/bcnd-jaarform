<?php
if (!defined('ABSPATH')) { exit; }

class BCND_Members {

    /** Resolve the member row of the current user or 403. */
    public static function current_or_error() {
        $m = BCND_Core::member_for_user(get_current_user_id());
        if (!$m) {
            return new WP_Error('bcnd_no_member', 'Geen lidprofiel gekoppeld aan dit account', ['status' => 404]);
        }
        return $m;
    }

    public static function get_me() {
        $m = self::current_or_error();
        return is_wp_error($m) ? $m : rest_ensure_response($m);
    }

    public static function update_me($req) {
        global $wpdb;
        $m = self::current_or_error();
        if (is_wp_error($m)) { return $m; }
        $allowed = ['address', 'city', 'postal_code', 'phone'];
        $data = self::collect($req, $allowed);
        if ($data) {
            $data['updated_at'] = BCND_Core::now();
            $wpdb->update(BCND_Database::t('members'), $data, ['id' => $m['id']]);
        }
        return rest_ensure_response(BCND_Core::get_member($m['id']));
    }

    public static function list_all($req) {
        global $wpdb;
        $t = BCND_Database::t('members');
        $where = '1=1'; $args = [];
        $status = sanitize_text_field($req->get_param('status'));
        if ($status) { $where .= ' AND status = %s'; $args[] = $status; }
        $q = sanitize_text_field($req->get_param('q'));
        if ($q) {
            $like = '%' . $wpdb->esc_like($q) . '%';
            // member_number (lidnummer) lives on the WP user, not this table.
            $where .= " AND (name LIKE %s OR email LIKE %s OR user_id IN (
                SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'lidnummer' AND meta_value LIKE %s
            ))";
            array_push($args, $like, $like, $like);
        }
        $sql = "SELECT * FROM $t WHERE $where ORDER BY name ASC";
        if ($args) { $sql = $wpdb->prepare($sql, $args); }
        $rows = $wpdb->get_results($sql, ARRAY_A);
        $out = array_map(['BCND_Core', 'format_member'], $rows);
        return rest_ensure_response($out);
    }

    public static function get_one($req) {
        $m = BCND_Core::get_member((int) $req['id']);
        if (!$m) { return new WP_Error('bcnd_not_found', 'Lid niet gevonden', ['status' => 404]); }
        return rest_ensure_response($m);
    }

    public static function create($req) {
        global $wpdb;
        $email = sanitize_email($req->get_param('email'));
        $name = sanitize_text_field($req->get_param('name'));
        $password = (string) $req->get_param('password');
        $license = sanitize_text_field($req->get_param('license_since'));
        if (!$email || !$name || !$password || !$license) {
            return new WP_Error('bcnd_invalid', 'Naam, e-mail, wachtwoord en licentiedatum zijn verplicht', ['status' => 400]);
        }
        if (email_exists($email) || username_exists($email)) {
            return new WP_Error('bcnd_exists', 'E-mailadres is al in gebruik', ['status' => 400]);
        }
        $uid = wp_insert_user([
            'user_login' => $email,
            'user_email' => $email,
            'user_pass' => $password,
            'display_name' => $name,
            'role' => 'bcnd_licensed',
        ]);
        if (is_wp_error($uid)) { return $uid; }

        // Lidnummer and licentiedatum live on the WP user (JetEngine fields), not here.
        update_user_meta($uid, 'lidnummer', sanitize_text_field($req->get_param('member_number')));
        update_user_meta($uid, 'license_since', $license);

        $now = BCND_Core::now();
        $wpdb->insert(BCND_Database::t('members'), [
            'user_id' => $uid,
            'name' => $name,
            'email' => $email,
            'address' => sanitize_text_field($req->get_param('address')),
            'city' => sanitize_text_field($req->get_param('city')),
            'postal_code' => sanitize_text_field($req->get_param('postal_code')),
            'phone' => sanitize_text_field($req->get_param('phone')),
            'status' => 'active',
            'notes' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int) $wpdb->insert_id;
        BCND_Audit_Log::add('member', $id, 'lid_aangemaakt', ['remark' => $name]);
        return rest_ensure_response(BCND_Core::get_member($id));
    }

    public static function update_one($req) {
        global $wpdb;
        $id = (int) $req['id'];
        $existing = BCND_Core::get_member($id);
        if (!$existing) { return new WP_Error('bcnd_not_found', 'Lid niet gevonden', ['status' => 404]); }

        // Lidnummer and licentiedatum live on the WP user (JetEngine fields), not here.
        $meta_changed = false;
        $member_number = $req->get_param('member_number');
        if ($member_number !== null) {
            update_user_meta($existing['user_id'], 'lidnummer', sanitize_text_field($member_number));
            $meta_changed = true;
        }
        $license_since = $req->get_param('license_since');
        if ($license_since !== null) {
            update_user_meta($existing['user_id'], 'license_since', sanitize_text_field($license_since));
            $meta_changed = true;
        }

        $allowed = ['name', 'address', 'city', 'postal_code', 'phone', 'status', 'notes'];
        $data = self::collect($req, $allowed);
        if (!$data && !$meta_changed) { return new WP_Error('bcnd_invalid', 'Geen wijzigingen', ['status' => 400]); }
        if ($data) {
            $data['updated_at'] = BCND_Core::now();
            $wpdb->update(BCND_Database::t('members'), $data, ['id' => $id]);
        }
        if (isset($data['name'])) {
            $u = get_userdata($existing['user_id']);
            if ($u) { wp_update_user(['ID' => $existing['user_id'], 'display_name' => $data['name']]); }
        }
        BCND_Audit_Log::add('member', $id, 'lid_gewijzigd', ['old' => $existing, 'new' => $data]);
        return rest_ensure_response(BCND_Core::get_member($id));
    }

    /**
     * One-time migration: link existing WordPress accounts (JetEngine
     * "Licentielid" members) to a bcnd_members row and grant the
     * bcnd_licensed role, without touching or copying their JetEngine data.
     * Safe to run more than once — already-linked users are skipped.
     */
    public static function migrate_legacy($req) {
        global $wpdb;
        $t = BCND_Database::t('members');

        // JetEngine stores "Lidmaatschap" (type_lid) as a serialized checkbox
        // array; users with no such meta at all (no JetEngine profile yet)
        // are excluded by this LIKE match, which is exactly what we want.
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'type_lid' AND meta_value LIKE %s",
            '%"Licentielid"%'
        ));

        $migrated = []; $skipped = [];
        foreach ($user_ids as $user_id) {
            $user_id = (int) $user_id;
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $t WHERE user_id = %d", $user_id));
            if ($existing) {
                $skipped[] = ['user_id' => $user_id, 'reason' => 'al gekoppeld'];
                continue;
            }
            $u = get_userdata($user_id);
            if (!$u) {
                $skipped[] = ['user_id' => $user_id, 'reason' => 'WP-account niet gevonden'];
                continue;
            }
            $now = BCND_Core::now();
            $wpdb->insert($t, [
                'user_id' => $user_id,
                'name' => $u->display_name,
                'email' => $u->user_email,
                'address' => '', 'city' => '', 'postal_code' => '', 'phone' => '',
                'status' => 'active', 'notes' => '',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $u->add_role('bcnd_licensed');
            $id = (int) $wpdb->insert_id;
            BCND_Audit_Log::add('member', $id, 'lid_gemigreerd', ['remark' => 'Gekoppeld vanuit bestaand WP-account']);
            $migrated[] = ['user_id' => $user_id, 'name' => $u->display_name, 'email' => $u->user_email,
                'member_number' => BCND_Core::member_number_of($user_id), 'license_since' => BCND_Core::license_since_of($user_id)];
        }

        return rest_ensure_response([
            'total_licentieleden_gevonden' => count($user_ids),
            'gemigreerd' => $migrated,
            'overgeslagen' => $skipped,
        ]);
    }

    private static function collect($req, $allowed) {
        $data = [];
        foreach ($allowed as $k) {
            $v = $req->get_param($k);
            if ($v !== null) {
                $data[$k] = ($k === 'notes') ? sanitize_textarea_field($v) : sanitize_text_field($v);
            }
        }
        return $data;
    }
}
