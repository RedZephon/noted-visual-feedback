<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables from including scope
defined('ABSPATH') || exit; ?>
<div class="wrap noted-wrap">
    <p><a href="<?php echo esc_url(admin_url('admin.php?page=noted-pins')); ?>">&larr; <?php esc_html_e('Back to Feedback', 'noted-visual-feedback'); ?></a></p>

    <h1>
        <?php
        printf(
            /* translators: %1$d: pin number, %2$s: page path */
            esc_html__('Pin #%1$d — %2$s', 'noted-visual-feedback'),
            (int) $pin['pin_number'],
            esc_html($pin['url_path'] ?? '/')
        );
        ?>
        <span class="noted-status-indicator" style="margin-left:12px;">
            <span class="noted-status-dot-admin" style="background:<?php echo esc_attr($pin['status'] === 'open' ? '#E06042' : '#3ecf7a'); ?>;"></span>
            <?php echo esc_html(ucfirst($pin['status'])); ?>
        </span>
    </h1>

    <p class="description">
        <?php echo esc_html(ucfirst($pin['breakpoint'])); ?>
        &middot; <?php esc_html_e('by', 'noted-visual-feedback'); ?> <?php echo esc_html($pin['author_name']); ?>
        <?php if ($pin['author_type'] === 'guest') : ?><small>(<?php esc_html_e('guest', 'noted-visual-feedback'); ?>)</small><?php endif; ?>
        &middot; <?php echo esc_html(human_time_diff(strtotime($pin['created_at']), current_time('timestamp')) . ' ' . __('ago', 'noted-visual-feedback')); ?>
    </p>

    <div class="noted-pin-detail__body">
        <?php echo wp_kses_post($pin['body']); ?>
    </div>

    <!-- Actions -->
    <div style="display:flex;gap:8px;margin:16px 0;">
        <form method="post" style="display:inline;">
            <?php wp_nonce_field('noted_pin_action', 'noted_pin_action_nonce'); ?>
            <?php if ($pin['status'] === 'open') : ?>
                <input type="hidden" name="pin_action" value="resolve">
                <button type="submit" class="button button-primary"><?php esc_html_e('Resolve', 'noted-visual-feedback'); ?></button>
            <?php else : ?>
                <input type="hidden" name="pin_action" value="reopen">
                <button type="submit" class="button"><?php esc_html_e('Reopen', 'noted-visual-feedback'); ?></button>
            <?php endif; ?>
        </form>
        <form method="post" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e('Delete this pin and all its comments?', 'noted-visual-feedback'); ?>');">
            <?php wp_nonce_field('noted_pin_action', 'noted_pin_action_nonce'); ?>
            <input type="hidden" name="pin_action" value="delete">
            <button type="submit" class="button noted-delete-link"><?php esc_html_e('Delete', 'noted-visual-feedback'); ?></button>
        </form>
        <?php
        $page_url = home_url($pin['url_path'] ?? '/');
        $review_url = add_query_arg('noted', '', $page_url);
        ?>
        <a href="<?php echo esc_url($review_url); ?>" class="button" target="_blank"><?php esc_html_e('View on Site', 'noted-visual-feedback'); ?> &rarr;</a>
    </div>

    <!-- Thread -->
    <?php if (!empty($comments)) : ?>
        <h3><?php esc_html_e('Thread', 'noted-visual-feedback'); ?></h3>
        <div class="noted-pin-thread">
            <?php foreach ($comments as $comment) : ?>
                <div class="noted-pin-thread__item">
                    <strong><?php echo esc_html($comment['author_name']); ?></strong>
                    <?php if ($comment['author_type'] === 'guest') : ?><small>(<?php esc_html_e('guest', 'noted-visual-feedback'); ?>)</small><?php endif; ?>
                    <span class="noted-activity-time">&middot; <?php echo esc_html(human_time_diff(strtotime($comment['created_at']), current_time('timestamp')) . ' ' . __('ago', 'noted-visual-feedback')); ?></span>
                    <p><?php echo wp_kses_post($comment['body']); ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
