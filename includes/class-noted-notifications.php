<?php

defined('ABSPATH') || exit;

class Noted_Notifications {

    public static function init() {
        add_action('noted_pin_created', [self::class, 'on_pin_created'], 10, 2);
        add_action('noted_comment_created', [self::class, 'on_comment_created'], 10, 2);
        add_action('noted_pin_resolved', [self::class, 'on_pin_resolved'], 10, 2);
    }

    private static function get_settings(): array {
        return get_option('noted_notification_settings', [
            'enabled'       => true,
            'on_new_pin'    => true,
            'on_reply'      => true,
            'on_resolved'   => false,
            'mode'          => 'immediate',
            'digest_time'   => '09:00',
            'default_email' => get_option('admin_email'),
        ]);
    }

    private static function get_recipients(): array {
        $settings   = self::get_settings();
        $default    = $settings['default_email'] ?? get_option('admin_email');
        $recipients = [$default];
        return array_filter($recipients, 'is_email');
    }

    public static function on_pin_created(int $pin_id, array $pin_data) {
        $settings = self::get_settings();
        if (empty($settings['enabled']) || empty($settings['on_new_pin'])) {
            return;
        }

        $recipients = self::get_recipients();
        if (empty($recipients)) {
            return;
        }

        $page_url  = home_url($pin_data['page_path'] ?? '/');
        $admin_url = admin_url("admin.php?page=noted-pins&action=view&pin={$pin_id}");

        $subject = sprintf(
            /* translators: %1$s: site name, %2$d: pin number, %3$s: page path */
            __('[%1$s] New feedback — Pin #%2$d on %3$s', 'noted-visual-feedback'),
            get_bloginfo('name'),
            $pin_data['pin_number'] ?? 0,
            $pin_data['page_path'] ?? '/'
        );

        $body  = '<div style="font-family:sans-serif;max-width:560px;margin:0 auto;">';
        $body .= '<h2 style="color:#E06042;">' . esc_html__('New Feedback', 'noted-visual-feedback') . '</h2>';
        $body .= '<p><strong>' . esc_html($pin_data['author_name'] ?? __('Someone', 'noted-visual-feedback')) . '</strong> ' . esc_html__('left feedback on', 'noted-visual-feedback') . ' ';
        $body .= '<a href="' . esc_url($page_url) . '">' . esc_html($pin_data['page_path'] ?? '/') . '</a></p>';
        $body .= '<blockquote style="border-left:3px solid #E06042;padding:8px 16px;margin:16px 0;color:#555;">';
        $body .= wp_kses_post($pin_data['body'] ?? '');
        $body .= '</blockquote>';
        $body .= '<p><a href="' . esc_url($admin_url) . '" style="color:#E06042;">' . esc_html__('View in Dashboard', 'noted-visual-feedback') . ' &rarr;</a></p>';
        $body .= '</div>';

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        foreach ($recipients as $email) {
            wp_mail($email, $subject, $body, $headers);
        }
    }

    public static function on_comment_created(int $comment_id, array $comment_data) {
        $settings = self::get_settings();
        if (empty($settings['enabled']) || empty($settings['on_reply'])) {
            return;
        }

        global $wpdb;
        $prefix = $wpdb->prefix;

        $pin = $wpdb->get_row($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT * FROM {$prefix}noted_pins WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $comment_data['pin_id'] ?? 0
        ));
        if (!$pin) {
            return;
        }

        $recipients = self::get_recipients();
        if (empty($recipients)) {
            return;
        }

        // Resolve author name.
        $author_name = __('Someone', 'noted-visual-feedback');
        if ($comment_data['author_type'] === 'user' && !empty($comment_data['author_user_id'])) {
            $user = get_userdata($comment_data['author_user_id']);
            $author_name = $user ? $user->display_name : __('Team member', 'noted-visual-feedback');
        } elseif (!empty($comment_data['author_guest_id'])) {
            $guest = $wpdb->get_row($wpdb->prepare("SELECT name FROM {$prefix}noted_guests WHERE id = %d", $comment_data['author_guest_id'])); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $author_name = $guest ? $guest->name : __('Guest', 'noted-visual-feedback');
        }

        $page = $wpdb->get_row($wpdb->prepare("SELECT url_path FROM {$prefix}noted_pages WHERE id = %d", $pin->page_id)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $admin_url = admin_url("admin.php?page=noted-pins&action=view&pin={$pin->id}");

        $subject = sprintf(
            /* translators: %1$s: site name, %2$d: pin number */
            __('[%1$s] Reply on Pin #%2$d', 'noted-visual-feedback'),
            get_bloginfo('name'),
            $pin->pin_number
        );

        $body  = '<div style="font-family:sans-serif;max-width:560px;margin:0 auto;">';
        $body .= '<h2 style="color:#E06042;">' . esc_html__('New Reply', 'noted-visual-feedback') . '</h2>';
        $body .= '<p><strong>' . esc_html($author_name) . '</strong> ' . esc_html__('replied to', 'noted-visual-feedback') . ' Pin #' . esc_html($pin->pin_number);
        $body .= ' ' . esc_html__('on', 'noted-visual-feedback') . ' <em>' . esc_html($page->url_path ?? '/') . '</em></p>';
        $body .= '<blockquote style="border-left:3px solid #E06042;padding:8px 16px;margin:16px 0;color:#555;">';
        $body .= wp_kses_post($comment_data['body'] ?? '');
        $body .= '</blockquote>';
        $body .= '<p><a href="' . esc_url($admin_url) . '" style="color:#E06042;">' . esc_html__('View Thread', 'noted-visual-feedback') . ' &rarr;</a></p>';
        $body .= '</div>';

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        foreach ($recipients as $email) {
            wp_mail($email, $subject, $body, $headers);
        }
    }

    public static function on_pin_resolved(int $pin_id, array $pin_data) {
        $settings = self::get_settings();
        if (empty($settings['enabled']) || empty($settings['on_resolved'])) {
            return;
        }

        global $wpdb;
        $prefix = $wpdb->prefix;

        $recipients = self::get_recipients();
        if (empty($recipients)) {
            return;
        }

        $admin_url = admin_url("admin.php?page=noted-pins&action=view&pin={$pin_id}");
        $subject   = sprintf(
            /* translators: %1$s: site name, %2$d: pin number */
            __('[%1$s] Pin #%2$d resolved', 'noted-visual-feedback'),
            get_bloginfo('name'),
            $pin_data['pin_number'] ?? 0
        );

        $body  = '<div style="font-family:sans-serif;max-width:560px;margin:0 auto;">';
        $body .= '<h2 style="color:#3ecf7a;">' . esc_html__('Pin Resolved', 'noted-visual-feedback') . '</h2>';
        /* translators: %d: pin number */
        $body .= '<p>' . sprintf(esc_html__('Pin #%d has been marked as resolved.', 'noted-visual-feedback'), $pin_data['pin_number'] ?? 0) . '</p>';
        $body .= '<p><a href="' . esc_url($admin_url) . '" style="color:#E06042;">' . esc_html__('View Pin', 'noted-visual-feedback') . ' &rarr;</a></p>';
        $body .= '</div>';

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        foreach ($recipients as $email) {
            wp_mail($email, $subject, $body, $headers);
        }
    }
}
