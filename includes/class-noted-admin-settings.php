<?php

defined('ABSPATH') || exit;

class Noted_Admin_Settings {

    public static function render() {
        $saved = false;

        // Handle settings form.
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['noted_settings_nonce'])) {
            if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['noted_settings_nonce'])), 'noted_save_settings')) {
                wp_die(esc_html__('Security check failed.', 'noted-visual-feedback'));
            }

            update_option('noted_visibility', sanitize_text_field(wp_unslash($_POST['noted_visibility'] ?? 'param')));
            update_option('noted_brand_color', sanitize_hex_color(wp_unslash($_POST['noted_brand_color'] ?? '#F5A623')) ?: '#F5A623');
            update_option('noted_toolbar_position', sanitize_text_field(wp_unslash($_POST['noted_toolbar_position'] ?? 'top')));
            update_option('noted_show_branding', !empty($_POST['noted_show_branding']));

            $saved = true;
        }

        // Handle permissions form.
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['noted_permissions_nonce'])) {
            if (wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['noted_permissions_nonce'])), 'noted_save_permissions')) {
                $caps = [
                    Noted_Capabilities::MANAGE_REVIEWS,
                    Noted_Capabilities::VIEW_REVIEWS,
                    Noted_Capabilities::RESOLVE_PINS,
                    Noted_Capabilities::EXPORT_REVIEWS,
                ];
                $roles = wp_roles()->roles;

                foreach ($roles as $role_name => $role_data) {
                    $role = get_role($role_name);
                    if (!$role) {
                        continue;
                    }
                    foreach ($caps as $cap) {
                        $field = "noted_perm_{$role_name}_{$cap}";
                        if ($role_name === 'administrator' && $cap === Noted_Capabilities::MANAGE_REVIEWS) {
                            $role->add_cap($cap);
                            continue;
                        }
                        if (!empty($_POST[$field])) {
                            $role->add_cap($cap);
                        } else {
                            $role->remove_cap($cap);
                        }
                    }
                }
                $saved = true;
            }
        }

        // Handle notification settings.
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['noted_notifications_nonce'])) {
            if (wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['noted_notifications_nonce'])), 'noted_save_notifications')) {
                $notif = [
                    'enabled'       => !empty($_POST['notif_enabled']),
                    'on_new_pin'    => !empty($_POST['notif_on_new_pin']),
                    'on_reply'      => !empty($_POST['notif_on_reply']),
                    'on_resolved'   => !empty($_POST['notif_on_resolved']),
                    'mode'          => sanitize_text_field(wp_unslash($_POST['notif_mode'] ?? 'immediate')),
                    'digest_time'   => sanitize_text_field(wp_unslash($_POST['notif_digest_time'] ?? '09:00')),
                    'default_email' => sanitize_email(wp_unslash($_POST['notif_default_email'] ?? '')),
                ];
                update_option('noted_notification_settings', $notif);
                $saved = true;
            }
        }

        // Handle data deletion.
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['noted_delete_data_nonce'])) {
            if (wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['noted_delete_data_nonce'])), 'noted_delete_all_data')) {
                global $wpdb;
                $prefix = $wpdb->prefix;
                // Include pro-owned tables defensively so Delete All still clears them if present.
                $tables = ['noted_text_edits', 'noted_annotations', 'noted_comments', 'noted_pins', 'noted_guests', 'noted_pages'];
                foreach ($tables as $table) {
                    $wpdb->query("TRUNCATE TABLE {$prefix}{$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
                }
                echo '<div class="notice notice-warning"><p>' . esc_html__('All Noted data has been deleted.', 'noted-visual-feedback') . '</p></div>';
            }
        }

        $visibility       = get_option('noted_visibility', 'param');
        $brand_color      = get_option('noted_brand_color', '#E06042');
        $toolbar_position = get_option('noted_toolbar_position', 'top');
        $notif_settings   = get_option('noted_notification_settings', [
            'enabled'       => true,
            'on_new_pin'    => true,
            'on_reply'      => true,
            'on_resolved'   => false,
            'mode'          => 'digest',
            'digest_time'   => '09:00',
            'default_email' => get_option('admin_email'),
        ]);

        // Table stats — only free-plan tables.
        global $wpdb;
        $prefix      = $wpdb->prefix;
        $table_stats = [];
        $table_names = ['noted_pages', 'noted_pins', 'noted_comments', 'noted_guests'];
        foreach ($table_names as $table) {
            $table_stats[$table] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}{$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        }

        // Roles for permissions matrix.
        $all_roles = wp_roles()->roles;
        $caps      = [
            Noted_Capabilities::MANAGE_REVIEWS => __('Manage', 'noted-visual-feedback'),
            Noted_Capabilities::VIEW_REVIEWS   => __('View', 'noted-visual-feedback'),
            Noted_Capabilities::RESOLVE_PINS   => __('Resolve', 'noted-visual-feedback'),
            Noted_Capabilities::EXPORT_REVIEWS => __('Export', 'noted-visual-feedback'),
        ];

        include NOTED_PLUGIN_DIR . 'templates/admin-settings.php';
    }
}
