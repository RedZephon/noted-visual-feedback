<?php

defined('ABSPATH') || exit;

class Noted_REST_API {

    public static function init() {
        add_action('rest_api_init', [self::class, 'register']);
    }

    public static function register() {
        $ns = 'noted/v1';

        // Pages
        register_rest_route($ns, '/pages/ensure', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'ensure_page'],
            'permission_callback' => [self::class, 'can_view_or_guest'],
            'args'                => [
                'url_path' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Pins
        register_rest_route($ns, '/pins', [
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'list_pins'],
                'permission_callback' => [self::class, 'can_view_or_guest'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'create_pin'],
                'permission_callback' => [self::class, 'can_view_or_guest'],
            ],
        ]);

        register_rest_route($ns, '/pins/(?P<id>\d+)', [
            [
                'methods'             => 'PATCH',
                'callback'            => [self::class, 'update_pin'],
                'permission_callback' => [self::class, 'can_resolve_or_author'],
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => [self::class, 'delete_pin'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        // CSV download
        register_rest_route($ns, '/pins/csv', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'download_pins_csv'],
            'permission_callback' => [self::class, 'can_export'],
        ]);

        // Comments
        register_rest_route($ns, '/pins/(?P<pin_id>\d+)/comments', [
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'list_comments'],
                'permission_callback' => [self::class, 'can_view_or_guest'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'create_comment'],
                'permission_callback' => [self::class, 'can_view_or_guest'],
            ],
        ]);

        // Guests
        register_rest_route($ns, '/guests/register', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'register_guest'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/guests/validate', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'validate_guest'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/guests/check-access', [
            'methods'             => 'POST',
            'callback'            => [self::class, 'check_guest_access'],
            'permission_callback' => '__return_true',
        ]);

        // Presence
        register_rest_route($ns, '/presence', [
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'heartbeat'],
                'permission_callback' => [self::class, 'can_view_or_guest'],
            ],
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'get_presence'],
                'permission_callback' => [self::class, 'can_view_or_guest'],
            ],
        ]);

        // Share (page-level share link management).
        register_rest_route($ns, '/pages/(?P<id>\d+)/share', [
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'get_page_share'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods'             => 'PATCH',
                'callback'            => [self::class, 'update_page_share'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'generate_page_share_link'],
                'permission_callback' => [self::class, 'can_manage'],
            ],
        ]);

        // Sitemap
        register_rest_route($ns, '/sitemap', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_sitemap'],
            'permission_callback' => [self::class, 'can_manage'],
        ]);

        // Stats
        register_rest_route($ns, '/stats', [
            'methods'             => 'GET',
            'callback'            => [self::class, 'get_stats'],
            'permission_callback' => [self::class, 'can_view'],
        ]);

        /**
         * Extension hook: lets the Pro plugin register additional REST routes
         * on the same namespace (annotations, text-edits, exports, etc.).
         */
        do_action('noted_register_rest_routes', $ns);
    }

    // ── Permission callbacks ──────────────────────────────

    public static function can_manage() {
        return current_user_can(Noted_Capabilities::MANAGE_REVIEWS);
    }

    public static function can_view() {
        return current_user_can(Noted_Capabilities::VIEW_REVIEWS);
    }

    public static function can_resolve() {
        return current_user_can(Noted_Capabilities::RESOLVE_PINS);
    }

    public static function can_export() {
        return current_user_can(Noted_Capabilities::EXPORT_REVIEWS);
    }

    public static function can_view_or_guest($request) {
        // Allow companion plugins to bypass guest token validation.
        if ( apply_filters( 'noted_bypass_guest_auth', false ) ) {
            return true;
        }

        if (current_user_can(Noted_Capabilities::VIEW_REVIEWS)) {
            return true;
        }

        $token = $request->get_header('X-Noted-Guest-Token');
        if (!$token) {
            $token = $request->get_param('guest_token');
        }
        if (!$token) {
            return false;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $guest = $wpdb->get_row($wpdb->prepare(
            "SELECT id, page_id FROM {$wpdb->prefix}noted_guests WHERE token = %s",
            $token
        ));

        if (!$guest) {
            return false;
        }

        $request->set_param('_noted_guest_id', (int) $guest->id);
        $request->set_param('_noted_guest_page_id', (int) $guest->page_id);

        return true;
    }

    public static function can_resolve_or_author($request) {
        if (current_user_can(Noted_Capabilities::RESOLVE_PINS)) {
            return true;
        }
        return self::is_pin_author($request);
    }

    private static function is_pin_author($request) {
        $pin_id = absint($request->get_param('id'));
        if (!$pin_id) {
            return false;
        }

        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $pin = $wpdb->get_row($wpdb->prepare(
            "SELECT author_type, author_user_id, author_guest_id FROM {$wpdb->prefix}noted_pins WHERE id = %d",
            $pin_id
        ));

        if (!$pin) {
            return false;
        }

        if ($pin->author_type === 'user' && is_user_logged_in()) {
            return (int) $pin->author_user_id === get_current_user_id();
        }

        $guest_token = $request->get_header('X-Noted-Guest-Token') ?? $request->get_param('guest_token');
        if ($guest_token && $pin->author_type === 'guest') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $guest = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}noted_guests WHERE token = %s",
                $guest_token
            ));
            return $guest && (int) $guest->id === (int) $pin->author_guest_id;
        }

        return false;
    }

    /**
     * Return true if the request has authenticated-user access OR is a
     * guest whose authorized page_id matches the given $page_id.
     */
    private static function guest_can_access_page($request, $page_id) {
        if (current_user_can(Noted_Capabilities::VIEW_REVIEWS)) {
            return true;
        }
        $guest_page_id = (int) $request->get_param('_noted_guest_page_id');
        return $guest_page_id > 0 && $guest_page_id === (int) $page_id;
    }

    /**
     * Fetch a pin's page_id, or 0 if it doesn't exist.
     */
    private static function get_pin_page_id($pin_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT page_id FROM {$wpdb->prefix}noted_pins WHERE id = %d",
            (int) $pin_id
        )); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    // ── Share ─────────────────────────────────────────────

    public static function get_page_share($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $id     = absint($request->get_param('id'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $page = $wpdb->get_row($wpdb->prepare(
            "SELECT id, url_path, title, access_token FROM {$prefix}noted_pages WHERE id = %d",
            $id
        ));

        if (!$page) {
            return new WP_Error('not_found', __('Page not found.', 'noted-visual-feedback'), ['status' => 404]);
        }

        $share_url = $page->access_token
            ? add_query_arg('noted', $page->access_token, home_url($page->url_path))
            : '';

        return new WP_REST_Response([
            'id'           => (int) $page->id,
            'url_path'     => $page->url_path,
            'title'        => $page->title,
            'access_token' => $page->access_token,
            'share_url'    => $share_url,
        ], 200);
    }

    public static function update_page_share($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $id     = absint($request->get_param('id'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$prefix}noted_pages WHERE id = %d",
            $id
        ));

        if (!$existing) {
            return new WP_Error('not_found', __('Page not found.', 'noted-visual-feedback'), ['status' => 404]);
        }

        $data = [];

        if ($request->has_param('access_token')) {
            $token_val = $request->get_param('access_token');
            $data['access_token'] = empty($token_val) ? null : sanitize_text_field($token_val);
        }

        if (!empty($data)) {
            $wpdb->update("{$prefix}noted_pages", $data, ['id' => $id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        return self::get_page_share($request);
    }

    public static function generate_page_share_link($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $id     = absint($request->get_param('id'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $page = $wpdb->get_row($wpdb->prepare(
            "SELECT id, url_path FROM {$prefix}noted_pages WHERE id = %d",
            $id
        ));

        if (!$page) {
            return new WP_Error('not_found', __('Page not found.', 'noted-visual-feedback'), ['status' => 404]);
        }

        $token = bin2hex(random_bytes(32));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->update(
            "{$prefix}noted_pages",
            ['access_token' => $token],
            ['id' => $id]
        );

        $share_url = add_query_arg('noted', $token, home_url($page->url_path));

        return new WP_REST_Response([
            'token'     => $token,
            'share_url' => $share_url,
        ], 200);
    }

    // ── Pages ─────────────────────────────────────────────

    public static function ensure_page($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $url_path = sanitize_text_field($request->get_param('url_path'));
        $title    = sanitize_text_field($request->get_param('title') ?? '');

        // Guest scope check: a guest token is bound to one page. The url_path
        // they submit must match the page they already have access to; they
        // cannot create new pages or access other pages.
        $guest_page_id = (int) $request->get_param('_noted_guest_page_id');
        if ($guest_page_id > 0 && !current_user_can(Noted_Capabilities::VIEW_REVIEWS)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $guest_page = $wpdb->get_row($wpdb->prepare(
                "SELECT url_path FROM {$prefix}noted_pages WHERE id = %d",
                $guest_page_id
            ));
            if (!$guest_page || $guest_page->url_path !== $url_path) {
                return new WP_Error('forbidden', __('You do not have access to this resource.', 'noted-visual-feedback'), ['status' => 403]);
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}noted_pages WHERE url_path = %s",
            $url_path
        ));

        if ($existing) {
            return new WP_REST_Response($existing, 200);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->insert("{$prefix}noted_pages", [
            'url_path'   => $url_path,
            'title'      => $title,
            'sort_order' => 0,
        ]);

        if ($wpdb->insert_id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $page = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}noted_pages WHERE id = %d",
                $wpdb->insert_id
            ));
            return new WP_REST_Response($page, 201);
        }

        return new WP_Error('insert_failed', __('Could not create page.', 'noted-visual-feedback'), ['status' => 500]);
    }

    // ── Pins ──────────────────────────────────────────────

    public static function list_pins($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $where  = [];
        $values = [];

        // Guest scope: force page_id to the guest's authorized page,
        // overriding any client-supplied page_id.
        $guest_page_id = (int) $request->get_param('_noted_guest_page_id');
        if ($guest_page_id > 0 && !current_user_can(Noted_Capabilities::VIEW_REVIEWS)) {
            $where[]  = 'page_id = %d';
            $values[] = $guest_page_id;
        } else {
            $page_id = $request->get_param('page_id');
            if ($page_id) {
                $where[]  = 'page_id = %d';
                $values[] = absint($page_id);
            }
        }

        $status = $request->get_param('status');
        if ($status) {
            $where[]  = 'status = %s';
            $values[] = sanitize_text_field($status);
        }

        $breakpoint = $request->get_param('breakpoint');
        if ($breakpoint) {
            $where[]  = 'breakpoint = %s';
            $values[] = sanitize_text_field($breakpoint);
        }

        /**
         * Extension hook: allow Pro plugin to add filter clauses before
         * running the pin query.
         *
         * @param array           $args    ['where' => string[], 'values' => mixed[]]
         * @param WP_REST_Request $request
         */
        $args = apply_filters('noted_pin_query_args', ['where' => $where, 'values' => $values], $request);
        $where  = $args['where']  ?? $where;
        $values = $args['values'] ?? $values;

        $sql = "SELECT * FROM {$prefix}noted_pins";
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY pin_number ASC';

        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, ...$values); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        $pins = $wpdb->get_results($sql); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        // Resolve author names.
        foreach ($pins as &$pin) {
            if ($pin->author_type === 'user' && $pin->author_user_id) {
                $user = get_userdata($pin->author_user_id);
                $pin->author_name   = $user ? $user->display_name : '';
                $pin->author_avatar = $user ? get_avatar_url($user->ID, ['size' => 64]) : '';
            } elseif ($pin->author_type === 'guest' && $pin->author_guest_id) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $guest = $wpdb->get_row($wpdb->prepare(
                    "SELECT name, avatar_hash FROM {$prefix}noted_guests WHERE id = %d",
                    $pin->author_guest_id
                ));
                $pin->author_name   = $guest ? $guest->name : '';
                $pin->author_avatar = '';
            } else {
                $pin->author_name   = '';
                $pin->author_avatar = '';
            }
        }
        unset($pin);

        return new WP_REST_Response($pins, 200);
    }

    public static function create_pin($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $page_id = absint($request->get_param('page_id'));

        // Guest scope check: a guest can only create pins on their own page.
        if (!self::guest_can_access_page($request, $page_id)) {
            return new WP_Error('forbidden', __('You do not have access to this resource.', 'noted-visual-feedback'), ['status' => 403]);
        }

        // Verify page exists.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $page = $wpdb->get_row($wpdb->prepare(
            "SELECT id, url_path FROM {$prefix}noted_pages WHERE id = %d",
            $page_id
        ));
        if (!$page) {
            return new WP_Error('invalid_page', __('Page not found.', 'noted-visual-feedback'), ['status' => 400]);
        }

        // Auto-increment pin_number per page.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $max_number = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(MAX(pin_number), 0) FROM {$prefix}noted_pins WHERE page_id = %d",
            $page_id
        ));
        $pin_number = $max_number + 1;

        // Determine author.
        $guest_id = $request->get_param('_noted_guest_id');
        if (!empty($guest_id)) {
            $author_type     = 'guest';
            $author_user_id  = null;
            $author_guest_id = absint($guest_id);
        } else {
            $author_type     = 'user';
            $author_user_id  = get_current_user_id();
            $author_guest_id = null;
        }

        $data = [
            'page_id'          => $page_id,
            'x_percent'        => (float) $request->get_param('x_percent'),
            'y_percent'        => (float) $request->get_param('y_percent'),
            'css_selector'     => sanitize_text_field($request->get_param('css_selector') ?? ''),
            'selector_offset_x'=> $request->get_param('selector_offset_x') !== null ? (float) $request->get_param('selector_offset_x') : null,
            'selector_offset_y'=> $request->get_param('selector_offset_y') !== null ? (float) $request->get_param('selector_offset_y') : null,
            'viewport_width'   => $request->get_param('viewport_width') !== null ? absint($request->get_param('viewport_width')) : null,
            'scroll_y'         => $request->get_param('scroll_y') !== null ? (float) $request->get_param('scroll_y') : null,
            'breakpoint'       => sanitize_text_field($request->get_param('breakpoint') ?? 'desktop'),
            'pin_number'       => $pin_number,
            'status'           => 'open',
            'author_type'      => $author_type,
            'author_user_id'   => $author_user_id,
            'author_guest_id'  => $author_guest_id,
            'body'             => wp_kses_post($request->get_param('body') ?? ''),
            'attachment_ids'   => sanitize_text_field($request->get_param('attachment_ids') ?? ''),
        ];

        $wpdb->insert("{$prefix}noted_pins", $data); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if (!$wpdb->insert_id) {
            return new WP_Error('insert_failed', __('Could not create pin.', 'noted-visual-feedback'), ['status' => 500]);
        }

        $pin_id = $wpdb->insert_id;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $pin    = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}noted_pins WHERE id = %d",
            $pin_id
        ));

        // Resolve author name for notification.
        $author_name = '';
        if ($author_type === 'user') {
            $user        = get_userdata($author_user_id);
            $author_name = $user ? $user->display_name : '';
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $guest       = $wpdb->get_row($wpdb->prepare(
                "SELECT name FROM {$prefix}noted_guests WHERE id = %d",
                $author_guest_id
            ));
            $author_name = $guest ? $guest->name : '';
        }

        do_action('noted_pin_created', $pin_id, array_merge($data, [
            'pin_number'  => $pin_number,
            'page_path'   => $page->url_path,
            'author_name' => $author_name,
        ]));

        return new WP_REST_Response($pin, 201);
    }

    public static function update_pin($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $id     = absint($request->get_param('id'));

        // Defence-in-depth guest scope check: guest pin authors can only
        // update pins on their authorized page.
        if (!self::guest_can_access_page($request, self::get_pin_page_id($id))) {
            return new WP_Error('forbidden', __('You do not have access to this resource.', 'noted-visual-feedback'), ['status' => 403]);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}noted_pins WHERE id = %d",
            $id
        ));

        if (!$existing) {
            return new WP_Error('not_found', __('Pin not found.', 'noted-visual-feedback'), ['status' => 404]);
        }

        $data = [];

        $status = $request->get_param('status');
        if ($status !== null) {
            $data['status'] = sanitize_text_field($status);
            if ($status === 'resolved') {
                $data['resolved_at'] = current_time('mysql', true);
                $data['resolved_by'] = get_current_user_id() ?: null;
            } elseif ($status === 'open') {
                $data['resolved_at'] = null;
                $data['resolved_by'] = null;
            }
        }

        $body = $request->get_param('body');
        if ($body !== null) {
            $data['body'] = wp_kses_post($body);
        }

        if (!empty($data)) {
            $wpdb->update("{$prefix}noted_pins", $data, ['id' => $id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $pin = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}noted_pins WHERE id = %d",
            $id
        ));

        if (isset($data['status']) && $data['status'] === 'resolved') {
            do_action('noted_pin_resolved', $id, (array) $pin);
        }

        return new WP_REST_Response($pin, 200);
    }

    public static function delete_pin($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $id     = absint($request->get_param('id'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$prefix}noted_pins WHERE id = %d",
            $id
        ));

        if (!$existing) {
            return new WP_Error('not_found', __('Pin not found.', 'noted-visual-feedback'), ['status' => 404]);
        }

        $wpdb->delete("{$prefix}noted_pins", ['id' => $id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        return new WP_REST_Response(['deleted' => true], 200);
    }

    // ── CSV Download ──────────────────────────────────────

    /**
     * Stream a CSV file of all pins (with page path, author, comments) directly.
     * Replaces the old export-engine infrastructure.
     */
    public static function download_pins_csv($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // Optional page_id filter.
        $page_id = absint($request->get_param('page_id') ?? 0);

        $where  = '';
        $params = [];
        if ($page_id) {
            $where    = 'WHERE p.page_id = %d';
            $params[] = $page_id;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT p.*, pg.url_path, pg.title AS page_title
                FROM {$prefix}noted_pins p
                LEFT JOIN {$prefix}noted_pages pg ON p.page_id = pg.id
                {$where}
                ORDER BY p.created_at DESC";

        $pins = empty($params)
            ? $wpdb->get_results($sql, ARRAY_A) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            : $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        // Resolve author names + thread per pin.
        foreach ($pins as &$pin) {
            if ($pin['author_type'] === 'user' && $pin['author_user_id']) {
                $u = get_userdata($pin['author_user_id']);
                $pin['author_name'] = $u ? $u->display_name : 'Unknown';
            } elseif ($pin['author_type'] === 'guest' && $pin['author_guest_id']) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $g = $wpdb->get_row($wpdb->prepare(
                    "SELECT name FROM {$prefix}noted_guests WHERE id = %d",
                    $pin['author_guest_id']
                ));
                $pin['author_name'] = $g ? $g->name : 'Guest';
            } else {
                $pin['author_name'] = 'Unknown';
            }

            // Fetch comments for this pin.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $comments = $wpdb->get_results($wpdb->prepare(
                "SELECT body FROM {$prefix}noted_comments WHERE pin_id = %d ORDER BY created_at ASC",
                $pin['id']
            ), ARRAY_A);
            $pin['_replies'] = implode(' | ', array_map(
                fn($c) => wp_strip_all_tags($c['body']),
                $comments
            ));
        }
        unset($pin);

        // Stream CSV.
        $filename = 'noted-pins-' . gmdate('Y-m-d-His') . '.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $fp = fopen('php://output', 'w'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        fputcsv($fp, [
            'Pin #', 'Page', 'Page Path', 'Breakpoint', 'Status',
            'Author', 'Author Type', 'Comment', 'Replies', 'Created',
        ]);

        foreach ($pins as $pin) {
            fputcsv($fp, [
                $pin['pin_number'],
                $pin['page_title'] ?? '',
                $pin['url_path'] ?? '/',
                $pin['breakpoint'],
                $pin['status'],
                $pin['author_name'],
                $pin['author_type'],
                wp_strip_all_tags($pin['body']),
                $pin['_replies'],
                $pin['created_at'],
            ]);
        }
        fclose($fp); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }

    // ── Comments ──────────────────────────────────────────

    public static function list_comments($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $pin_id = absint($request->get_param('pin_id'));

        // Guest scope check: only allow comments on pins in the guest's page.
        if (!self::guest_can_access_page($request, self::get_pin_page_id($pin_id))) {
            return new WP_Error('forbidden', __('You do not have access to this resource.', 'noted-visual-feedback'), ['status' => 403]);
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $comments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$prefix}noted_comments WHERE pin_id = %d ORDER BY created_at ASC",
            $pin_id
        ));

        foreach ($comments as &$comment) {
            if ($comment->author_type === 'user' && $comment->author_user_id) {
                $user = get_userdata($comment->author_user_id);
                $comment->author_name   = $user ? $user->display_name : '';
                $comment->author_avatar = $user ? get_avatar_url($user->ID, ['size' => 64]) : '';
            } elseif ($comment->author_type === 'guest' && $comment->author_guest_id) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $guest = $wpdb->get_row($wpdb->prepare(
                    "SELECT name FROM {$prefix}noted_guests WHERE id = %d",
                    $comment->author_guest_id
                ));
                $comment->author_name   = $guest ? $guest->name : '';
                $comment->author_avatar = '';
            } else {
                $comment->author_name   = '';
                $comment->author_avatar = '';
            }
        }
        unset($comment);

        return new WP_REST_Response($comments, 200);
    }

    public static function create_comment($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $pin_id = absint($request->get_param('pin_id'));

        // Guest scope check: only allow comments on pins in the guest's page.
        if (!self::guest_can_access_page($request, self::get_pin_page_id($pin_id))) {
            return new WP_Error('forbidden', __('You do not have access to this resource.', 'noted-visual-feedback'), ['status' => 403]);
        }

        // Verify pin exists.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $pin = $wpdb->get_row($wpdb->prepare(
            "SELECT id, page_id FROM {$prefix}noted_pins WHERE id = %d",
            $pin_id
        ));
        if (!$pin) {
            return new WP_Error('invalid_pin', __('Pin not found.', 'noted-visual-feedback'), ['status' => 400]);
        }

        $guest_id = $request->get_param('_noted_guest_id');
        if (!empty($guest_id)) {
            $author_type     = 'guest';
            $author_user_id  = null;
            $author_guest_id = absint($guest_id);
        } else {
            $author_type     = 'user';
            $author_user_id  = get_current_user_id();
            $author_guest_id = null;
        }

        $parent_id = $request->get_param('parent_id');

        $data = [
            'pin_id'          => $pin_id,
            'parent_id'       => $parent_id ? absint($parent_id) : null,
            'author_type'     => $author_type,
            'author_user_id'  => $author_user_id,
            'author_guest_id' => $author_guest_id,
            'body'            => wp_kses_post($request->get_param('body') ?? ''),
            'attachment_ids'  => sanitize_text_field($request->get_param('attachment_ids') ?? ''),
        ];

        $wpdb->insert("{$prefix}noted_comments", $data); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if (!$wpdb->insert_id) {
            return new WP_Error('insert_failed', __('Could not create comment.', 'noted-visual-feedback'), ['status' => 500]);
        }

        $comment_id = $wpdb->insert_id;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $comment    = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}noted_comments WHERE id = %d",
            $comment_id
        ));

        do_action('noted_comment_created', $comment_id, array_merge($data, [
            'page_id' => (int) $pin->page_id,
        ]));

        return new WP_REST_Response($comment, 201);
    }

    // ── Guests ────────────────────────────────────────────

    public static function register_guest($request) {
        // Allow companion plugins to provide a pre-registered guest identity.
        $demo_guest = apply_filters( 'noted_demo_guest_override', null, $request );
        if ( $demo_guest !== null && is_array( $demo_guest ) ) {
            return rest_ensure_response( $demo_guest );
        }

        global $wpdb;
        $prefix = $wpdb->prefix;

        $page_token = sanitize_text_field($request->get_param('page_token'));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $page = $wpdb->get_row($wpdb->prepare(
            "SELECT id, url_path, title FROM {$prefix}noted_pages WHERE access_token = %s",
            $page_token
        ));

        if (!$page) {
            return new WP_Error('invalid_token', __('Invalid or expired review link.', 'noted-visual-feedback'), ['status' => 403]);
        }

        $guest_token = bin2hex(random_bytes(32));
        $email       = sanitize_email($request->get_param('email') ?? '');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->insert("{$prefix}noted_guests", [
            'page_id'      => $page->id,
            'name'         => sanitize_text_field($request->get_param('name')),
            'email'        => $email ?: null,
            'avatar_hash'  => $email ? md5(strtolower(trim($email))) : null,
            'token'        => $guest_token,
            'last_seen_at' => current_time('mysql', true),
        ]);

        if (!$wpdb->insert_id) {
            return new WP_Error('insert_failed', __('Could not register guest.', 'noted-visual-feedback'), ['status' => 500]);
        }

        return new WP_REST_Response([
            'guest_id' => $wpdb->insert_id,
            'token'    => $guest_token,
            'page'     => [
                'id'       => (int) $page->id,
                'url_path' => $page->url_path,
                'title'    => $page->title,
            ],
        ], 201);
    }

    public static function validate_guest($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $token = sanitize_text_field($request->get_param('token'));

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $guest = $wpdb->get_row($wpdb->prepare(
            "SELECT g.*, p.url_path as page_url_path, p.title as page_title
             FROM {$prefix}noted_guests g
             JOIN {$prefix}noted_pages p ON p.id = g.page_id
             WHERE g.token = %s",
            $token
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if (!$guest) {
            return new WP_Error('invalid_token', __('Invalid guest token.', 'noted-visual-feedback'), ['status' => 403]);
        }

        // Update last seen.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->update(
            "{$prefix}noted_guests",
            ['last_seen_at' => current_time('mysql', true)],
            ['id' => $guest->id]
        );

        return new WP_REST_Response([
            'guest_id' => (int) $guest->id,
            'name'     => $guest->name,
            'email'    => $guest->email,
            'page'     => [
                'id'       => (int) $guest->page_id,
                'url_path' => $guest->page_url_path,
                'title'    => $guest->page_title,
            ],
        ], 200);
    }

    public static function check_guest_access($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $token  = sanitize_text_field($request->get_param('page_token'));

        if (empty($token)) {
            return new WP_Error('missing_token', __('Token is required.', 'noted-visual-feedback'), ['status' => 400]);
        }

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $page = $wpdb->get_row($wpdb->prepare(
            "SELECT id, url_path, title
             FROM {$prefix}noted_pages
             WHERE access_token = %s",
            $token
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if (!$page) {
            return new WP_Error('invalid_token', __('This review link is not valid.', 'noted-visual-feedback'), ['status' => 404]);
        }

        return new WP_REST_Response([
            'page_title' => $page->title ?: $page->url_path,
            'url_path'   => $page->url_path,
        ], 200);
    }

    // ── Sitemap ───────────────────────────────────────────

    public static function get_sitemap($request) {
        $urls = [];

        // Homepage.
        $urls[] = [
            'path'  => '/',
            'title' => get_bloginfo('name') . ' — Home',
            'type'  => 'homepage',
        ];

        // Published pages.
        $pages = get_pages(['post_status' => 'publish']);
        foreach ($pages as $page) {
            $urls[] = [
                'path'  => wp_make_link_relative(get_permalink($page)),
                'title' => $page->post_title,
                'type'  => 'page',
            ];
        }

        // Published posts (recent 50).
        $posts = get_posts(['post_status' => 'publish', 'numberposts' => 50]);
        foreach ($posts as $post) {
            $urls[] = [
                'path'  => wp_make_link_relative(get_permalink($post)),
                'title' => $post->post_title,
                'type'  => 'post',
            ];
        }

        // Public custom post type archives.
        $cpts = get_post_types(['public' => true, '_builtin' => false], 'objects');
        foreach ($cpts as $cpt) {
            if ($cpt->has_archive) {
                $urls[] = [
                    'path'  => wp_make_link_relative(get_post_type_archive_link($cpt->name)),
                    'title' => $cpt->labels->name . ' Archive',
                    'type'  => 'archive',
                ];
            }
        }

        return new WP_REST_Response($urls, 200);
    }

    // ── Stats ─────────────────────────────────────────────

    public static function get_stats($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        return new WP_REST_Response([
            'total_pins'     => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}noted_pins"), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'open_pins'      => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}noted_pins WHERE status = 'open'"), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'resolved_pins'  => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}noted_pins WHERE status = 'resolved'"), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'pages_reviewed' => (int) $wpdb->get_var("SELECT COUNT(DISTINCT page_id) FROM {$prefix}noted_pins"), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'active_guests'  => (int) $wpdb->get_var("SELECT COUNT(DISTINCT id) FROM {$prefix}noted_guests WHERE last_seen_at > DATE_SUB(NOW(), INTERVAL 30 DAY)"), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            'pins_this_week' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$prefix}noted_pins WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)"), // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        ], 200);
    }

    // ── Presence ────────────────────────────────────────

    public static function heartbeat($request) {
        $page_id = absint($request->get_param('page_id'));
        if (!$page_id) {
            return new WP_Error('missing_page_id', __('page_id is required.', 'noted-visual-feedback'), ['status' => 400]);
        }

        // Guest scope check: guest can only heartbeat their authorized page.
        if (!self::guest_can_access_page($request, $page_id)) {
            return new WP_Error('forbidden', __('You do not have access to this resource.', 'noted-visual-feedback'), ['status' => 403]);
        }

        $guest_id = $request->get_param('_noted_guest_id');
        if (!empty($guest_id)) {
            $viewer = [
                'type'   => 'guest',
                'id'     => absint($guest_id),
                'name'   => sanitize_text_field($request->get_param('name') ?? 'Guest'),
                'avatar' => '',
            ];
        } else {
            $user = wp_get_current_user();
            $viewer = [
                'type'   => 'user',
                'id'     => $user->ID,
                'name'   => $user->display_name,
                'avatar' => get_avatar_url($user->ID, ['size' => 64]),
            ];
        }

        $key      = 'noted_presence_' . $page_id;
        $presence = get_transient($key) ?: [];
        $uid      = $viewer['type'] . '_' . $viewer['id'];

        $presence[$uid] = array_merge($viewer, ['seen' => time()]);

        // Prune stale entries (older than 60 seconds)
        $presence = array_filter($presence, function ($v) {
            return $v['seen'] >= time() - 60;
        });

        set_transient($key, $presence, 120);

        return new WP_REST_Response(['ok' => true], 200);
    }

    public static function get_presence($request) {
        $page_id = absint($request->get_param('page_id'));
        if (!$page_id) {
            return new WP_Error('missing_page_id', __('page_id is required.', 'noted-visual-feedback'), ['status' => 400]);
        }

        // Guest scope check: guest can only read presence for their page.
        if (!self::guest_can_access_page($request, $page_id)) {
            return new WP_Error('forbidden', __('You do not have access to this resource.', 'noted-visual-feedback'), ['status' => 403]);
        }

        $key      = 'noted_presence_' . $page_id;
        $presence = get_transient($key) ?: [];

        // Filter stale
        $active = array_filter($presence, function ($v) {
            return $v['seen'] >= time() - 60;
        });

        // Exclude the current user from the list
        $guest_id = $request->get_param('_noted_guest_id');
        if (!empty($guest_id)) {
            $self = 'guest_' . absint($guest_id);
        } else {
            $self = 'user_' . get_current_user_id();
        }
        unset($active[$self]);

        $viewers = array_values(array_map(function ($v) {
            return [
                'type'   => $v['type'],
                'id'     => $v['id'],
                'name'   => $v['name'],
                'avatar' => $v['avatar'],
            ];
        }, $active));

        return new WP_REST_Response($viewers, 200);
    }
}
