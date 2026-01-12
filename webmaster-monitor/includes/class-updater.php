<?php
/**
 * Plugin Updater Class
 *
 * Gestisce gli aggiornamenti automatici del plugin dalla piattaforma Webmaster Monitor.
 *
 * @package Webmaster_Monitor
 */

// Impedisci accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe per gestire gli aggiornamenti del plugin
 */
class WM_Monitor_Updater {

    /**
     * URL dell'API per il check aggiornamenti
     */
    private $api_url = 'https://app.webmaster-monitor.com/api/plugin/info';

    /**
     * Slug del plugin
     */
    private $plugin_slug;

    /**
     * Basename del plugin (cartella/file.php)
     */
    private $plugin_basename;

    /**
     * Versione corrente del plugin
     */
    private $current_version;

    /**
     * Cache key per le informazioni del plugin
     */
    private $cache_key = 'wm_monitor_update_info';

    /**
     * Durata cache in secondi (12 ore)
     */
    private $cache_duration = 43200;

    /**
     * Costruttore
     *
     * @param string $plugin_file Path completo del file principale del plugin
     */
    public function __construct($plugin_file) {
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->plugin_slug = dirname($this->plugin_basename);
        $this->current_version = WM_MONITOR_VERSION;

        // Hook per il check degli aggiornamenti
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));

        // Hook per le informazioni del plugin (popup dettagli)
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);

        // Hook dopo l'installazione di un aggiornamento
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);

        // Hook per aggiungere link nella pagina plugin
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
    }

    /**
     * Controlla se ci sono aggiornamenti disponibili
     *
     * @param object $transient Transient degli aggiornamenti
     * @return object Transient modificato
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Ottieni informazioni remote
        $remote_info = $this->get_remote_info();

        if ($remote_info && isset($remote_info->version)) {
            // Confronta versioni
            if (version_compare($this->current_version, $remote_info->version, '<')) {
                $plugin_data = new stdClass();
                $plugin_data->slug = $this->plugin_slug;
                $plugin_data->plugin = $this->plugin_basename;
                $plugin_data->new_version = $remote_info->version;
                $plugin_data->url = $remote_info->homepage ?? '';
                $plugin_data->package = $remote_info->download_url ?? '';
                $plugin_data->tested = $remote_info->tested ?? '';
                $plugin_data->requires_php = $remote_info->requires_php ?? '';
                $plugin_data->requires = $remote_info->requires ?? '';

                // Aggiungi icone se disponibili
                if (isset($remote_info->icons)) {
                    $plugin_data->icons = (array) $remote_info->icons;
                }

                // Aggiungi banner se disponibili
                if (isset($remote_info->banners)) {
                    $plugin_data->banners = (array) $remote_info->banners;
                }

                $transient->response[$this->plugin_basename] = $plugin_data;
            } else {
                // Nessun aggiornamento, aggiungi a no_update per evitare check ripetuti
                $plugin_data = new stdClass();
                $plugin_data->slug = $this->plugin_slug;
                $plugin_data->plugin = $this->plugin_basename;
                $plugin_data->new_version = $this->current_version;
                $plugin_data->url = '';
                $plugin_data->package = '';

                $transient->no_update[$this->plugin_basename] = $plugin_data;
            }
        }

        return $transient;
    }

    /**
     * Fornisce informazioni dettagliate sul plugin per il popup
     *
     * @param false|object|array $result Risultato default
     * @param string $action Azione richiesta
     * @param object $args Argomenti della richiesta
     * @return false|object Informazioni plugin o false
     */
    public function plugin_info($result, $action, $args) {
        // Verifica che sia la richiesta giusta
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        // Ottieni informazioni remote
        $remote_info = $this->get_remote_info();

        if (!$remote_info) {
            return $result;
        }

        $plugin_info = new stdClass();
        $plugin_info->name = $remote_info->name ?? 'Webmaster Monitor';
        $plugin_info->slug = $this->plugin_slug;
        $plugin_info->version = $remote_info->version ?? '';
        $plugin_info->author = $remote_info->author ?? '';
        $plugin_info->author_profile = $remote_info->author_profile ?? '';
        $plugin_info->homepage = $remote_info->homepage ?? '';
        $plugin_info->requires = $remote_info->requires ?? '';
        $plugin_info->tested = $remote_info->tested ?? '';
        $plugin_info->requires_php = $remote_info->requires_php ?? '';
        $plugin_info->downloaded = 0;
        $plugin_info->last_updated = date('Y-m-d H:i:s');
        $plugin_info->download_link = $remote_info->download_url ?? '';

        // Sezioni (descrizione, installazione, changelog)
        if (isset($remote_info->sections)) {
            $plugin_info->sections = (array) $remote_info->sections;
        }

        // Banner
        if (isset($remote_info->banners)) {
            $plugin_info->banners = (array) $remote_info->banners;
        }

        // Icone
        if (isset($remote_info->icons)) {
            $plugin_info->icons = (array) $remote_info->icons;
        }

        return $plugin_info;
    }

    /**
     * Azioni dopo l'installazione di un aggiornamento
     *
     * @param bool $response Risposta installazione
     * @param array $hook_extra Extra hook
     * @param array $result Risultato installazione
     * @return array Risultato
     */
    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        // Verifica che sia il nostro plugin
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_basename) {
            return $result;
        }

        // La cartella potrebbe essere rinominata dopo l'estrazione
        // Assicuriamoci che il nome sia corretto
        $plugin_folder = WP_PLUGIN_DIR . '/' . $this->plugin_slug;
        $wp_filesystem->move($result['destination'], $plugin_folder);
        $result['destination'] = $plugin_folder;

        // Riattiva il plugin se era attivo
        if (is_plugin_active($this->plugin_basename)) {
            activate_plugin($this->plugin_basename);
        }

        // Pulisci la cache
        $this->clear_cache();

        return $result;
    }

    /**
     * Aggiunge link personalizzati nella riga del plugin
     *
     * @param array $links Link esistenti
     * @param string $file File del plugin
     * @return array Link modificati
     */
    public function plugin_row_meta($links, $file) {
        if ($file !== $this->plugin_basename) {
            return $links;
        }

        $links[] = '<a href="https://webmaster-monitor.com/docs" target="_blank">' .
                   __('Documentazione', 'webmaster-monitor') . '</a>';
        $links[] = '<a href="https://webmaster-monitor.com/support" target="_blank">' .
                   __('Supporto', 'webmaster-monitor') . '</a>';

        return $links;
    }

    /**
     * Ottiene le informazioni remote sul plugin
     *
     * @param bool $force_refresh Forza il refresh della cache
     * @return object|false Informazioni remote o false in caso di errore
     */
    private function get_remote_info($force_refresh = false) {
        // Controlla cache
        if (!$force_refresh) {
            $cached = get_transient($this->cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Effettua richiesta API
        $response = wp_remote_get($this->api_url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
            ),
        ));

        // Verifica errori
        if (is_wp_error($response)) {
            return false;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return false;
        }

        // Decodifica JSON
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (!$data || !isset($data->version)) {
            return false;
        }

        // Salva in cache
        set_transient($this->cache_key, $data, $this->cache_duration);

        return $data;
    }

    /**
     * Pulisce la cache delle informazioni remote
     */
    public function clear_cache() {
        delete_transient($this->cache_key);
    }

    /**
     * Forza un controllo aggiornamenti
     */
    public function force_update_check() {
        $this->clear_cache();
        delete_site_transient('update_plugins');
        wp_update_plugins();
    }
}
