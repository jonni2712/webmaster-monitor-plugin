<?php
/**
 * Classe per gestire gli endpoint REST API
 */

if (!defined('ABSPATH')) {
    exit;
}

class WM_Monitor_API {

    /**
     * Namespace REST API
     */
    const API_NAMESPACE = 'webmaster-monitor/v1';

    /**
     * Registra le routes REST API
     */
    public static function register_routes() {
        // Endpoint principale: status completo
        register_rest_route(self::API_NAMESPACE, '/status', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_status'),
            'permission_callback' => array(__CLASS__, 'verify_api_key'),
        ));

        // Endpoint: solo info server
        register_rest_route(self::API_NAMESPACE, '/server', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_server_info'),
            'permission_callback' => array(__CLASS__, 'verify_api_key'),
        ));

        // Endpoint: solo info WordPress
        register_rest_route(self::API_NAMESPACE, '/wordpress', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_wp_info'),
            'permission_callback' => array(__CLASS__, 'verify_api_key'),
        ));

        // Endpoint: health check (pubblico, per uptime monitoring)
        register_rest_route(self::API_NAMESPACE, '/health', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_health'),
            'permission_callback' => '__return_true',
        ));

        // Endpoint: verifica connessione (per test iniziale)
        register_rest_route(self::API_NAMESPACE, '/ping', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'ping'),
            'permission_callback' => array(__CLASS__, 'verify_api_key'),
        ));
    }

    /**
     * Verifica API key
     */
    public static function verify_api_key($request) {
        // Cerca API key in header o query parameter
        $api_key = $request->get_header('X-WM-API-Key');

        if (empty($api_key)) {
            $api_key = $request->get_param('api_key');
        }

        if (empty($api_key)) {
            return new WP_Error(
                'missing_api_key',
                __('API key mancante', 'webmaster-monitor'),
                array('status' => 401)
            );
        }

        if (!Webmaster_Monitor::verify_api_key($api_key)) {
            return new WP_Error(
                'invalid_api_key',
                __('API key non valida', 'webmaster-monitor'),
                array('status' => 403)
            );
        }

        return true;
    }

    /**
     * Endpoint: Status completo
     */
    public static function get_status($request) {
        $data = array(
            'plugin_version' => WM_MONITOR_VERSION,
            'timestamp' => current_time('c'),
            'server' => WM_Monitor_Server_Info::get_all(),
            'wordpress' => WM_Monitor_WP_Info::get_all(),
        );

        return new WP_REST_Response($data, 200);
    }

    /**
     * Endpoint: Info server
     */
    public static function get_server_info($request) {
        $data = array(
            'plugin_version' => WM_MONITOR_VERSION,
            'timestamp' => current_time('c'),
            'server' => WM_Monitor_Server_Info::get_all(),
        );

        return new WP_REST_Response($data, 200);
    }

    /**
     * Endpoint: Info WordPress
     */
    public static function get_wp_info($request) {
        $data = array(
            'plugin_version' => WM_MONITOR_VERSION,
            'timestamp' => current_time('c'),
            'wordpress' => WM_Monitor_WP_Info::get_all(),
        );

        return new WP_REST_Response($data, 200);
    }

    /**
     * Endpoint: Health check (pubblico)
     */
    public static function get_health($request) {
        global $wpdb;

        $health = array(
            'status' => 'ok',
            'timestamp' => current_time('c'),
            'checks' => array(),
        );

        // Check database
        $db_check = $wpdb->get_var('SELECT 1');
        $health['checks']['database'] = $db_check === '1';

        // Check filesystem
        $health['checks']['filesystem'] = is_writable(WP_CONTENT_DIR);

        // Check WP cron
        $health['checks']['cron'] = !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON;

        // Status generale
        if (!$health['checks']['database'] || !$health['checks']['filesystem']) {
            $health['status'] = 'error';
        }

        return new WP_REST_Response($health, 200);
    }

    /**
     * Endpoint: Ping (test connessione)
     */
    public static function ping($request) {
        return new WP_REST_Response(array(
            'status' => 'ok',
            'message' => 'Webmaster Monitor connesso correttamente',
            'plugin_version' => WM_MONITOR_VERSION,
            'timestamp' => current_time('c'),
            'site_url' => get_site_url(),
        ), 200);
    }
}
