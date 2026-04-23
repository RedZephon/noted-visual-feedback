<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables from including scope
defined('ABSPATH') || exit; ?>
<div class="wrap noted-wrap noted-wrap--full">
    <?php include NOTED_PLUGIN_DIR . 'templates/admin-header.php'; ?>
    <h1 class="wp-heading-inline"><?php esc_html_e('Pages', 'noted-visual-feedback'); ?></h1>
    <p class="description" style="margin-top:4px;">
        <?php esc_html_e('All pages and posts on your site. Click "Start Reviewing" to open the feedback overlay, or "Review" to continue reviewing a page.', 'noted-visual-feedback'); ?>
    </p>
    <hr class="wp-header-end">

    <?php if (!empty($pages)) : ?>
        <table class="widefat striped" style="margin-top:16px;">
            <thead>
                <tr>
                    <th><?php esc_html_e('Page', 'noted-visual-feedback'); ?></th>
                    <th style="width:60px;"><?php esc_html_e('Type', 'noted-visual-feedback'); ?></th>
                    <th style="width:70px;"><?php esc_html_e('Status', 'noted-visual-feedback'); ?></th>
                    <th style="text-align:center;width:70px;"><?php esc_html_e('Open', 'noted-visual-feedback'); ?></th>
                    <th style="text-align:center;width:70px;"><?php esc_html_e('Resolved', 'noted-visual-feedback'); ?></th>
                    <th style="text-align:center;width:60px;"><?php esc_html_e('Total', 'noted-visual-feedback'); ?></th>
                    <th style="width:160px;"><?php esc_html_e('Actions', 'noted-visual-feedback'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $page) :
                    $review_url  = add_query_arg('noted', '', home_url($page->url_path));
                    $total       = (int) $page->pin_count;
                    $is_reviewed = $page->reviewed;

                    // Type label
                    $type_labels = ['homepage' => 'Home', 'page' => 'Page', 'post' => 'Post', 'custom' => 'Custom'];
                    $type_label  = $type_labels[$page->post_type] ?? ucfirst($page->post_type);

                    // Status badge color
                    $status_colors = ['publish' => '#00a32a', 'draft' => '#d4920a', 'pending' => '#d4920a', 'private' => '#6B6968'];
                    $status_color  = $status_colors[$page->post_status] ?? '#6B6968';
                ?>
                    <tr<?php echo !$is_reviewed ? ' style="opacity:0.7;"' : ''; ?>>
                        <td>
                            <strong><?php echo esc_html($page->title); ?></strong>
                            <div style="color:#6B6968;font-size:12px;"><?php echo esc_html($page->url_path); ?></div>
                        </td>
                        <td>
                            <span style="font-size:11px;color:#6B6968;"><?php echo esc_html($type_label); ?></span>
                        </td>
                        <td>
                            <span style="font-size:11px;color:<?php echo esc_attr($status_color); ?>;font-weight:600;">
                                <?php echo esc_html(ucfirst($page->post_status)); ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($is_reviewed && (int) $page->open_count > 0) : ?>
                                <span style="color:#F5A623;font-weight:700;"><?php echo esc_html($page->open_count); ?></span>
                            <?php else : ?>
                                <span style="color:#E3E0D9;">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($is_reviewed && (int) $page->resolved_count > 0) : ?>
                                <span style="color:#3ecf7a;font-weight:700;"><?php echo esc_html($page->resolved_count); ?></span>
                            <?php else : ?>
                                <span style="color:#E3E0D9;">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($is_reviewed && $total > 0) : ?>
                                <strong><?php echo esc_html($total); ?></strong>
                            <?php else : ?>
                                <span style="color:#E3E0D9;">&mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_reviewed) : ?>
                                <a href="<?php echo esc_url($review_url); ?>" target="_blank" class="button button-small">
                                    <?php esc_html_e('Review', 'noted-visual-feedback'); ?> &rarr;
                                </a>
                                <?php if ($total > 0) : ?>
                                    <a href="<?php echo esc_url(add_query_arg('page_path', $page->url_path, admin_url('admin.php?page=noted-pins'))); ?>" class="button button-small">
                                        <?php esc_html_e('View Feedback', 'noted-visual-feedback'); ?>
                                    </a>
                                <?php endif; ?>
                            <?php else : ?>
                                <a href="<?php echo esc_url($review_url); ?>" target="_blank" class="button button-primary button-small">
                                    <?php esc_html_e('Start Reviewing', 'noted-visual-feedback'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <div style="text-align:center;padding:60px 20px;">
            <p style="font-size:14px;color:#6B6968;"><?php esc_html_e('No pages found.', 'noted-visual-feedback'); ?></p>
        </div>
    <?php endif; ?>
    <?php include NOTED_PLUGIN_DIR . 'templates/admin-footer.php'; ?>
</div>
