<?php

defined('ABSPATH') || exit;

class Noted_Capabilities {

    const MANAGE_REVIEWS = 'noted_manage_reviews';
    const VIEW_REVIEWS   = 'noted_view_reviews';
    const RESOLVE_PINS   = 'noted_resolve_pins';
    const EXPORT_REVIEWS = 'noted_export_reviews';

    public static function init() {
        // Caps are set on activation — nothing needed on every load.
    }

    public static function activate() {
        $admin = get_role('administrator');
        if ($admin) {
            $admin->add_cap(self::MANAGE_REVIEWS);
            $admin->add_cap(self::VIEW_REVIEWS);
            $admin->add_cap(self::RESOLVE_PINS);
            $admin->add_cap(self::EXPORT_REVIEWS);
        }

        $editor = get_role('editor');
        if ($editor) {
            $editor->add_cap(self::VIEW_REVIEWS);
            $editor->add_cap(self::RESOLVE_PINS);
        }
    }

    public static function deactivate() {
        foreach (['administrator', 'editor'] as $role_name) {
            $role = get_role($role_name);
            if ($role) {
                $role->remove_cap(self::MANAGE_REVIEWS);
                $role->remove_cap(self::VIEW_REVIEWS);
                $role->remove_cap(self::RESOLVE_PINS);
                $role->remove_cap(self::EXPORT_REVIEWS);
            }
        }
    }
}
