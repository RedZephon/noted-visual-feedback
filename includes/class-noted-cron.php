<?php

defined('ABSPATH') || exit;

class Noted_Cron {

    public static function init() {
        $settings = get_option('noted_notification_settings', []);

        if (!empty($settings['enabled']) && ($settings['mode'] ?? 'immediate') === 'digest') {
            if (!wp_next_scheduled('noted_daily_digest')) {
                $time = $settings['digest_time'] ?? '09:00';
                $timestamp = strtotime('today ' . $time);
                if ($timestamp < time()) {
                    $timestamp = strtotime('tomorrow ' . $time);
                }
                wp_schedule_event($timestamp, 'daily', 'noted_daily_digest');
            }
        } else {
            wp_clear_scheduled_hook('noted_daily_digest');
        }

        add_action('noted_daily_digest', [self::class, 'send_digest']);

        // Ensure any legacy export-cleanup cron is cleared.
        wp_clear_scheduled_hook('noted_cleanup_exports');
    }

    public static function send_digest() {
        $settings = get_option('noted_notification_settings', []);
        if (empty($settings['enabled']) || ($settings['mode'] ?? 'immediate') !== 'digest') {
            return;
        }

        global $wpdb;
        $prefix = $wpdb->prefix;

        // Get pins created in the last 24 hours.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $recent_pins = $wpdb->get_results(
            "SELECT p.*, pg.url_path
             FROM {$prefix}noted_pins p
             LEFT JOIN {$prefix}noted_pages pg ON p.page_id = pg.id
             WHERE p.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY p.created_at DESC"
        );

        $recent_comments = $wpdb->get_results(
            "SELECT c.*, pin.pin_number, pg.url_path
             FROM {$prefix}noted_comments c
             LEFT JOIN {$prefix}noted_pins pin ON c.pin_id = pin.id
             LEFT JOIN {$prefix}noted_pages pg ON pin.page_id = pg.id
             WHERE c.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             ORDER BY c.created_at DESC"
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        if (empty($recent_pins) && empty($recent_comments)) {
            return;
        }

        $email = $settings['default_email'] ?? get_option('admin_email');
        if (!is_email($email)) {
            return;
        }

        $subject = sprintf(
            /* translators: %1$s: site name, %2$d: pin count, %3$d: reply count */
            __('[%1$s] Noted Daily Digest — %2$d new pins, %3$d replies', 'noted-visual-feedback'),
            get_bloginfo('name'), count($recent_pins), count($recent_comments)
        );

        $body  = '<div style="font-family:sans-serif;max-width:560px;margin:0 auto;">';
        $body .= '<h2 style="color:#E06042;">' . esc_html__('Daily Feedback Digest', 'noted-visual-feedback') . '</h2>';

        if (!empty($recent_pins)) {
            /* translators: %d: number of new pins */
            $body .= '<h3>' . sprintf(esc_html__('New Pins (%d)', 'noted-visual-feedback'), count($recent_pins)) . '</h3><ul>';
            foreach ($recent_pins as $pin) {
                /* translators: %1$d: pin number, %2$s: page path */
                $body .= '<li>' . sprintf(esc_html__('Pin #%1$d on %2$s', 'noted-visual-feedback'), $pin->pin_number, esc_html($pin->url_path ?? '/'));
                $body .= ' — ' . esc_html(wp_trim_words(wp_strip_all_tags($pin->body), 15)) . '</li>';
            }
            $body .= '</ul>';
        }

        if (!empty($recent_comments)) {
            /* translators: %d: number of new replies */
            $body .= '<h3>' . sprintf(esc_html__('New Replies (%d)', 'noted-visual-feedback'), count($recent_comments)) . '</h3><ul>';
            foreach ($recent_comments as $c) {
                /* translators: %d: pin number */
                $body .= '<li>' . sprintf(esc_html__('Reply on Pin #%d', 'noted-visual-feedback'), $c->pin_number ?? 0);
                $body .= ' — ' . esc_html(wp_trim_words(wp_strip_all_tags($c->body), 15)) . '</li>';
            }
            $body .= '</ul>';
        }

        $body .= '<p><a href="' . esc_url(admin_url('admin.php?page=noted')) . '" style="color:#E06042;">' . esc_html__('View Dashboard', 'noted-visual-feedback') . ' &rarr;</a></p>';
        $body .= '</div>';

        wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
    }

    public static function clear_schedules() {
        wp_clear_scheduled_hook('noted_daily_digest');
        wp_clear_scheduled_hook('noted_cleanup_expired');
        wp_clear_scheduled_hook('noted_cleanup_exports');
    }
}
