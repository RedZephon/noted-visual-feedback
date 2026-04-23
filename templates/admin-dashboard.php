<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables from including scope
defined('ABSPATH') || exit; ?>
<div class="wrap noted-wrap">
    <?php include NOTED_PLUGIN_DIR . 'templates/admin-header.php'; ?>
    <h1><?php esc_html_e('Dashboard', 'noted-visual-feedback'); ?></h1>

    <!-- Stats -->
    <div class="noted-stats-grid">
        <?php
        $stat_cards = [
            ['label' => __('Open Pins', 'noted-visual-feedback'),  'value' => $stats['open_pins'],      'color' => '#F5A623'],
            ['label' => __('Resolved', 'noted-visual-feedback'),    'value' => $stats['resolved_pins'],  'color' => '#3ecf7a'],
            ['label' => __('Pages Reviewed', 'noted-visual-feedback'), 'value' => $stats['pages_reviewed'], 'color' => '#4d8ef7'],
            ['label' => __('This Week', 'noted-visual-feedback'),   'value' => $stats['pins_this_week'], 'color' => '#9b7aed'],
        ];
        foreach ($stat_cards as $card) :
        ?>
            <div class="noted-stat-card" style="border-left:4px solid <?php echo esc_attr($card['color']); ?>;">
                <div class="noted-stat-card__value"><?php echo esc_html($card['value']); ?></div>
                <div class="noted-stat-card__label"><?php echo esc_html($card['label']); ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Quick Actions -->
    <div class="noted-quick-actions" style="margin-bottom:24px;">
        <a href="<?php echo esc_url(home_url('/?noted')); ?>" class="button button-primary" target="_blank">
            <?php esc_html_e('Start Reviewing', 'noted-visual-feedback'); ?> &rarr;
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=noted-pages')); ?>" class="button">
            <?php esc_html_e('View Pages', 'noted-visual-feedback'); ?>
        </a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=noted-pins')); ?>" class="button">
            <?php esc_html_e('View All Feedback', 'noted-visual-feedback'); ?>
        </a>
        <?php if (current_user_can(Noted_Capabilities::EXPORT_REVIEWS)) : ?>
            <a href="<?php echo esc_url(rest_url('noted/v1/pins/csv?_wpnonce=' . wp_create_nonce('wp_rest'))); ?>" class="button">
                <?php esc_html_e('Download CSV', 'noted-visual-feedback'); ?>
            </a>
        <?php endif; ?>
    </div>

    <!-- Activity Feed -->
    <div class="postbox">
        <h2 class="hndle"><?php esc_html_e('Recent Activity', 'noted-visual-feedback'); ?></h2>
        <div class="inside">
            <?php if (!empty($activities)) : ?>
                <ul class="noted-activity-list">
                    <?php foreach ($activities as $act) :
                        $icon = $act->type === 'pin' ? '&#x1f534;' : '&#x1f4ac;';
                        $action_text = $act->type === 'pin'
                            /* translators: %1$d: pin number, %2$s: page path */
                            ? sprintf(__('pinned #%1$d on %2$s', 'noted-visual-feedback'), $act->pin_number, $act->page_path ?? '/')
                            /* translators: %s: page path */
                            : sprintf(__('replied on %s', 'noted-visual-feedback'), $act->page_path ?? '/');
                        $body_preview = wp_trim_words(wp_strip_all_tags($act->body), 10, '...');
                        $link = admin_url("admin.php?page=noted-pins&action=view&pin={$act->id}");
                    ?>
                        <li class="noted-activity-item">
                            <span class="noted-activity-icon"><?php echo wp_kses_post($icon); ?></span>
                            <span>
                                <strong><?php echo esc_html($act->author_name); ?></strong>
                                <?php echo esc_html($action_text); ?>
                                — <a href="<?php echo esc_url($link); ?>"><?php echo esc_html($body_preview); ?></a>
                            </span>
                            <span class="noted-activity-time"><?php echo esc_html(human_time_diff(strtotime($act->created_at), current_time('timestamp')) . ' ago'); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><?php esc_html_e('No activity yet. Visit any page and add ?noted to the URL to start reviewing.', 'noted-visual-feedback'); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <?php include NOTED_PLUGIN_DIR . 'templates/admin-footer.php'; ?>
</div>
