<?php

defined('WP_UNINSTALL_PLUGIN') || exit;

if (is_multisite()) {
    $noted_sites = get_sites(['fields' => 'ids']);
    foreach ($noted_sites as $noted_site_id) {
        switch_to_blog($noted_site_id);
        noted_uninstall_site();
        restore_current_blog();
    }
} else {
    noted_uninstall_site();
}

function noted_uninstall_site() {
    global $wpdb;
    $prefix = $wpdb->prefix;

    // Drop all custom tables (order matters — FK constraints).
    $tables = [
        'noted_text_edits',
        'noted_annotations',
        'noted_comments',
        'noted_pins',
        'noted_guests',
        'noted_pages',
    ];

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}{$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
    }

    // Delete all options.
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'noted\_%'"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

    // Delete all transients.
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_noted\_%'"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_noted\_%'"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

    // Remove capabilities.
    foreach (['administrator', 'editor'] as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->remove_cap('noted_manage_reviews');
            $role->remove_cap('noted_view_reviews');
            $role->remove_cap('noted_resolve_pins');
            $role->remove_cap('noted_export_reviews');
        }
    }
}
