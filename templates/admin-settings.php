<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template variables from including scope
defined('ABSPATH') || exit;
$active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$tabs = [
    'general'       => __('General', 'noted-visual-feedback'),
    'permissions'   => __('Permissions', 'noted-visual-feedback'),
    'notifications' => __('Notifications', 'noted-visual-feedback'),
    'data'          => __('Data', 'noted-visual-feedback'),
];
?>
<div class="wrap noted-wrap noted-wrap--full">
    <?php include NOTED_PLUGIN_DIR . 'templates/admin-header.php'; ?>
    <h1><?php esc_html_e('Settings', 'noted-visual-feedback'); ?></h1>

    <?php if ($saved) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'noted-visual-feedback'); ?></p></div>
    <?php endif; ?>

    <nav class="noted-settings-tabs">
        <?php foreach ($tabs as $tab_id => $tab_label) : ?>
            <a href="<?php echo esc_url(add_query_arg('tab', $tab_id, admin_url('admin.php?page=noted-settings'))); ?>"
               class="noted-settings-tab <?php echo $active_tab === $tab_id ? 'active' : ''; ?>">
                <?php echo esc_html($tab_label); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if ($active_tab === 'general') : ?>
        <!-- ═══ GENERAL TAB ═══ -->
        <form method="post">
            <?php wp_nonce_field('noted_save_settings', 'noted_settings_nonce'); ?>
            <h2><?php esc_html_e('Overlay Visibility', 'noted-visual-feedback'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Visibility Mode', 'noted-visual-feedback'); ?></th>
                    <td>
                        <fieldset>
                            <label><input type="radio" name="noted_visibility" value="param" <?php checked($visibility, 'param'); ?>> <strong><?php esc_html_e('URL Parameter (default)', 'noted-visual-feedback'); ?></strong><p class="description"><?php esc_html_e('Add ?noted to any page URL to activate the review overlay.', 'noted-visual-feedback'); ?></p></label><br>
                            <label><input type="radio" name="noted_visibility" value="always" <?php checked($visibility, 'always'); ?>> <strong><?php esc_html_e('Always On', 'noted-visual-feedback'); ?></strong><p class="description"><?php esc_html_e('Always visible for logged-in users with review permissions.', 'noted-visual-feedback'); ?></p></label><br>
                            <label><input type="radio" name="noted_visibility" value="off" <?php checked($visibility, 'off'); ?>> <strong><?php esc_html_e('Off', 'noted-visual-feedback'); ?></strong><p class="description"><?php esc_html_e('Disabled. Guest share links still work.', 'noted-visual-feedback'); ?></p></label>
                        </fieldset>
                    </td>
                </tr>
            </table>
            <h2><?php esc_html_e('Appearance', 'noted-visual-feedback'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="noted_brand_color"><?php esc_html_e('Brand Color', 'noted-visual-feedback'); ?></label></th>
                    <td>
                        <input type="color" name="noted_brand_color" id="noted_brand_color" value="<?php echo esc_attr($brand_color); ?>">
                        <p class="description"><?php esc_html_e('Accent color used for pin markers, highlights, active states, and buttons in the review overlay.', 'noted-visual-feedback'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="noted_toolbar_position"><?php esc_html_e('Toolbar Position', 'noted-visual-feedback'); ?></label></th>
                    <td>
                        <select name="noted_toolbar_position" id="noted_toolbar_position">
                            <option value="top" <?php selected($toolbar_position, 'top'); selected($toolbar_position, 'top-right'); ?>><?php esc_html_e('Top (default)', 'noted-visual-feedback'); ?></option>
                            <option value="bottom" <?php selected($toolbar_position, 'bottom'); selected($toolbar_position, 'bottom-right'); ?>><?php esc_html_e('Bottom', 'noted-visual-feedback'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Where the review toolbar appears in the overlay.', 'noted-visual-feedback'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Attribution', 'noted-visual-feedback'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="noted_show_branding" value="1" <?php checked(get_option('noted_show_branding', false)); ?> />
                            <?php esc_html_e('Show Noted branding in the feedback overlay', 'noted-visual-feedback'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('When enabled, a small "Powered by Noted" link appears in the guest-facing overlay. Off by default.', 'noted-visual-feedback'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <h2 style="margin-top:32px;"><?php esc_html_e('Onboarding', 'noted-visual-feedback'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Guided walkthrough', 'noted-visual-feedback'); ?></th>
                <td>
                    <button type="button" id="noted-reset-walkthrough" class="button" style="font-family:'Inter Tight',system-ui,sans-serif;">
                        <?php esc_html_e('Reset walkthrough', 'noted-visual-feedback'); ?>
                    </button>
                    <p class="description"><?php esc_html_e('Reset the guided walkthrough so it appears again next time you open the Noted Visual Feedback overlay.', 'noted-visual-feedback'); ?></p>
                </td>
            </tr>
        </table>

    <?php elseif ($active_tab === 'permissions') : ?>
        <!-- ═══ PERMISSIONS TAB ═══ -->
        <form method="post">
            <?php wp_nonce_field('noted_save_permissions', 'noted_permissions_nonce'); ?>
            <p class="description"><?php esc_html_e('Control which WordPress roles can access Noted Visual Feedback features.', 'noted-visual-feedback'); ?></p>
            <table class="widefat noted-permissions-table" style="max-width:600px;margin-top:16px;">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Role', 'noted-visual-feedback'); ?></th>
                        <?php foreach ($caps as $cap_label) : ?>
                            <th><?php echo esc_html($cap_label); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_roles as $role_name => $role_data) :
                        $role = get_role($role_name);
                    ?>
                        <tr>
                            <td><?php echo esc_html(translate_user_role($role_data['name'])); ?></td>
                            <?php foreach ($caps as $cap_key => $cap_label) :
                                $field_name = "noted_perm_{$role_name}_{$cap_key}";
                                $checked = $role && $role->has_cap($cap_key);
                                $disabled = ($role_name === 'administrator' && $cap_key === Noted_Capabilities::MANAGE_REVIEWS);
                            ?>
                                <td>
                                    <input type="checkbox" name="<?php echo esc_attr($field_name); ?>" value="1" <?php checked($checked); ?> <?php disabled($disabled); ?>>
                                    <?php if ($disabled) : ?><input type="hidden" name="<?php echo esc_attr($field_name); ?>" value="1"><?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php submit_button(__('Save Permissions', 'noted-visual-feedback')); ?>
        </form>

    <?php elseif ($active_tab === 'notifications') : ?>
        <!-- ═══ NOTIFICATIONS TAB ═══ -->
        <form method="post">
            <?php wp_nonce_field('noted_save_notifications', 'noted_notifications_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable Notifications', 'noted-visual-feedback'); ?></th>
                    <td><label><input type="checkbox" name="notif_enabled" value="1" <?php checked(!empty($notif_settings['enabled'])); ?>> <?php esc_html_e('Send email notifications for feedback events', 'noted-visual-feedback'); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Events', 'noted-visual-feedback'); ?></th>
                    <td>
                        <label><input type="checkbox" name="notif_on_new_pin" value="1" <?php checked(!empty($notif_settings['on_new_pin'])); ?>> <?php esc_html_e('New pin created', 'noted-visual-feedback'); ?></label><br>
                        <label><input type="checkbox" name="notif_on_reply" value="1" <?php checked(!empty($notif_settings['on_reply'])); ?>> <?php esc_html_e('Reply added to pin', 'noted-visual-feedback'); ?></label><br>
                        <label><input type="checkbox" name="notif_on_resolved" value="1" <?php checked(!empty($notif_settings['on_resolved'])); ?>> <?php esc_html_e('Pin resolved', 'noted-visual-feedback'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Delivery Mode', 'noted-visual-feedback'); ?></th>
                    <td>
                        <label><input type="radio" name="notif_mode" value="immediate" <?php checked(($notif_settings['mode'] ?? 'immediate'), 'immediate'); ?>> <?php esc_html_e('Immediate', 'noted-visual-feedback'); ?></label><br>
                        <label><input type="radio" name="notif_mode" value="digest" <?php checked(($notif_settings['mode'] ?? 'immediate'), 'digest'); ?>> <?php esc_html_e('Daily digest at', 'noted-visual-feedback'); ?>
                            <input type="time" name="notif_digest_time" value="<?php echo esc_attr($notif_settings['digest_time'] ?? '09:00'); ?>" style="width:auto;">
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="notif_default_email"><?php esc_html_e('Default Email', 'noted-visual-feedback'); ?></label></th>
                    <td><input type="email" name="notif_default_email" id="notif_default_email" class="regular-text" value="<?php echo esc_attr($notif_settings['default_email'] ?? get_option('admin_email')); ?>"></td>
                </tr>
            </table>
            <?php submit_button(__('Save Notifications', 'noted-visual-feedback')); ?>
        </form>

    <?php elseif ($active_tab === 'data') : ?>
        <!-- ═══ DATA TAB ═══ -->
        <h2><?php esc_html_e('Database Stats', 'noted-visual-feedback'); ?></h2>
        <table class="widefat striped" style="max-width:400px;">
            <tbody>
                <?php foreach ($table_stats as $table => $count) : ?>
                    <tr><td><?php echo esc_html(str_replace('noted_', '', $table)); ?></td><td><?php echo esc_html(number_format_i18n($count)); ?> <?php esc_html_e('rows', 'noted-visual-feedback'); ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (current_user_can(Noted_Capabilities::EXPORT_REVIEWS)) : ?>
            <h2 style="margin-top:24px;"><?php esc_html_e('Export', 'noted-visual-feedback'); ?></h2>
            <p>
                <a href="<?php echo esc_url(rest_url('noted/v1/pins/csv?_wpnonce=' . wp_create_nonce('wp_rest'))); ?>" class="button button-primary">
                    <?php esc_html_e('Download All Feedback (CSV)', 'noted-visual-feedback'); ?>
                </a>
            </p>
        <?php endif; ?>

        <h2 style="margin-top:24px;"><?php esc_html_e('Danger Zone', 'noted-visual-feedback'); ?></h2>
        <div style="background:#fff;border:0.5px solid #ef5350;border-radius:8px;padding:20px;max-width:500px;">
            <p style="margin-top:0;font-weight:600;color:#ef5350;"><?php esc_html_e('Delete All Data', 'noted-visual-feedback'); ?></p>
            <p class="description"><?php esc_html_e('This will permanently delete all pins, comments, and page data. This cannot be undone.', 'noted-visual-feedback'); ?></p>
            <form method="post" onsubmit="return confirm('<?php esc_attr_e('Delete ALL Noted Visual Feedback data? This cannot be undone.', 'noted-visual-feedback'); ?>');" style="margin-top:12px;">
                <?php wp_nonce_field('noted_delete_all_data', 'noted_delete_data_nonce'); ?>
                <button type="submit" class="button" style="color:#ef5350;border-color:#ef5350;"><?php esc_html_e('Delete All Data', 'noted-visual-feedback'); ?></button>
            </form>
        </div>

    <?php endif; ?>

    <?php include NOTED_PLUGIN_DIR . 'templates/admin-footer.php'; ?>
</div>
