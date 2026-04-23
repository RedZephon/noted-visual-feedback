<?php
/**
 * Plugin Name: Noted Visual Feedback
 * Plugin URI: https://wpnoted.com
 * Description: Collect visual feedback with pinned comments directly on your live WordPress site. All data stored locally in your WordPress database.
 * Version: 1.0.5
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Mountain Thirteen Media
 * Author URI: https://mountainthirteen.com
 * License: GPL-2.0-or-later
 * Text Domain: noted-visual-feedback
 * Domain Path: /languages
 */

/**
 * Extension hooks (for the companion Pro plugin).
 *
 * Actions:
 *   do_action( 'noted_register_rest_routes', string $namespace )
 *     Fired after all core REST routes are registered. The Pro plugin can
 *     hang additional routes (annotations, text-edits, exports, etc.) off
 *     the same namespace ('noted/v1').
 *
 *   do_action( 'noted_admin_menus_registered' )
 *     Fired after the core admin menu + submenus are registered. The Pro
 *     plugin can add additional submenu pages (Integrations, License,
 *     white-label settings, etc.).
 *
 *   do_action( 'noted_pin_created', int $pin_id, array $pin_data )
 *     Existing notification action — unchanged, documented here for completeness.
 *
 *   do_action( 'noted_comment_created', int $comment_id, array $comment_data )
 *   do_action( 'noted_pin_resolved', int $pin_id, array $pin_data )
 *
 * Filters:
 *   apply_filters( 'noted_pin_query_args', array $args, WP_REST_Request $request ) : array
 *     Fired in list_pins() before running the query. Lets the Pro plugin add
 *     filter clauses for additional pin columns.
 *     $args keys: 'where' (array of SQL fragments), 'values' (array of
 *     prepared-statement values).
 *
 *   apply_filters( 'noted_script_config', array $config ) : array
 *     Fired in the frontend script loader before wp_localize_script. Lets
 *     the Pro plugin add / override config fields (feature flags,
 *     white-label strings, license info, pin caps, etc.).
 *
 *   apply_filters( 'noted_show_powered_by', bool $show ) : bool
 *     Whether to show the "Powered by Noted" branding. Default: true in
 *     Phase 2b; Phase 4 adds the admin opt-in. Pro plugin can filter to
 *     false when white-label is enabled.
 *
 *   apply_filters( 'noted_overlay_config', array $config ) : array
 *     Existing filter — unchanged, documented here. Same semantics as
 *     'noted_script_config' (kept for backwards compatibility).
 *
 *   apply_filters( 'noted_force_overlay', bool $force ) : bool
 *     Force-load the frontend overlay regardless of visibility settings.
 *
 *   apply_filters( 'noted_bypass_guest_auth', bool $bypass ) : bool
 *     Allow a companion plugin (e.g. demo) to bypass guest-token checks.
 *
 *   apply_filters( 'noted_demo_guest_override', ?array $guest, WP_REST_Request $request ) : ?array
 *     Allow a companion plugin to provide a pre-registered guest identity
 *     for /guests/register without persisting to the DB.
 */

defined('ABSPATH') || exit;

define('NOTED_VERSION', '1.0.5');
define('NOTED_DB_VERSION', '1.0.0');
define('NOTED_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NOTED_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NOTED_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('NOTED_PLUGIN_FILE', __FILE__);
define('NOTED_PLUGIN_SLUG', 'noted-visual-feedback');

// Load all class files
foreach (glob(NOTED_PLUGIN_DIR . 'includes/class-noted-*.php') as $noted_file) {
    require_once $noted_file;
}

// Activation / Deactivation
register_activation_hook(__FILE__, ['Noted_Activator', 'activate']);
register_deactivation_hook(__FILE__, ['Noted_Activator', 'deactivate']);

// Multisite: create tables on new site.
add_action('wp_initialize_site', ['Noted_Activator', 'on_new_site'], 10, 1);

// Initialize after all plugins loaded
add_action('plugins_loaded', function () {
    Noted_Activator::maybe_update_db();
    Noted_Capabilities::init();
    Noted_Admin::init();
    Noted_REST_API::init();
    Noted_Script_Loader::init();
    Noted_Notifications::init();
    Noted_Cron::init();
    Noted_Walkthrough::init();
});
