<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables from including scope
defined('ABSPATH') || exit; ?>
<div class="wrap noted-wrap noted-wrap--full">
    <?php include NOTED_PLUGIN_DIR . 'templates/admin-header.php'; ?>
    <h1 class="wp-heading-inline"><?php esc_html_e('Feedback', 'noted-visual-feedback'); ?></h1>

    <?php if (current_user_can(Noted_Capabilities::EXPORT_REVIEWS)) : ?>
        <a href="<?php echo esc_url(rest_url('noted/v1/pins/csv?_wpnonce=' . wp_create_nonce('wp_rest'))); ?>"
           class="page-title-action"><?php esc_html_e('Download CSV', 'noted-visual-feedback'); ?></a>
    <?php endif; ?>

    <hr class="wp-header-end">

    <form method="post">
        <input type="hidden" name="page" value="noted-pins" />
        <?php
        wp_nonce_field('bulk-pins');
        $table = new Noted_Pins_Table();
        $table->prepare_items();
        $table->search_box(__('Search feedback', 'noted-visual-feedback'), 'noted-search');
        $table->display();
        ?>
    </form>
</div>
