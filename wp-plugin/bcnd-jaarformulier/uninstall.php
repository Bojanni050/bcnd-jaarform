<?php
/**
 * Uninstall: only remove settings/options. Data tables are preserved by default
 * so that historical, approved annual forms remain auditable. To fully purge,
 * define BCND_REMOVE_ALL_DATA as true in wp-config.php before uninstalling.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }

delete_option('bcnd_db_version');

if (defined('BCND_REMOVE_ALL_DATA') && BCND_REMOVE_ALL_DATA) {
    global $wpdb;
    $tables = ['training_documents', 'annual_form_items', 'annual_forms', 'training',
               'consultations', 'status_history', 'notifications', 'members'];
    foreach ($tables as $t) {
        $name = $wpdb->prefix . 'bcnd_' . $t;
        $wpdb->query("DROP TABLE IF EXISTS $name");
    }
    delete_option('bcnd_settings');
    remove_role('bcnd_admin');
    remove_role('bcnd_member');
}
