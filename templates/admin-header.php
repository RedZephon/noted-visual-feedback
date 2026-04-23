<?php
defined('ABSPATH') || exit;
?>
<div class="noted-admin-header">
    <div class="noted-admin-header__brand">
        <span class="noted-admin-wordmark">
            <span class="noted-admin-wm-wrap">
                <span class="noted-admin-wm-hl"></span>
                <span class="noted-admin-wm-text"><span class="noted-admin-wm-noted">noted</span></span>
            </span>
        </span>
    </div>
    <nav class="noted-admin-header__nav">
        <a href="https://wpnoted.com/docs" target="_blank">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            <?php esc_html_e('Docs', 'noted-visual-feedback'); ?>
        </a>
        <a href="https://wpnoted.com/changelog" target="_blank">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <?php esc_html_e('Changelog', 'noted-visual-feedback'); ?>
        </a>
        <a href="https://wpnoted.com/support" target="_blank">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            <?php esc_html_e('Support', 'noted-visual-feedback'); ?>
        </a>
        <a href="https://wpnoted.com" target="_blank">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            wpnoted.com
        </a>
    </nav>
</div>
