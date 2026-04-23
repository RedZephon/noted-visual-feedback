<?php

defined('ABSPATH') || exit;

class Noted_Script_Loader {

    public static function init() {
        add_action('wp_enqueue_scripts', [self::class, 'maybe_inject'], 999);
        add_action('admin_bar_menu', [self::class, 'add_admin_bar_link'], 999);
    }

    /**
     * Add "Review" link to the WP admin bar on the frontend.
     */
    public static function add_admin_bar_link($wp_admin_bar) {
        if (!current_user_can(Noted_Capabilities::VIEW_REVIEWS)) {
            return;
        }

        if (is_admin()) {
            $review_url = home_url('/?noted');
        } else {
            $review_url = home_url(add_query_arg('noted', '', remove_query_arg('noted')));
        }

        $wp_admin_bar->add_node([
            'id'    => 'noted-review',
            'title' => '<span class="ab-icon dashicons dashicons-format-chat"></span> ' . esc_html__('Noted Visual Feedback', 'noted-visual-feedback'),
            'href'  => $review_url,
            'meta'  => [
                'title'  => __('Open Noted Visual Feedback review overlay', 'noted-visual-feedback'),
                'target' => is_admin() ? '_blank' : '_self',
            ],
        ]);
    }

    public static function maybe_inject() {
        if (is_admin()) {
            return;
        }

        // Allow companion plugins to force-load the overlay (e.g., demo mode).
        $force_overlay = apply_filters( 'noted_force_overlay', false );

        // Activation via URL param: ?noted
        $has_param      = isset($_GET['noted']); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $param_value    = sanitize_text_field(wp_unslash($_GET['noted'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $is_guest_token = $has_param && !empty($param_value) && $param_value !== '1';

        $visibility  = get_option('noted_visibility', 'param');
        $should_load = false;

        switch ($visibility) {
            case 'param':
                if ($has_param && !$is_guest_token) {
                    $should_load = current_user_can(Noted_Capabilities::VIEW_REVIEWS);
                }
                if ($is_guest_token) {
                    $should_load = true;
                }
                break;

            case 'always':
                $should_load = current_user_can(Noted_Capabilities::VIEW_REVIEWS);
                if ($is_guest_token) {
                    $should_load = true;
                }
                break;

            case 'off':
            default:
                if ($is_guest_token) {
                    $should_load = true;
                } elseif ( ! $force_overlay ) {
                    return;
                }
        }

        if ( $force_overlay ) {
            $should_load = true;
        }

        if (!$should_load) {
            return;
        }

        // Detect current page path.
        $current_path = wp_parse_url(get_permalink(), PHP_URL_PATH) ?: '/';
        if (is_front_page()) {
            $current_path = '/';
        }

        // Load the full overlay.
        wp_enqueue_script(
            'noted-overlay',
            NOTED_PLUGIN_URL . 'assets/js/noted-overlay.min.js',
            [],
            NOTED_VERSION,
            ['in_footer' => true, 'strategy' => 'defer']
        );

        /**
         * Whether to show the "Powered by Noted" branding in the overlay.
         * Hidden by default; site owner can opt in via Settings > General.
         */
        $show_powered_by = apply_filters(
            'noted_show_powered_by',
            (bool) get_option('noted_show_branding', false)
        );

        $strings = [
            'pinHint'           => __('Click anywhere on the page to place a comment', 'noted-visual-feedback'),
            'elementHint'       => __('Hover to highlight elements, click to select for feedback', 'noted-visual-feedback'),
            'noFeedback'        => __('No feedback yet', 'noted-visual-feedback'),
            'noFeedbackDesc'    => __('Click the pin tool and click anywhere on the page to leave feedback.', 'noted-visual-feedback'),
            'reply'             => __('Reply...', 'noted-visual-feedback'),
            'leaveFeedback'     => __('Leave your feedback...', 'noted-visual-feedback'),
            'submit'            => __('Submit', 'noted-visual-feedback'),
            'cancel'            => __('Cancel', 'noted-visual-feedback'),
            'resolve'           => __('Resolve', 'noted-visual-feedback'),
            'reopen'            => __('Reopen', 'noted-visual-feedback'),
            'delete'            => __('Delete', 'noted-visual-feedback'),
            'annotate'          => __('Annotate', 'noted-visual-feedback'),
            'view'              => __('View', 'noted-visual-feedback'),
            'desktop'           => __('Desktop', 'noted-visual-feedback'),
            'tablet'            => __('Tablet', 'noted-visual-feedback'),
            'mobile'            => __('Mobile', 'noted-visual-feedback'),
            'comments'          => __('Comments', 'noted-visual-feedback'),
            'all'               => __('All', 'noted-visual-feedback'),
            'open'              => __('Open', 'noted-visual-feedback'),
            'done'              => __('Done', 'noted-visual-feedback'),
            'shareReviewLink'   => __('Share', 'noted-visual-feedback'),
            'generateLink'      => __('Generate Link', 'noted-visual-feedback'),
            'copyLink'          => __('Copy', 'noted-visual-feedback'),
            'copied'            => __('Copied!', 'noted-visual-feedback'),
            /* translators: %d: number of open pins */
            'openCount'         => __('%d open', 'noted-visual-feedback'),
            /* translators: %d: number of resolved pins */
            'resolvedCount'     => __('%d resolved', 'noted-visual-feedback'),
            /* translators: %s: element or page name */
            'commentOn'         => __('Comment on %s', 'noted-visual-feedback'),
            'welcomeTitle'      => __('Welcome to Review', 'noted-visual-feedback'),
            'welcomeSubtitle'   => __('You\'ve been invited to leave feedback on this site.', 'noted-visual-feedback'),
            'yourName'          => __('Your name', 'noted-visual-feedback'),
            'yourEmail'         => __('Your email (optional)', 'noted-visual-feedback'),
            'continue'          => __('Continue', 'noted-visual-feedback'),
            'connecting'        => __('Connecting...', 'noted-visual-feedback'),
            'poweredByNoted'    => $show_powered_by ? __('Powered by Noted', 'noted-visual-feedback') : '',
            'linkInvalidTitle'  => __('Invalid Review Link', 'noted-visual-feedback'),
            'linkInvalid'       => __('This review link is not valid. It may have been revoked or the project was archived.', 'noted-visual-feedback'),
            'saving'            => __('Saving...', 'noted-visual-feedback'),
            'pending'           => __('Pending', 'noted-visual-feedback'),
            'viewMode'          => __('View mode — switch to Annotate to leave feedback', 'noted-visual-feedback'),
            'select'            => __('Select', 'noted-visual-feedback'),
            'exportTo'          => __('Export', 'noted-visual-feedback'),
            'discuss'           => __('Discuss', 'noted-visual-feedback'),
            'keyboardShortcuts' => __('Keyboard Shortcuts', 'noted-visual-feedback'),
            'tools'             => __('Tools', 'noted-visual-feedback'),
            'navigation'        => __('Navigation', 'noted-visual-feedback'),
            'general'           => __('General', 'noted-visual-feedback'),
            'pinTool'           => __('Pin (comment) tool', 'noted-visual-feedback'),
            'elementTool'       => __('Element selector tool', 'noted-visual-feedback'),
            'cursorTool'        => __('Cursor tool', 'noted-visual-feedback'),
            'nextPin'           => __('Next pin', 'noted-visual-feedback'),
            'previousPin'       => __('Previous pin', 'noted-visual-feedback'),
            'nextPage'          => __('Next page', 'noted-visual-feedback'),
            'previousPage'      => __('Previous page', 'noted-visual-feedback'),
            'togglePanel'       => __('Toggle comments panel', 'noted-visual-feedback'),
            'toggleMode'        => __('Toggle Annotate / View', 'noted-visual-feedback'),
            'cancelClose'       => __('Cancel / Close', 'noted-visual-feedback'),
            'showHelp'          => __('Show this help', 'noted-visual-feedback'),
            'pressAnyKey'       => __('Press any key to close', 'noted-visual-feedback'),
        ];

        $config = [
            'restUrl'       => esc_url_raw(rest_url('noted/v1/')),
            'restNonce'     => wp_create_nonce('wp_rest'),
            // Zero placeholder for backwards compat with the compiled bundle.
            'projectId'     => 0,
            'pagePath'      => $current_path,
            'pageTitle'     => wp_get_document_title(),
            'position'      => get_option('noted_toolbar_position', 'top'),
            'brandColor'    => get_option('noted_brand_color', '#F5A623'),
            'guestToken'    => $is_guest_token ? $param_value : '',
            'currentUser'   => null,
            'canResolve'    => false,
            'canExport'     => false,
            'isInternal'    => false,
            'walkthroughCompleted' => is_user_logged_in()
                ? (bool) get_user_meta(get_current_user_id(), Noted_Walkthrough::META_KEY, true)
                : true,
            'strings'       => $strings,
        ];

        if (is_user_logged_in()) {
            $user                  = wp_get_current_user();
            $config['currentUser'] = [
                'id'     => $user->ID,
                'name'   => $user->display_name,
                'email'  => $user->user_email,
                'avatar' => get_avatar_url($user->ID, ['size' => 64]),
            ];
            $config['canResolve']  = current_user_can(Noted_Capabilities::RESOLVE_PINS);
            $config['canExport']   = current_user_can(Noted_Capabilities::EXPORT_REVIEWS);
            $config['isInternal']  = current_user_can(Noted_Capabilities::MANAGE_REVIEWS);
        }

        /**
         * Extension hook: Pro plugin can add/override config fields
         * (feature flags, white-label, license info, caps, etc.).
         */
        $config = apply_filters('noted_script_config', $config);

        // Backwards-compatible alias (existing hook).
        $config = apply_filters('noted_overlay_config', $config);

        wp_localize_script('noted-overlay', 'NotedConfig', $config);
    }
}
