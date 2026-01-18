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

        // Endpoint: applica aggiornamento
        register_rest_route(self::API_NAMESPACE, '/apply-update', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'apply_update'),
            'permission_callback' => array(__CLASS__, 'verify_api_key'),
            'args' => array(
                'type' => array(
                    'required' => true,
                    'type' => 'string',
                    'enum' => array('plugin', 'theme', 'core'),
                ),
                'slug' => array(
                    'required' => true,
                    'type' => 'string',
                ),
            ),
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
            'multisite' => WM_Monitor_Multisite_Info::get_all(),
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

    /**
     * Endpoint: Applica aggiornamento
     */
    public static function apply_update($request) {
        $type = $request->get_param('type');
        $slug = $request->get_param('slug');

        // Include required WordPress upgrade files
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/update.php';

        // Silent skin - no output
        $skin = new WP_Ajax_Upgrader_Skin();

        try {
            switch ($type) {
                case 'plugin':
                    $result = self::update_plugin($slug, $skin);
                    break;
                case 'theme':
                    $result = self::update_theme($slug, $skin);
                    break;
                case 'core':
                    $result = self::update_core($skin);
                    break;
                default:
                    return new WP_Error(
                        'invalid_type',
                        __('Tipo di aggiornamento non valido', 'webmaster-monitor'),
                        array('status' => 400)
                    );
            }

            if (is_wp_error($result)) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'error' => $result->get_error_message(),
                    'type' => $type,
                    'slug' => $slug,
                ), 500);
            }

            return new WP_REST_Response(array(
                'success' => true,
                'message' => sprintf(__('%s aggiornato con successo', 'webmaster-monitor'), ucfirst($type)),
                'type' => $type,
                'slug' => $slug,
                'new_version' => $result['new_version'] ?? null,
            ), 200);

        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage(),
                'type' => $type,
                'slug' => $slug,
            ), 500);
        }
    }

    /**
     * Aggiorna un plugin
     */
    private static function update_plugin($slug, $skin) {
        // Force refresh of update transient
        wp_update_plugins();

        $update_plugins = get_site_transient('update_plugins');

        // Find plugin file from slug
        $plugin_file = null;
        if (!empty($update_plugins->response)) {
            foreach ($update_plugins->response as $file => $plugin_data) {
                if (dirname($file) === $slug || $file === $slug) {
                    $plugin_file = $file;
                    break;
                }
            }
        }

        if (!$plugin_file) {
            return new WP_Error('no_update', __('Nessun aggiornamento disponibile per questo plugin', 'webmaster-monitor'));
        }

        $upgrader = new Plugin_Upgrader($skin);
        $result = $upgrader->upgrade($plugin_file);

        if (is_wp_error($result)) {
            return $result;
        }

        if ($result === false) {
            return new WP_Error('upgrade_failed', __('Aggiornamento plugin fallito', 'webmaster-monitor'));
        }

        // Get new version
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file);

        return array(
            'success' => true,
            'new_version' => $plugin_data['Version'] ?? null,
        );
    }

    /**
     * Aggiorna un tema
     */
    private static function update_theme($slug, $skin) {
        // Force refresh of update transient
        wp_update_themes();

        $update_themes = get_site_transient('update_themes');

        if (empty($update_themes->response[$slug])) {
            return new WP_Error('no_update', __('Nessun aggiornamento disponibile per questo tema', 'webmaster-monitor'));
        }

        $upgrader = new Theme_Upgrader($skin);
        $result = $upgrader->upgrade($slug);

        if (is_wp_error($result)) {
            return $result;
        }

        if ($result === false) {
            return new WP_Error('upgrade_failed', __('Aggiornamento tema fallito', 'webmaster-monitor'));
        }

        // Get new version
        $theme = wp_get_theme($slug);

        return array(
            'success' => true,
            'new_version' => $theme->get('Version'),
        );
    }

    /**
     * Aggiorna WordPress core
     */
    private static function update_core($skin) {
        // Force refresh of update transient
        wp_version_check();

        $updates = get_core_updates();

        if (empty($updates) || $updates[0]->response === 'latest') {
            return new WP_Error('no_update', __('WordPress e\' gia\' aggiornato all\'ultima versione', 'webmaster-monitor'));
        }

        $update = $updates[0];

        $upgrader = new Core_Upgrader($skin);
        $result = $upgrader->upgrade($update);

        if (is_wp_error($result)) {
            return $result;
        }

        if ($result === false) {
            return new WP_Error('upgrade_failed', __('Aggiornamento WordPress fallito', 'webmaster-monitor'));
        }

        global $wp_version;

        return array(
            'success' => true,
            'new_version' => $wp_version,
        );
    }
}
