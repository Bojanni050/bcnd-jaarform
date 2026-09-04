<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Registers the namespaced REST API: /wp-json/bcnd/v1/...
 * Every endpoint enforces server-side authorization.
 */
class BCND_REST_API {

    const NS = 'bcnd/v1';

    public static function logged_in() {
        return is_user_logged_in();
    }

    public static function is_admin() {
        return is_user_logged_in() && BCND_Roles::is_admin_user();
    }

    public static function register_routes() {
        $ns = self::NS;
        $member = ['permission_callback' => [__CLASS__, 'logged_in']];
        $admin = ['permission_callback' => [__CLASS__, 'is_admin']];

        // Identity
        register_rest_route($ns, '/auth/me', array_merge($member, [
            'methods' => 'GET',
            'callback' => function () {
                $id = BCND_Core::current_identity();
                return $id ? rest_ensure_response($id) : new WP_Error('bcnd_auth', 'Niet geautoriseerd', ['status' => 401]);
            },
        ]));

        // Members
        register_rest_route($ns, '/members/me', [
            ['methods' => 'GET', 'callback' => ['BCND_Members', 'get_me'], 'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'PUT', 'callback' => ['BCND_Members', 'update_me'], 'permission_callback' => [__CLASS__, 'logged_in']],
        ]);
        register_rest_route($ns, '/members', [
            ['methods' => 'GET', 'callback' => ['BCND_Members', 'list_all'], 'permission_callback' => function () { return current_user_can('bcnd_manage_members') || self::is_admin(); }],
            ['methods' => 'POST', 'callback' => ['BCND_Members', 'create'], 'permission_callback' => function () { return current_user_can('bcnd_manage_members') || self::is_admin(); }],
        ]);
        register_rest_route($ns, '/members/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => ['BCND_Members', 'get_one'], 'permission_callback' => function () { return current_user_can('bcnd_manage_members') || self::is_admin(); }],
            ['methods' => 'PUT', 'callback' => ['BCND_Members', 'update_one'], 'permission_callback' => function () { return current_user_can('bcnd_manage_members') || self::is_admin(); }],
        ]);

        // Trainings
        register_rest_route($ns, '/trainings', [
            ['methods' => 'GET', 'callback' => ['BCND_Training', 'list_all'], 'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'POST', 'callback' => ['BCND_Training', 'create'], 'permission_callback' => [__CLASS__, 'logged_in']],
        ]);
        register_rest_route($ns, '/trainings/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => ['BCND_Training', 'get_one'], 'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'PUT', 'callback' => ['BCND_Training', 'update'], 'permission_callback' => [__CLASS__, 'logged_in']],
        ]);
        register_rest_route($ns, '/trainings/(?P<id>\d+)/history', $member + [
            'methods' => 'GET', 'callback' => ['BCND_Training', 'history']]);
        register_rest_route($ns, '/trainings/(?P<id>\d+)/submit', $member + [
            'methods' => 'POST', 'callback' => ['BCND_Training', 'submit']]);
        register_rest_route($ns, '/trainings/(?P<id>\d+)/review', [
            'methods' => 'POST', 'callback' => ['BCND_Training', 'review'],
            'permission_callback' => function () { return current_user_can('bcnd_review_training') || self::is_admin(); }]);

        // Consultations
        register_rest_route($ns, '/consults', [
            ['methods' => 'GET', 'callback' => ['BCND_Consultations', 'get'], 'permission_callback' => [__CLASS__, 'logged_in']],
            ['methods' => 'PUT', 'callback' => ['BCND_Consultations', 'upsert'], 'permission_callback' => [__CLASS__, 'logged_in']],
        ]);

        // Annual forms
        register_rest_route($ns, '/annual-forms/overview', $member + ['methods' => 'GET', 'callback' => ['BCND_Annual_Forms', 'overview']]);
        register_rest_route($ns, '/annual-forms', $member + ['methods' => 'GET', 'callback' => ['BCND_Annual_Forms', 'get_form']]);
        register_rest_route($ns, '/annual-forms/(?P<year>\d+)/submit', $member + ['methods' => 'POST', 'callback' => ['BCND_Annual_Forms', 'submit']]);
        register_rest_route($ns, '/annual-forms/admin/list', ['methods' => 'GET', 'callback' => ['BCND_Annual_Forms', 'admin_list'], 'permission_callback' => function () { return current_user_can('bcnd_review_annual_forms') || self::is_admin(); }]);
        register_rest_route($ns, '/annual-forms/admin/(?P<id>\d+)', ['methods' => 'GET', 'callback' => ['BCND_Annual_Forms', 'admin_detail'], 'permission_callback' => function () { return current_user_can('bcnd_review_annual_forms') || self::is_admin(); }]);
        register_rest_route($ns, '/annual-forms/admin/(?P<id>\d+)/review', ['methods' => 'POST', 'callback' => ['BCND_Annual_Forms', 'review'], 'permission_callback' => function () { return current_user_can('bcnd_review_annual_forms') || self::is_admin(); }]);
        register_rest_route($ns, '/annual-forms/admin/(?P<id>\d+)/generate-pdf', ['methods' => 'POST', 'callback' => ['BCND_Annual_Forms', 'generate_pdf'], 'permission_callback' => function () { return current_user_can('bcnd_review_annual_forms') || self::is_admin(); }]);
        register_rest_route($ns, '/annual-forms/(?P<id>\d+)/history', $member + ['methods' => 'GET', 'callback' => ['BCND_Annual_Forms', 'history']]);
        register_rest_route($ns, '/annual-forms/(?P<id>\d+)/pdf', $member + ['methods' => 'GET', 'callback' => ['BCND_Annual_Forms', 'get_pdf']]);

        // Documents
        register_rest_route($ns, '/documents/upload', $member + ['methods' => 'POST', 'callback' => ['BCND_Documents', 'upload']]);
        register_rest_route($ns, '/documents', $member + ['methods' => 'GET', 'callback' => ['BCND_Documents', 'list_docs']]);
        register_rest_route($ns, '/documents/(?P<id>\d+)', $member + ['methods' => 'DELETE', 'callback' => ['BCND_Documents', 'delete']]);
        register_rest_route($ns, '/documents/(?P<id>\d+)/download', $member + ['methods' => 'GET', 'callback' => ['BCND_Documents', 'download']]);

        // Admin
        register_rest_route($ns, '/admin/dashboard', $admin + ['methods' => 'GET', 'callback' => ['BCND_Admin_Controller', 'dashboard']]);
        register_rest_route($ns, '/admin/review-queue', $admin + ['methods' => 'GET', 'callback' => ['BCND_Admin_Controller', 'review_queue']]);

        // Settings
        register_rest_route($ns, '/settings', [
            ['methods' => 'GET', 'callback' => ['BCND_Settings', 'read'], 'permission_callback' => function () { return current_user_can('bcnd_manage_settings') || self::is_admin(); }],
            ['methods' => 'PUT', 'callback' => ['BCND_Settings', 'update'], 'permission_callback' => function () { return current_user_can('bcnd_manage_settings') || self::is_admin(); }],
        ]);
        register_rest_route($ns, '/settings/public', $member + ['methods' => 'GET', 'callback' => ['BCND_Settings', 'read_public']]);

        // Notifications
        register_rest_route($ns, '/notifications', $member + ['methods' => 'GET', 'callback' => function () {
            return rest_ensure_response(BCND_Notifications::list_for_user(get_current_user_id()));
        }]);
        register_rest_route($ns, '/notifications/(?P<id>\d+)/read', $member + ['methods' => 'POST', 'callback' => function ($req) {
            global $wpdb;
            $wpdb->update(BCND_Database::t('notifications'), ['is_read' => 1], ['id' => (int) $req['id'], 'user_id' => get_current_user_id()]);
            return rest_ensure_response(['ok' => true]);
        }]);
        register_rest_route($ns, '/notifications/read-all', $member + ['methods' => 'POST', 'callback' => function () {
            global $wpdb;
            $wpdb->update(BCND_Database::t('notifications'), ['is_read' => 1], ['user_id' => get_current_user_id(), 'is_read' => 0]);
            return rest_ensure_response(['ok' => true]);
        }]);
    }
}
