<?php
if (!defined('ABSPATH')) { exit; }

class BCND_Audit_Log {

    /**
     * Record a status/administrative change.
     */
    public static function add($entity_type, $entity_id, $action, $opts = []) {
        global $wpdb;
        $u = wp_get_current_user();
        $actor_id = ($u && $u->exists()) ? (int) $u->ID : null;
        $actor_name = ($u && $u->exists()) ? $u->display_name : 'Systeem';
        $actor_role = ($u && $u->exists()) ? BCND_Core::role_of($u) : 'system';

        $wpdb->insert(BCND_Database::t('status_history'), [
            'entity_type' => $entity_type,
            'entity_id' => (string) $entity_id,
            'action' => $action,
            'from_status' => isset($opts['from']) ? $opts['from'] : null,
            'to_status' => isset($opts['to']) ? $opts['to'] : null,
            'remark' => isset($opts['remark']) ? $opts['remark'] : null,
            'old_value' => isset($opts['old']) ? maybe_serialize($opts['old']) : null,
            'new_value' => isset($opts['new']) ? maybe_serialize($opts['new']) : null,
            'actor_id' => $actor_id,
            'actor_name' => $actor_name,
            'actor_role' => $actor_role,
            'created_at' => BCND_Core::now(),
        ]);
    }

    public static function history($entity_type, $entity_id) {
        global $wpdb;
        $t = BCND_Database::t('status_history');
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $t WHERE entity_type = %s AND entity_id = %s ORDER BY created_at ASC, id ASC",
            $entity_type, (string) $entity_id), ARRAY_A);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'action' => $r['action'],
                'from_status' => $r['from_status'],
                'to_status' => $r['to_status'],
                'remark' => $r['remark'],
                'actor_name' => $r['actor_name'],
                'actor_role' => $r['actor_role'],
                'created_at' => $r['created_at'],
            ];
        }
        return $out;
    }
}
