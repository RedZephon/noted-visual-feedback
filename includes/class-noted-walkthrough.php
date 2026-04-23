<?php

defined('ABSPATH') || exit;

class Noted_Walkthrough {

    const META_KEY = 'notedwp_walkthrough_completed';

    public static function init() {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes() {
        $ns = 'noted/v1';

        register_rest_route($ns, '/walkthrough', [
            [
                'methods'             => 'GET',
                'callback'            => [self::class, 'get_state'],
                'permission_callback' => [self::class, 'check_logged_in'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [self::class, 'set_state'],
                'permission_callback' => [self::class, 'check_logged_in'],
                'args'                => [
                    'completed' => [
                        'required'          => true,
                        'type'              => 'boolean',
                        'sanitize_callback' => 'rest_sanitize_boolean',
                    ],
                ],
            ],
        ]);
    }

    public static function check_logged_in() {
        return is_user_logged_in();
    }

    public static function get_state() {
        $completed = (bool) get_user_meta(get_current_user_id(), self::META_KEY, true);

        return rest_ensure_response([
            'completed' => $completed,
        ]);
    }

    public static function set_state($request) {
        $completed = (bool) $request->get_param('completed');

        update_user_meta(get_current_user_id(), self::META_KEY, $completed ? '1' : '0');

        return rest_ensure_response([
            'success'   => true,
            'completed' => $completed,
        ]);
    }
}
