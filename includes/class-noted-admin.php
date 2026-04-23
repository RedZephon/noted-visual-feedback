<?php

defined('ABSPATH') || exit;

class Noted_Admin {

    public static function init() {
        add_action('admin_menu', [self::class, 'register_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
        add_action('admin_notices', [self::class, 'maybe_show_welcome']);
        add_filter('plugin_action_links_' . NOTED_PLUGIN_BASENAME, [self::class, 'plugin_action_links']);
        add_filter('plugin_row_meta', [self::class, 'plugin_row_meta'], 10, 2);

        // Integrate into native WP page/post list tables.
        foreach (['page', 'post'] as $type) {
            add_filter("manage_{$type}_posts_columns", [self::class, 'add_feedback_column']);
            add_action("manage_{$type}_posts_custom_column", [self::class, 'render_feedback_column'], 10, 2);
            add_filter("{$type}_row_actions", [self::class, 'add_row_action'], 10, 2);
        }
    }

    /**
     * Add Settings link to the plugin row on the Plugins page.
     */
    public static function plugin_action_links(array $links): array {
        $settings_url = admin_url('admin.php?page=noted-settings');
        array_unshift($links, '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'noted-visual-feedback') . '</a>');
        return $links;
    }

    /**
     * Add site link to plugin row meta (next to "By Mountain Thirteen Media").
     */
    public static function plugin_row_meta(array $meta, string $plugin_file): array {
        if ($plugin_file === NOTED_PLUGIN_BASENAME) {
            $meta[] = '<a href="https://wpnoted.com" target="_blank">' . esc_html__('Visit wpnoted.com', 'noted-visual-feedback') . '</a>';
        }
        return $meta;
    }

    /**
     * Add "Feedback" column to WP pages/posts list.
     */
    public static function add_feedback_column($columns) {
        $columns['noted_feedback'] = __('Feedback', 'noted-visual-feedback');
        return $columns;
    }

    /**
     * Render the feedback count in the custom column.
     */
    public static function render_feedback_column($column, $post_id) {
        if ($column !== 'noted_feedback') return;

        global $wpdb;
        $prefix = $wpdb->prefix;
        $path   = wp_make_link_relative(get_permalink($post_id));

        $page = $wpdb->get_row($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT id FROM {$prefix}noted_pages WHERE url_path = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $path
        ));

        if (!$page) {
            echo '<span style="color:#E3E0D9;">&mdash;</span>';
            return;
        }

        $open = (int) $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT COUNT(*) FROM {$prefix}noted_pins WHERE page_id = %d AND status = 'open'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $page->id
        ));
        $resolved = (int) $wpdb->get_var($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT COUNT(*) FROM {$prefix}noted_pins WHERE page_id = %d AND status = 'resolved'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $page->id
        ));

        $base_url = admin_url('admin.php?page=noted-pins');

        if ($open > 0) {
            $open_url = add_query_arg(['status' => 'open', 'page_path' => $path], $base_url);
            echo '<a href="' . esc_url($open_url) . '" style="color:#F5A623;font-weight:700;text-decoration:none;">' . esc_html($open) . ' ' . esc_html__('open', 'noted-visual-feedback') . '</a>';
        }
        if ($resolved > 0) {
            $resolved_url = add_query_arg(['status' => 'resolved', 'page_path' => $path], $base_url);
            echo ($open > 0 ? ' &middot; ' : '');
            echo '<a href="' . esc_url($resolved_url) . '" style="color:#3ecf7a;text-decoration:none;">' . esc_html($resolved) . ' ' . esc_html__('resolved', 'noted-visual-feedback') . '</a>';
        }
        if ($open === 0 && $resolved === 0) {
            echo '<span style="color:#E3E0D9;">&mdash;</span>';
        }
    }

    /**
     * Add "Open Noted Visual Feedback" link to page/post row actions.
     */
    public static function add_row_action($actions, $post) {
        if (!current_user_can(Noted_Capabilities::VIEW_REVIEWS)) return $actions;

        $url = add_query_arg('noted', '', get_permalink($post));
        $actions['noted_review'] = sprintf(
            '<a href="%s" target="_blank" style="color:#F5A623;font-weight:600;">%s</a>',
            esc_url($url),
            esc_html__('Open Noted Visual Feedback', 'noted-visual-feedback')
        );
        return $actions;
    }

    public static function maybe_show_welcome() {
        if (!get_option('noted_show_welcome')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || (strpos($screen->id, 'page_noted') === false && strpos($screen->id, '_noted') === false)) {
            return;
        }
        ?>
        <div class="notice notice-info is-dismissible" id="noted-welcome">
            <p>
                <strong><?php esc_html_e('Welcome to Noted Visual Feedback!', 'noted-visual-feedback'); ?></strong>
                <?php esc_html_e('Visit any page on your site and add ?noted to the URL to start reviewing.', 'noted-visual-feedback'); ?>
                <a href="<?php echo esc_url(add_query_arg('noted', '', home_url('/'))); ?>" target="_blank">
                    <?php esc_html_e('Start Reviewing', 'noted-visual-feedback'); ?> &rarr;
                </a>
            </p>
            <p style="margin-top:4px;">
                <?php esc_html_e('Need drawing annotations, text-edit suggestions, integrations, or white-label? Noted Pro adds all of that.', 'noted-visual-feedback'); ?>
                <a href="https://wpnoted.com/pricing/" target="_blank" rel="noopener"><?php esc_html_e('Learn more', 'noted-visual-feedback'); ?> &rarr;</a>
            </p>
        </div>
        <?php
        delete_option('noted_show_welcome');
    }

    public static function register_menu() {
        add_menu_page(
            __('Noted Visual Feedback', 'noted-visual-feedback'),
            __('Noted Visual Feedback', 'noted-visual-feedback'),
            Noted_Capabilities::VIEW_REVIEWS,
            'noted',
            [self::class, 'render_dashboard'],
            'dashicons-format-chat',
            30
        );

        add_submenu_page('noted', __('Dashboard', 'noted-visual-feedback'), __('Dashboard', 'noted-visual-feedback'),
            Noted_Capabilities::VIEW_REVIEWS, 'noted', [self::class, 'render_dashboard']);

        add_submenu_page('noted', __('Pages', 'noted-visual-feedback'), __('Pages', 'noted-visual-feedback'),
            Noted_Capabilities::VIEW_REVIEWS, 'noted-pages', [self::class, 'render_pages']);

        add_submenu_page('noted', __('Feedback', 'noted-visual-feedback'), __('Feedback', 'noted-visual-feedback'),
            Noted_Capabilities::VIEW_REVIEWS, 'noted-pins', ['Noted_Admin_Pins', 'render']);

        add_submenu_page('noted', __('Settings', 'noted-visual-feedback'), __('Settings', 'noted-visual-feedback'),
            Noted_Capabilities::MANAGE_REVIEWS, 'noted-settings', ['Noted_Admin_Settings', 'render']);

        // "Open Review Tool" external link in sidebar
        global $submenu;
        if (current_user_can(Noted_Capabilities::VIEW_REVIEWS)) {
            $submenu['noted'][] = [
                '<span style="color:#F5A623;font-weight:600;">' . esc_html__('Open Review Tool', 'noted-visual-feedback') . ' &#8599;</span>',
                Noted_Capabilities::VIEW_REVIEWS,
                home_url('/?noted'),
            ];
        }

        // "Noted Pro" upsell link.
        if (current_user_can(Noted_Capabilities::MANAGE_REVIEWS)) {
            $submenu['noted'][] = [
                '<span style="color:#D4920A;font-weight:600;">' . esc_html__('Noted Pro', 'noted-visual-feedback') . ' &#8599;</span>',
                Noted_Capabilities::MANAGE_REVIEWS,
                'https://wpnoted.com/pricing/',
            ];
        }

        /**
         * Extension hook: Pro plugin can add additional submenu pages
         * (Integrations, License, white-label settings, etc.).
         */
        do_action('noted_admin_menus_registered');
    }

    public static function enqueue_admin_assets($hook) {
        wp_enqueue_script(
            'noted-admin',
            NOTED_PLUGIN_URL . 'assets/js/noted-admin.js',
            [],
            NOTED_VERSION,
            true
        );
        wp_localize_script('noted-admin', 'NotedAdmin', [
            'walkthroughUrl' => esc_url_raw(rest_url('noted/v1/walkthrough')),
            'nonce'          => wp_create_nonce('wp_rest'),
            'i18n'           => [
                'resetting' => __('Resetting...', 'noted-visual-feedback'),
                'done'      => __('Done, walkthrough will appear on next launch', 'noted-visual-feedback'),
                'error'     => __('Error, please try again', 'noted-visual-feedback'),
                'reset'     => __('Reset walkthrough', 'noted-visual-feedback'),
            ],
        ]);

        // Match "toplevel_page_noted" and "noted_page_*" submenu hooks.
        if (strpos($hook, 'page_noted') === false && strpos($hook, '_noted') === false) {
            return;
        }
        wp_enqueue_style('noted-admin', NOTED_PLUGIN_URL . 'assets/css/noted-admin.css', [], NOTED_VERSION);
    }

    public static function render_dashboard() {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $stats = [
            'open_pins'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}noted_pins WHERE status = 'open'"), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'resolved_pins'  => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}noted_pins WHERE status = 'resolved'"), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'pages_reviewed' => (int) $wpdb->get_var("SELECT COUNT(DISTINCT page_id) FROM {$prefix}noted_pins"), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'pins_this_week' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}noted_pins WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        ];

        // Activity feed: last 20 events (pins + comments).
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $activities = $wpdb->get_results("
            (SELECT 'pin' AS type, p.id, p.body, p.pin_number, p.status AS pin_status, p.author_type,
                    p.author_user_id, p.author_guest_id, p.created_at,
                    pg.url_path AS page_path
             FROM {$prefix}noted_pins p
             LEFT JOIN {$prefix}noted_pages pg ON p.page_id = pg.id
             ORDER BY p.created_at DESC LIMIT 20)
            UNION ALL
            (SELECT 'comment' AS type, c.id, c.body, NULL AS pin_number, NULL AS pin_status, c.author_type,
                    c.author_user_id, c.author_guest_id, c.created_at,
                    pg.url_path AS page_path
             FROM {$prefix}noted_comments c
             LEFT JOIN {$prefix}noted_pins p ON c.pin_id = p.id
             LEFT JOIN {$prefix}noted_pages pg ON p.page_id = pg.id
             ORDER BY c.created_at DESC LIMIT 20)
            ORDER BY created_at DESC LIMIT 20
        ");
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // Resolve author names.
        foreach ($activities as &$act) {
            if ($act->author_type === 'user' && $act->author_user_id) {
                $u = get_userdata($act->author_user_id);
                $act->author_name = $u ? $u->display_name : __('Unknown', 'noted-visual-feedback');
            } else {
                $g = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$prefix}noted_guests WHERE id = %d", $act->author_guest_id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $act->author_name = $g ? $g->name : __('Guest', 'noted-visual-feedback');
            }
        }
        unset($act);

        include NOTED_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }

    public static function render_pages() {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // Get all reviewed pages with feedback counts.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $reviewed = $wpdb->get_results("
            SELECT pg.*,
                   (SELECT COUNT(*) FROM {$prefix}noted_pins WHERE page_id = pg.id) AS pin_count,
                   (SELECT COUNT(*) FROM {$prefix}noted_pins WHERE page_id = pg.id AND status = 'open') AS open_count,
                   (SELECT COUNT(*) FROM {$prefix}noted_pins WHERE page_id = pg.id AND status = 'resolved') AS resolved_count
            FROM {$prefix}noted_pages pg
            ORDER BY pg.url_path ASC
        ");
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        $reviewed_map = [];
        foreach ($reviewed as $r) {
            $reviewed_map[$r->url_path] = $r;
        }

        $wp_pages = get_pages(['post_status' => ['publish', 'draft', 'pending', 'private']]);
        $wp_posts = get_posts(['post_type' => 'post', 'post_status' => ['publish', 'draft'], 'numberposts' => -1]);

        $pages = [];

        $home_reviewed = $reviewed_map['/'] ?? null;
        $pages[] = (object) [
            'wp_id'           => 0,
            'title'           => __('Homepage', 'noted-visual-feedback'),
            'url_path'        => '/',
            'post_status'     => 'publish',
            'post_type'       => 'homepage',
            'reviewed'        => (bool) $home_reviewed,
            'pin_count'       => $home_reviewed->pin_count ?? 0,
            'open_count'      => $home_reviewed->open_count ?? 0,
            'resolved_count'  => $home_reviewed->resolved_count ?? 0,
        ];

        foreach ($wp_pages as $wp_page) {
            $path = wp_make_link_relative(get_permalink($wp_page));
            $r = $reviewed_map[$path] ?? null;
            $pages[] = (object) [
                'wp_id'           => $wp_page->ID,
                'title'           => $wp_page->post_title ?: $path,
                'url_path'        => $path,
                'post_status'     => $wp_page->post_status,
                'post_type'       => 'page',
                'reviewed'        => (bool) $r,
                'pin_count'       => $r->pin_count ?? 0,
                'open_count'      => $r->open_count ?? 0,
                'resolved_count'  => $r->resolved_count ?? 0,
            ];
            unset($reviewed_map[$path]);
        }

        foreach ($wp_posts as $wp_post) {
            $path = wp_make_link_relative(get_permalink($wp_post));
            $r = $reviewed_map[$path] ?? null;
            $pages[] = (object) [
                'wp_id'           => $wp_post->ID,
                'title'           => $wp_post->post_title ?: $path,
                'url_path'        => $path,
                'post_status'     => $wp_post->post_status,
                'post_type'       => 'post',
                'reviewed'        => (bool) $r,
                'pin_count'       => $r->pin_count ?? 0,
                'open_count'      => $r->open_count ?? 0,
                'resolved_count'  => $r->resolved_count ?? 0,
            ];
            unset($reviewed_map[$path]);
        }

        foreach ($reviewed_map as $path => $r) {
            if ($path === '/') continue;
            $pages[] = (object) [
                'wp_id'           => 0,
                'title'           => $r->title ?: $path,
                'url_path'        => $path,
                'post_status'     => 'publish',
                'post_type'       => 'custom',
                'reviewed'        => true,
                'pin_count'       => $r->pin_count ?? 0,
                'open_count'      => $r->open_count ?? 0,
                'resolved_count'  => $r->resolved_count ?? 0,
            ];
        }

        include NOTED_PLUGIN_DIR . 'templates/admin-pages.php';
    }
}
