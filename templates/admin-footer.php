<?php defined('ABSPATH') || exit; ?>
<div class="noted-admin-footer">
    <div class="noted-admin-footer__credit">
        <?php printf(
            /* translators: %s: author link */
            esc_html__('Noted Visual Feedback by %s', 'noted-visual-feedback'),
            '<a href="https://mountainthirteen.com" target="_blank" style="color:#F5A623;text-decoration:none;font-weight:700;">Mountain Thirteen</a>'
        ); ?>
    </div>
    <div class="noted-admin-footer__links">
        <a href="https://wpnoted.com/docs" target="_blank"><?php esc_html_e('Documentation', 'noted-visual-feedback'); ?></a>
        <span style="color:#E3E0D9;">|</span>
        <a href="https://wpnoted.com/support" target="_blank"><?php esc_html_e('Support', 'noted-visual-feedback'); ?></a>
        <span style="color:#E3E0D9;">|</span>
        <a href="https://wpnoted.com/changelog" target="_blank"><?php esc_html_e('Changelog', 'noted-visual-feedback'); ?></a>
        <span style="color:#E3E0D9;">|</span>
        <a href="https://wpnoted.com" target="_blank">wpnoted.com</a>
    </div>
    <div class="noted-admin-footer__version">
        v<?php echo esc_html(NOTED_VERSION); ?>
    </div>
</div>
