<?php

defined('ABSPATH') || exit;

class Noted_Activator {

    public static function activate($network_wide = false) {
        if (is_multisite() && $network_wide) {
            $sites = get_sites(['fields' => 'ids']);
            foreach ($sites as $site_id) {
                switch_to_blog($site_id);
                self::create_tables();
                Noted_Capabilities::activate();
                self::set_defaults();
                restore_current_blog();
            }
        } else {
            self::create_tables();
            Noted_Capabilities::activate();
            self::set_defaults();
        }

        flush_rewrite_rules();
    }

    public static function deactivate($network_wide = false) {
        if (is_multisite() && $network_wide) {
            $sites = get_sites(['fields' => 'ids']);
            foreach ($sites as $site_id) {
                switch_to_blog($site_id);
                Noted_Capabilities::deactivate();
                Noted_Cron::clear_schedules();
                restore_current_blog();
            }
        } else {
            Noted_Capabilities::deactivate();
            Noted_Cron::clear_schedules();
        }

        flush_rewrite_rules();
    }

    /**
     * Create tables when a new site is added to the network.
     */
    public static function on_new_site($new_site) {
        if (!is_plugin_active_for_network(NOTED_PLUGIN_BASENAME)) {
            return;
        }

        switch_to_blog($new_site->blog_id);
        self::create_tables();
        self::set_defaults();
        Noted_Capabilities::activate();
        restore_current_blog();
    }

    public static function maybe_update_db() {
        $installed_version = get_option('noted_db_version', '0');
        if (version_compare($installed_version, NOTED_DB_VERSION, '<')) {
            self::create_tables();
            update_option('noted_db_version', NOTED_DB_VERSION);
        }
    }

    private static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $prefix  = $wpdb->prefix;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // dbDelta requires: each field on its own line, two spaces before
        // PRIMARY KEY definition, KEY (not INDEX) for secondary indexes,
        // no trailing commas before closing paren, no FK constraints.

        $tables = [];

        // 1. Pages (share tokens are now page-scoped).
        $tables[] = "CREATE TABLE {$prefix}noted_pages (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  url_path varchar(2048) NOT NULL,
  title varchar(255) DEFAULT NULL,
  sort_order int(11) DEFAULT 0,
  access_token varchar(64) DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY uk_url_path (url_path(191)),
  UNIQUE KEY uk_access_token (access_token)
) {$charset};";

        // 2. Pins
        $tables[] = "CREATE TABLE {$prefix}noted_pins (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  page_id bigint(20) unsigned NOT NULL,
  x_percent double NOT NULL,
  y_percent double NOT NULL,
  css_selector text DEFAULT NULL,
  selector_offset_x double DEFAULT NULL,
  selector_offset_y double DEFAULT NULL,
  viewport_width int(11) DEFAULT NULL,
  scroll_y double DEFAULT NULL,
  breakpoint varchar(10) NOT NULL DEFAULT 'desktop',
  pin_number int(10) unsigned NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'open',
  author_type varchar(10) NOT NULL,
  author_user_id bigint(20) unsigned DEFAULT NULL,
  author_guest_id bigint(20) unsigned DEFAULT NULL,
  body text NOT NULL,
  attachment_ids text DEFAULT NULL,
  resolved_at datetime DEFAULT NULL,
  resolved_by bigint(20) unsigned DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY idx_page (page_id),
  KEY idx_status (status),
  KEY idx_breakpoint (breakpoint),
  KEY idx_page_status (page_id,status)
) {$charset};";

        // 3. Comments
        $tables[] = "CREATE TABLE {$prefix}noted_comments (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  pin_id bigint(20) unsigned NOT NULL,
  parent_id bigint(20) unsigned DEFAULT NULL,
  author_type varchar(10) NOT NULL,
  author_user_id bigint(20) unsigned DEFAULT NULL,
  author_guest_id bigint(20) unsigned DEFAULT NULL,
  body text NOT NULL,
  attachment_ids text DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY idx_pin (pin_id)
) {$charset};";

        // Annotations and text-edits tables are owned by the Pro plugin — not created in free.

        // 4. Guests (now bound to page_id).
        $tables[] = "CREATE TABLE {$prefix}noted_guests (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  page_id bigint(20) unsigned NOT NULL,
  name varchar(255) NOT NULL,
  email varchar(255) DEFAULT NULL,
  avatar_hash varchar(32) DEFAULT NULL,
  token varchar(64) NOT NULL,
  last_seen_at datetime DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY idx_page (page_id),
  KEY idx_token (token)
) {$charset};";

        $sql = implode("\n", $tables);
        dbDelta($sql);

        // Add foreign key constraints separately (best-effort).
        self::add_foreign_keys();
    }

    private static function add_foreign_keys() {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $fks = [
            "ALTER TABLE {$prefix}noted_pins ADD CONSTRAINT fk_pins_page
             FOREIGN KEY (page_id) REFERENCES {$prefix}noted_pages(id) ON DELETE CASCADE",
            "ALTER TABLE {$prefix}noted_comments ADD CONSTRAINT fk_comments_pin
             FOREIGN KEY (pin_id) REFERENCES {$prefix}noted_pins(id) ON DELETE CASCADE",
            "ALTER TABLE {$prefix}noted_comments ADD CONSTRAINT fk_comments_parent
             FOREIGN KEY (parent_id) REFERENCES {$prefix}noted_comments(id) ON DELETE CASCADE",
            "ALTER TABLE {$prefix}noted_guests ADD CONSTRAINT fk_guests_page
             FOREIGN KEY (page_id) REFERENCES {$prefix}noted_pages(id) ON DELETE CASCADE",
        ];

        foreach ($fks as $fk) {
            $constraint_name = '';
            if (preg_match('/CONSTRAINT\s+(\w+)/', $fk, $m)) {
                $constraint_name = $m[1];
            }

            try {
                $exists = $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                     WHERE CONSTRAINT_SCHEMA = %s AND CONSTRAINT_NAME = %s",
                    DB_NAME,
                    $constraint_name
                ));

                if (!$exists) {
                    $wpdb->query($fk); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
                }
            } catch (\Exception $e) {
                // Log but don't fail activation.
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("Noted: Could not add FK {$constraint_name}: " . $e->getMessage()); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                }
            }
        }
    }

    private static function set_defaults() {
        add_option('noted_visibility', 'param');
        add_option('noted_toolbar_position', 'top');
        add_option('noted_brand_color', '#E06042');
        add_option('noted_db_version', NOTED_DB_VERSION);
        add_option('noted_show_welcome', true);
        add_option('noted_notify_on_new_pin', true);
        add_option('noted_notify_on_reply', true);
        add_option('noted_notification_emails', get_option('admin_email'));
        add_option('noted_show_branding', false);
    }
}
