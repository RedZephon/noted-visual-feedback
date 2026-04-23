<?php

defined('ABSPATH') || exit;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Noted_Pins_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct([
            'singular' => 'pin',
            'plural'   => 'pins',
            'ajax'     => false,
        ]);
    }

    public function get_columns() {
        return [
            'cb'         => '<input type="checkbox" />',
            'pin_number' => __('#', 'noted-visual-feedback'),
            'body'       => __('Comment', 'noted-visual-feedback'),
            'page'       => __('Page', 'noted-visual-feedback'),
            'status'     => __('Status', 'noted-visual-feedback'),
            'author'     => __('Author', 'noted-visual-feedback'),
            'created_at' => __('Date', 'noted-visual-feedback'),
        ];
    }

    public function get_sortable_columns() {
        return [
            'pin_number' => ['pin_number', false],
            'status'     => ['status', false],
            'created_at' => ['created_at', true],
        ];
    }

    public function get_bulk_actions() {
        $actions = [];
        if (current_user_can(Noted_Capabilities::RESOLVE_PINS)) {
            $actions['resolve'] = __('Mark Resolved', 'noted-visual-feedback');
            $actions['reopen']  = __('Reopen', 'noted-visual-feedback');
        }
        if (current_user_can(Noted_Capabilities::MANAGE_REVIEWS)) {
            $actions['delete'] = __('Delete', 'noted-visual-feedback');
        }
        return $actions;
    }

    public function column_cb($item) {
        return sprintf('<input type="checkbox" name="pin[]" value="%d" />', $item['id']);
    }

    public function column_default($item, $column_name) {
        return esc_html($item[$column_name] ?? '');
    }

    public function column_pin_number($item) {
        return esc_html($item['pin_number']);
    }

    public function column_body($item) {
        $body_text = wp_trim_words(wp_strip_all_tags($item['body']), 15, '...');
        $detail_url = admin_url("admin.php?page=noted-pins&action=view&pin={$item['id']}");
        return sprintf('<a href="%s"><strong>%s</strong></a>', esc_url($detail_url), esc_html($body_text));
    }

    public function column_status($item) {
        $color = $item['status'] === 'open' ? '#E06042' : '#3ecf7a';
        return sprintf(
            '<span class="noted-status-indicator"><span style="background:%s;" class="noted-status-dot-admin"></span>%s</span>',
            esc_attr($color),
            esc_html(ucfirst($item['status']))
        );
    }

    public function column_author($item) {
        $name = '';
        if ($item['author_type'] === 'user' && $item['author_user_id']) {
            $user = get_userdata($item['author_user_id']);
            $name = $user ? $user->display_name : __('Unknown', 'noted-visual-feedback');
        } elseif ($item['author_type'] === 'guest' && $item['author_guest_id']) {
            global $wpdb;
            $guest = $wpdb->get_row($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT name FROM {$wpdb->prefix}noted_guests WHERE id = %d",
                $item['author_guest_id']
            ));
            $name = $guest ? $guest->name : __('Guest', 'noted-visual-feedback');
        }
        $badge = $item['author_type'] === 'guest' ? ' <small>(' . esc_html__('guest', 'noted-visual-feedback') . ')</small>' : '';
        return esc_html($name) . $badge;
    }

    public function column_created_at($item) {
        return esc_html(human_time_diff(strtotime($item['created_at']), current_time('timestamp')) . ' ' . __('ago', 'noted-visual-feedback'));
    }

    public function column_page($item) {
        return esc_html($item['page_path'] ?? '—');
    }


    public function extra_tablenav($which) {
        if ($which !== 'top') {
            return;
        }
        global $wpdb;
        $prefix = $wpdb->prefix;

        $pages = $wpdb->get_results("SELECT id, url_path, title FROM {$prefix}noted_pages ORDER BY url_path"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $cur_page_path = isset($_GET['page_path']) ? sanitize_text_field(wp_unslash($_GET['page_path'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $cur_status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        ?>
        <div class="alignleft actions">
            <select name="page_path">
                <option value=""><?php esc_html_e('All Pages', 'noted-visual-feedback'); ?></option>
                <?php foreach ($pages as $pg) :
                    $label = $pg->title ?: $pg->url_path;
                    if (strpos($label, '–') !== false) $label = trim(explode('–', $label)[0]);
                    if (empty($label) || $label === get_bloginfo('name')) $label = ($pg->url_path === '/') ? __('Homepage', 'noted-visual-feedback') : $pg->url_path;
                ?>
                    <option value="<?php echo esc_attr($pg->url_path); ?>" <?php selected($cur_page_path, $pg->url_path); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status">
                <option value=""><?php esc_html_e('All Statuses', 'noted-visual-feedback'); ?></option>
                <option value="open" <?php selected($cur_status, 'open'); ?>><?php esc_html_e('Open', 'noted-visual-feedback'); ?></option>
                <option value="resolved" <?php selected($cur_status, 'resolved'); ?>><?php esc_html_e('Resolved', 'noted-visual-feedback'); ?></option>
            </select>
            <?php submit_button(__('Filter', 'noted-visual-feedback'), '', 'filter_action', false); ?>
            <?php if ($cur_page_path || $cur_status) : ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=noted-pins')); ?>" class="button"><?php esc_html_e('Clear', 'noted-visual-feedback'); ?></a>
            <?php endif; ?>
        </div>
        <?php
    }

    public function prepare_items() {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $per_page     = 25;
        $current_page = $this->get_pagenum();
        $offset       = ($current_page - 1) * $per_page;

        $where  = 'WHERE 1=1';
        $params = [];

        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (!empty($_GET['page_path'])) {
            $page_path = sanitize_text_field(wp_unslash($_GET['page_path']));
            $page_row  = $wpdb->get_row($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT id FROM {$prefix}noted_pages WHERE url_path = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $page_path
            ));
            if ($page_row) {
                $where   .= ' AND p.page_id = %d';
                $params[] = $page_row->id;
            } else {
                $where .= ' AND 1=0';
            }
        }
        if (!empty($_GET['status']) && in_array(sanitize_text_field(wp_unslash($_GET['status'])), ['open', 'resolved'], true)) {
            $where   .= ' AND p.status = %s';
            $params[] = sanitize_text_field(wp_unslash($_GET['status']));
        }
        if (!empty($_GET['s'])) {
            $where   .= ' AND p.body LIKE %s';
            $params[] = '%' . $wpdb->esc_like(sanitize_text_field(wp_unslash($_GET['s']))) . '%';
        }
        // phpcs:enable

        $orderby = 'p.created_at';
        $order   = 'DESC';
        $allowed = ['pin_number', 'status', 'created_at'];
        if (!empty($_GET['orderby']) && in_array(sanitize_key(wp_unslash($_GET['orderby'])), $allowed, true)) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $orderby = 'p.' . sanitize_key(wp_unslash($_GET['orderby'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }
        if (!empty($_GET['order']) && in_array(strtoupper(sanitize_text_field(wp_unslash($_GET['order']))), ['ASC', 'DESC'], true)) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $order = strtoupper(sanitize_text_field(wp_unslash($_GET['order']))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $count_sql   = "SELECT COUNT(*) FROM {$prefix}noted_pins p {$where}";
        $total_items = (int) (empty($params)
            ? $wpdb->get_var($count_sql) // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            : $wpdb->get_var($wpdb->prepare($count_sql, ...$params))); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $sql  = "SELECT p.*, pg.url_path AS page_path, pg.title AS page_title
                 FROM {$prefix}noted_pins p
                 LEFT JOIN {$prefix}noted_pages pg ON p.page_id = pg.id
                 {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $params[] = $per_page;
        $params[] = $offset;

        $this->items = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
        ]);

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns()];
    }
}

class Noted_Admin_Pins {

    public static function render() {
        $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ($action === 'view' && !empty($_GET['pin'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            self::render_detail(absint($_GET['pin'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        self::handle_bulk_actions();
        include NOTED_PLUGIN_DIR . 'templates/admin-pins.php';
    }

    private static function handle_bulk_actions() {
        if (empty($_POST['action']) || empty($_POST['pin'])) {
            return;
        }
        check_admin_referer('bulk-pins');

        global $wpdb;
        $prefix  = $wpdb->prefix;
        $pin_ids = array_map('absint', $_POST['pin']);
        $action  = sanitize_text_field(wp_unslash($_POST['action']));
        $message = '';

        switch ($action) {
            case 'resolve':
                foreach ($pin_ids as $id) {
                    $wpdb->update("{$prefix}noted_pins", ['status' => 'resolved', 'resolved_at' => current_time('mysql', true)], ['id' => $id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                }
                /* translators: %d: number of pins */
                $message = sprintf(__('%d pins resolved.', 'noted-visual-feedback'), count($pin_ids));
                break;
            case 'reopen':
                foreach ($pin_ids as $id) {
                    $wpdb->update("{$prefix}noted_pins", ['status' => 'open', 'resolved_at' => null], ['id' => $id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                }
                /* translators: %d: number of pins */
                $message = sprintf(__('%d pins reopened.', 'noted-visual-feedback'), count($pin_ids));
                break;
            case 'delete':
                foreach ($pin_ids as $id) {
                    $wpdb->delete("{$prefix}noted_pins", ['id' => $id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                }
                /* translators: %d: number of pins */
                $message = sprintf(__('%d pins deleted.', 'noted-visual-feedback'), count($pin_ids));
                break;
        }

        if ($message) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
    }

    private static function render_detail(int $pin_id) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // Handle actions.
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['noted_pin_action_nonce'])) {
            if (wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['noted_pin_action_nonce'])), 'noted_pin_action')) {
                $act = sanitize_text_field(wp_unslash($_POST['pin_action'] ?? ''));
                if ($act === 'resolve') {
                    $wpdb->update("{$prefix}noted_pins", ['status' => 'resolved', 'resolved_at' => current_time('mysql', true)], ['id' => $pin_id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                } elseif ($act === 'reopen') {
                    $wpdb->update("{$prefix}noted_pins", ['status' => 'open', 'resolved_at' => null], ['id' => $pin_id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                } elseif ($act === 'delete') {
                    $wpdb->delete("{$prefix}noted_pins", ['id' => $pin_id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    wp_safe_redirect(admin_url('admin.php?page=noted-pins'));
                    exit;
                }
            }
        }

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $pin = $wpdb->get_row($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT p.*, pg.url_path, pg.title AS page_title
             FROM {$prefix}noted_pins p
             LEFT JOIN {$prefix}noted_pages pg ON p.page_id = pg.id
             WHERE p.id = %d",
            $pin_id
        ), ARRAY_A);
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if (!$pin) {
            echo '<div class="wrap"><p>' . esc_html__('Pin not found.', 'noted-visual-feedback') . '</p></div>';
            return;
        }

        if ($pin['author_type'] === 'user' && $pin['author_user_id']) {
            $user = get_userdata($pin['author_user_id']);
            $pin['author_name'] = $user ? $user->display_name : __('Unknown', 'noted-visual-feedback');
        } else {
            $guest = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$prefix}noted_guests WHERE id = %d", $pin['author_guest_id'])); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $pin['author_name'] = $guest ? $guest->name : __('Guest', 'noted-visual-feedback');
        }

        $comments = $wpdb->get_results($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT * FROM {$prefix}noted_comments WHERE pin_id = %d ORDER BY created_at ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $pin_id
        ), ARRAY_A);

        foreach ($comments as &$c) {
            if ($c['author_type'] === 'user' && $c['author_user_id']) {
                $u = get_userdata($c['author_user_id']);
                $c['author_name'] = $u ? $u->display_name : __('Unknown', 'noted-visual-feedback');
            } else {
                $g = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$prefix}noted_guests WHERE id = %d", $c['author_guest_id'])); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $c['author_name'] = $g ? $g->name : __('Guest', 'noted-visual-feedback');
            }
        }
        unset($c);

        include NOTED_PLUGIN_DIR . 'templates/admin-pin-detail.php';
    }
}
