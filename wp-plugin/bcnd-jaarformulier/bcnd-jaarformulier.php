<?php
/**
 * Plugin Name: BCND Jaarformulier & Nascholingsadministratie
 * Description: Zelfstandige administratie voor bij- en nascholingen, consulten en jaarformulieren van BCND licentieleden. Bevat ledenportaal (shortcode [bcnd_portal]) en een beheeromgeving met REST API, eigen database, PDF-generatie en notificaties.
 * Version: 1.0.0
 * Author: BCND
 * Text Domain: bcnd-jaarformulier
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) { exit; }

define('BCND_VERSION', '1.2.0');
define('BCND_DB_VERSION', '1.2.0');
define('BCND_PLUGIN_FILE', __FILE__);
define('BCND_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BCND_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once BCND_PLUGIN_DIR . 'includes/class-database.php';
require_once BCND_PLUGIN_DIR . 'includes/class-roles.php';
require_once BCND_PLUGIN_DIR . 'includes/class-core.php';
require_once BCND_PLUGIN_DIR . 'includes/class-audit-log.php';
require_once BCND_PLUGIN_DIR . 'includes/class-notifications.php';
require_once BCND_PLUGIN_DIR . 'includes/class-pdf.php';
require_once BCND_PLUGIN_DIR . 'includes/class-members.php';
require_once BCND_PLUGIN_DIR . 'includes/class-training.php';
require_once BCND_PLUGIN_DIR . 'includes/class-consultations.php';
require_once BCND_PLUGIN_DIR . 'includes/class-annual-forms.php';
require_once BCND_PLUGIN_DIR . 'includes/class-documents.php';
require_once BCND_PLUGIN_DIR . 'includes/class-admin-controller.php';
require_once BCND_PLUGIN_DIR . 'includes/class-settings.php';
require_once BCND_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once BCND_PLUGIN_DIR . 'includes/class-frontend.php';

/**
 * Activation: create/upgrade schema, roles, private upload dir, schedule cron.
 * Never destroys existing data.
 */
function bcnd_activate() {
    BCND_Database::install();
    BCND_Roles::install();
    BCND_Database::ensure_private_dir();
    if (!wp_next_scheduled('bcnd_daily_cron')) {
        wp_schedule_event(time() + 3600, 'daily', 'bcnd_daily_cron');
    }
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'bcnd_activate');

function bcnd_deactivate() {
    $ts = wp_next_scheduled('bcnd_daily_cron');
    if ($ts) { wp_unschedule_event($ts, 'bcnd_daily_cron'); }
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'bcnd_deactivate');

// Run schema upgrades when the DB version changes (safe, additive via dbDelta).
add_action('plugins_loaded', function () {
    if (get_option('bcnd_db_version') !== BCND_DB_VERSION) {
        BCND_Database::install();
        BCND_Roles::install();
        BCND_Database::ensure_private_dir();
    }
});

// REST API
add_action('rest_api_init', function () {
    BCND_REST_API::register_routes();
});

// Frontend shortcode + admin menu + asset enqueue
add_action('init', ['BCND_Frontend', 'init']);
add_action('admin_menu', ['BCND_Frontend', 'register_admin_menu']);

// Daily reminders
add_action('bcnd_daily_cron', ['BCND_Notifications', 'run_daily_reminders']);

// When a WP user is deleted, soft-detach the member row (keep historical data).
add_action('deleted_user', function ($user_id) {
    global $wpdb;
    $t = $wpdb->prefix . 'bcnd_members';
    $wpdb->update($t, ['status' => 'inactive'], ['user_id' => $user_id]);
});
