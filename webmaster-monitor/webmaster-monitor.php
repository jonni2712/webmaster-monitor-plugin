<?php
/**
 * Plugin Name: Webmaster Monitor
 * Plugin URI: https://webmaster-monitor.com
 * Description: Collega il tuo sito WordPress alla piattaforma Webmaster Monitor per monitoraggio server, aggiornamenti e sicurezza.
 * Version: 1.0.2
 * Author: Webmaster Monitor
 * Author URI: https://webmaster-monitor.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: webmaster-monitor
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Impedisci accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Costanti plugin
define('WM_MONITOR_VERSION', '1.0.2');
define('WM_MONITOR_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WM_MONITOR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WM_MONITOR_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Classe principale del plugin
 */
class Webmaster_Monitor {

    /**
     * Istanza singleton
     */
    private static $instance = null;

    /**
     * Ottieni istanza singleton
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Costruttore
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Carica le dipendenze
     */
    private function load_dependencies() {
        require_once WM_MONITOR_PLUGIN_DIR . 'includes/class-server-info.php';
        require_once WM_MONITOR_PLUGIN_DIR . 'includes/class-wp-info.php';
        require_once WM_MONITOR_PLUGIN_DIR . 'includes/class-multisite-info.php';
        require_once WM_MONITOR_PLUGIN_DIR . 'includes/class-api.php';
        require_once WM_MONITOR_PLUGIN_DIR . 'includes/class-updater.php';
        require_once WM_MONITOR_PLUGIN_DIR . 'admin/settings.php';
    }

    /**
     * Istanza dell'updater
     */
    private $updater;

    /**
     * Inizializza gli hooks
     */
    private function init_hooks() {
        // Attivazione/Disattivazione
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Init
        add_action('init', array($this, 'init'));

        // REST API
        add_action('rest_api_init', array('WM_Monitor_API', 'register_routes'));

        // Admin
        if (is_admin()) {
            add_action('admin_menu', array('WM_Monitor_Settings', 'add_menu'));
            add_action('admin_init', array('WM_Monitor_Settings', 'register_settings'));

            // Inizializza l'updater per gli aggiornamenti automatici
            $this->updater = new WM_Monitor_Updater(__FILE__);
        }
    }

    /**
     * Inizializzazione
     */
    public function init() {
        load_plugin_textdomain('webmaster-monitor', false, dirname(WM_MONITOR_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Attivazione plugin
     */
    public function activate() {
        // Genera API key se non esiste
        if (!get_option('wm_monitor_api_key')) {
            $api_key = $this->generate_api_key();
            update_option('wm_monitor_api_key', $api_key);
        }

        // Salva data attivazione
        update_option('wm_monitor_activated', time());

        // Flush rewrite rules per REST API
        flush_rewrite_rules();
    }

    /**
     * Disattivazione plugin
     */
    public function deactivate() {
        flush_rewrite_rules();
    }

    /**
     * Genera una API key sicura
     */
    public function generate_api_key() {
        return 'wm_' . bin2hex(random_bytes(32));
    }

    /**
     * Rigenera API key
     */
    public static function regenerate_api_key() {
        $instance = self::get_instance();
        $new_key = $instance->generate_api_key();
        update_option('wm_monitor_api_key', $new_key);
        return $new_key;
    }

    /**
     * Ottieni API key corrente
     */
    public static function get_api_key() {
        return get_option('wm_monitor_api_key', '');
    }

    /**
     * Verifica API key
     */
    public static function verify_api_key($key) {
        $stored_key = self::get_api_key();
        return !empty($stored_key) && hash_equals($stored_key, $key);
    }

    /**
     * Ottieni istanza dell'updater
     */
    public function get_updater() {
        return $this->updater;
    }

    /**
     * Forza controllo aggiornamenti
     */
    public static function force_update_check() {
        $instance = self::get_instance();
        if ($instance->updater) {
            $instance->updater->force_update_check();
        }
    }
}

// Inizializza il plugin
function wm_monitor() {
    return Webmaster_Monitor::get_instance();
}

// Avvia
wm_monitor();
