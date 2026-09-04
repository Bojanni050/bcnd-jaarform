<?php
if (!defined('ABSPATH')) { exit; }

class BCND_Documents {

    const ALLOWED = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp'];
    const MAX = 15728640; // 15MB

    public static function upload($req) {
        global $wpdb;
        $files = $req->get_file_params();
        if (empty($files['file'])) {
            return new WP_Error('bcnd_no_file', 'Geen bestand ontvangen', ['status' => 400]);
        }
        $file = $files['file'];
        $name = $file['name'];
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED, true)) {
            return new WP_Error('bcnd_type', 'Bestandstype niet toegestaan (alleen PDF en afbeeldingen)', ['status' => 400]);
        }
        if ((int) $file['size'] > self::MAX) {
            return new WP_Error('bcnd_size', 'Bestand te groot (max 15MB)', ['status' => 400]);
        }
        // Validate real mime type.
        $check = wp_check_filetype_and_ext($file['tmp_name'], $name);
        if (empty($check['type'])) {
            return new WP_Error('bcnd_type', 'Bestandstype kon niet worden geverifieerd', ['status' => 400]);
        }

        $training_id = $req->get_param('training_id') ? (int) $req->get_param('training_id') : null;
        $is_admin = BCND_Roles::is_admin_user();

        if ($is_admin) {
            $member_id = null;
            if ($training_id) {
                $tt = BCND_Database::t('training');
                $member_id = (int) $wpdb->get_var($wpdb->prepare("SELECT member_id FROM $tt WHERE id = %d", $training_id));
            }
        } else {
            $m = BCND_Core::member_for_user(get_current_user_id());
            if (!$m) { return new WP_Error('bcnd_no_member', 'Geen lidprofiel', ['status' => 404]); }
            $member_id = $m['id'];
            if ($training_id) {
                $tt = BCND_Database::t('training');
                $owner = (int) $wpdb->get_var($wpdb->prepare("SELECT member_id FROM $tt WHERE id = %d", $training_id));
                if ($owner !== (int) $member_id) {
                    return new WP_Error('bcnd_forbidden', 'Geen toegang tot deze bijscholing', ['status' => 403]);
                }
            }
        }

        BCND_Database::ensure_private_dir();
        $dir = BCND_Database::private_dir();
        $stored = wp_generate_uuid4() . '.' . $ext;
        $dest = trailingslashit($dir) . $stored;
        if (!@move_uploaded_file($file['tmp_name'], $dest)) {
            if (!@copy($file['tmp_name'], $dest)) {
                return new WP_Error('bcnd_write', 'Opslaan mislukt', ['status' => 500]);
            }
        }
        @chmod($dest, 0640);

        $now = BCND_Core::now();
        $wpdb->insert(BCND_Database::t('training_documents'), [
            'member_id' => $member_id,
            'training_id' => $training_id,
            'stored_filename' => $stored,
            'original_filename' => sanitize_file_name($name),
            'mime' => $check['type'],
            'size' => (int) $file['size'],
            'doc_type' => sanitize_text_field($req->get_param('doc_type')) ?: 'deelnamebewijs',
            'is_deleted' => 0,
            'uploaded_by' => wp_get_current_user()->display_name,
            'created_at' => $now,
        ]);
        $id = (int) $wpdb->insert_id;
        return rest_ensure_response([
            'id' => $id,
            'original_filename' => sanitize_file_name($name),
            'content_type' => $check['type'],
            'size' => (int) $file['size'],
            'doc_type' => $req->get_param('doc_type') ?: 'deelnamebewijs',
        ]);
    }

    public static function list_docs($req) {
        global $wpdb;
        $t = BCND_Database::t('training_documents');
        $where = 'is_deleted = 0'; $args = [];
        if (!BCND_Roles::is_admin_user()) {
            $m = BCND_Core::member_for_user(get_current_user_id());
            if (!$m) { return rest_ensure_response([]); }
            $where .= ' AND member_id = %d'; $args[] = $m['id'];
        } elseif ($req->get_param('member_id')) {
            $where .= ' AND member_id = %d'; $args[] = (int) $req->get_param('member_id');
        }
        if ($req->get_param('training_id')) { $where .= ' AND training_id = %d'; $args[] = (int) $req->get_param('training_id'); }
        $sql = "SELECT * FROM $t WHERE $where ORDER BY created_at DESC";
        if ($args) { $sql = $wpdb->prepare($sql, $args); }
        $rows = $wpdb->get_results($sql, ARRAY_A);
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r['id'], 'training_id' => $r['training_id'] ? (int) $r['training_id'] : null,
                'original_filename' => $r['original_filename'], 'content_type' => $r['mime'],
                'size' => (int) $r['size'], 'doc_type' => $r['doc_type'], 'created_at' => $r['created_at'],
            ];
        }
        return rest_ensure_response($out);
    }

    public static function delete($req) {
        global $wpdb;
        $doc = self::get_doc((int) $req['id']);
        if (!$doc) { return new WP_Error('bcnd_not_found', 'Document niet gevonden', ['status' => 404]); }
        if (!self::can_access($doc)) { return new WP_Error('bcnd_forbidden', 'Geen toegang', ['status' => 403]); }
        $wpdb->update(BCND_Database::t('training_documents'), ['is_deleted' => 1], ['id' => (int) $req['id']]);
        return rest_ensure_response(['ok' => true]);
    }

    public static function download($req) {
        $doc = self::get_doc((int) $req['id']);
        if (!$doc || (int) $doc['is_deleted'] === 1) {
            return new WP_Error('bcnd_not_found', 'Document niet gevonden', ['status' => 404]);
        }
        if (!self::can_access($doc)) { return new WP_Error('bcnd_forbidden', 'Geen toegang', ['status' => 403]); }
        $path = trailingslashit(BCND_Database::private_dir()) . $doc['stored_filename'];
        if (!file_exists($path)) { return new WP_Error('bcnd_not_found', 'Bestand niet gevonden', ['status' => 404]); }
        nocache_headers();
        header('Content-Type: ' . $doc['mime']);
        header('Content-Disposition: inline; filename="' . $doc['original_filename'] . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    private static function get_doc($id) {
        global $wpdb;
        $t = BCND_Database::t('training_documents');
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id = %d", $id), ARRAY_A);
    }

    private static function can_access($doc) {
        if (BCND_Roles::is_admin_user()) { return true; }
        $m = BCND_Core::member_for_user(get_current_user_id());
        return $m && (int) $doc['member_id'] === (int) $m['id'];
    }
}
