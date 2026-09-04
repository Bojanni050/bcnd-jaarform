<?php
if (!defined('ABSPATH')) { exit; }

class BCND_Database {

    public static function t($name) {
        global $wpdb;
        return $wpdb->prefix . 'bcnd_' . $name;
    }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $members = self::t('members');
        $training = self::t('training');
        $training_docs = self::t('training_documents');
        $consultations = self::t('consultations');
        $annual = self::t('annual_forms');
        $annual_items = self::t('annual_form_items');
        $history = self::t('status_history');
        $notifications = self::t('notifications');

        $sql = [];

        $sql[] = "CREATE TABLE $members (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(190) NOT NULL DEFAULT '',
            email VARCHAR(190) NOT NULL DEFAULT '',
            address VARCHAR(190) NOT NULL DEFAULT '',
            city VARCHAR(120) NOT NULL DEFAULT '',
            postal_code VARCHAR(20) NOT NULL DEFAULT '',
            member_number VARCHAR(60) NOT NULL DEFAULT '',
            license_since DATE NULL,
            phone VARCHAR(60) NOT NULL DEFAULT '',
            status VARCHAR(30) NOT NULL DEFAULT 'active',
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_id (user_id),
            KEY member_number (member_number),
            KEY status (status)
        ) $charset;";

        $sql[] = "CREATE TABLE $training (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT UNSIGNED NOT NULL,
            member_name VARCHAR(190) NOT NULL DEFAULT '',
            year SMALLINT UNSIGNED NOT NULL,
            date DATE NULL,
            hours DECIMAL(5,2) NOT NULL DEFAULT 0,
            organization VARCHAR(190) NOT NULL DEFAULT '',
            subject VARCHAR(255) NOT NULL DEFAULT '',
            content_explanation TEXT NULL,
            speaker VARCHAR(190) NOT NULL DEFAULT '',
            activity_type VARCHAR(40) NOT NULL DEFAULT 'externe_bijscholing',
            member_remarks TEXT NULL,
            points DECIMAL(5,2) NULL,
            admin_remark TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'ingediend',
            reviewed_by VARCHAR(190) NULL,
            reviewed_at DATETIME NULL,
            submitted_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY member_year (member_id, year),
            KEY status (status)
        ) $charset;";

        $sql[] = "CREATE TABLE $training_docs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT UNSIGNED NOT NULL,
            training_id BIGINT UNSIGNED NULL,
            stored_filename VARCHAR(255) NOT NULL,
            original_filename VARCHAR(255) NOT NULL DEFAULT '',
            mime VARCHAR(120) NOT NULL DEFAULT '',
            size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            doc_type VARCHAR(40) NOT NULL DEFAULT 'deelnamebewijs',
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            uploaded_by VARCHAR(190) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY member_id (member_id),
            KEY training_id (training_id)
        ) $charset;";

        $sql[] = "CREATE TABLE $consultations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT UNSIGNED NOT NULL,
            year SMALLINT UNSIGNED NOT NULL,
            total_consults INT UNSIGNED NOT NULL DEFAULT 0,
            first_consults INT UNSIGNED NOT NULL DEFAULT 0,
            followup_consults INT UNSIGNED NOT NULL DEFAULT 0,
            other_activities TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY member_year (member_id, year)
        ) $charset;";

        $sql[] = "CREATE TABLE $annual (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            member_id BIGINT UNSIGNED NOT NULL,
            member_name VARCHAR(190) NOT NULL DEFAULT '',
            year SMALLINT UNSIGNED NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'concept',
            deviation_reason TEXT NULL,
            submitted_at DATETIME NULL,
            submitted_by VARCHAR(190) NULL,
            reviewed_by VARCHAR(190) NULL,
            reviewed_at DATETIME NULL,
            admin_remark TEXT NULL,
            pdf_filename VARCHAR(255) NULL,
            applied_points_norm INT NULL,
            applied_consults_norm INT NULL,
            achieved_points DECIMAL(6,2) NULL,
            achieved_consults INT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY member_year (member_id, year),
            KEY status (status)
        ) $charset;";

        $sql[] = "CREATE TABLE $annual_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            annual_form_id BIGINT UNSIGNED NOT NULL,
            training_id BIGINT UNSIGNED NULL,
            date DATE NULL,
            hours DECIMAL(5,2) NOT NULL DEFAULT 0,
            organization VARCHAR(190) NOT NULL DEFAULT '',
            subject VARCHAR(255) NOT NULL DEFAULT '',
            content_explanation TEXT NULL,
            speaker VARCHAR(190) NOT NULL DEFAULT '',
            activity_type VARCHAR(40) NOT NULL DEFAULT '',
            points DECIMAL(5,2) NULL,
            PRIMARY KEY  (id),
            KEY annual_form_id (annual_form_id)
        ) $charset;";

        $sql[] = "CREATE TABLE $history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type VARCHAR(30) NOT NULL,
            entity_id VARCHAR(60) NOT NULL,
            action VARCHAR(60) NOT NULL,
            from_status VARCHAR(30) NULL,
            to_status VARCHAR(30) NULL,
            remark TEXT NULL,
            old_value TEXT NULL,
            new_value TEXT NULL,
            actor_id BIGINT UNSIGNED NULL,
            actor_name VARCHAR(190) NULL,
            actor_role VARCHAR(30) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY entity (entity_type, entity_id)
        ) $charset;";

        $sql[] = "CREATE TABLE $notifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            type VARCHAR(60) NOT NULL DEFAULT '',
            title VARCHAR(190) NOT NULL DEFAULT '',
            message TEXT NULL,
            related LONGTEXT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) $charset;";

        foreach ($sql as $s) { dbDelta($s); }

        update_option('bcnd_db_version', BCND_DB_VERSION);

        // Seed default settings if absent.
        if (get_option('bcnd_settings') === false) {
            update_option('bcnd_settings', BCND_Core::default_settings());
        }
    }

    public static function private_dir() {
        $up = wp_upload_dir();
        return trailingslashit($up['basedir']) . 'bcnd-private';
    }

    public static function ensure_private_dir() {
        $dir = self::private_dir();
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }
        $ht = $dir . '/.htaccess';
        if (!file_exists($ht)) {
            @file_put_contents($ht, "Require all denied\nDeny from all\n");
        }
        $idx = $dir . '/index.php';
        if (!file_exists($idx)) {
            @file_put_contents($idx, "<?php // Silence is golden.");
        }
    }
}
