<?php
/**
 * Classe per raccogliere informazioni WordPress Multisite
 */

if (!defined('ABSPATH')) {
    exit;
}

class WM_Monitor_Multisite_Info {

    /**
     * Verifica se l'installazione WordPress e' multisite
     *
     * @return bool
     */
    public static function is_multisite() {
        return is_multisite();
    }

    /**
     * Ottieni informazioni sulla rete multisite
     *
     * @return array
     */
    public static function get_network_info() {
        if (!is_multisite()) {
            return array(
                'is_multisite' => false,
                'is_main_site' => false,
                'network_id' => null,
                'network_name' => null,
                'network_domain' => null,
                'network_path' => null,
                'site_count' => 0,
                'installation_type' => null,
            );
        }

        $network = get_network();
        $site_count = get_blog_count();

        // Determina il tipo di installazione (subdomain o subdirectory)
        $installation_type = defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL ? 'subdomain' : 'subdirectory';

        return array(
            'is_multisite' => true,
            'is_main_site' => is_main_site(),
            'network_id' => $network ? $network->id : null,
            'network_name' => $network ? $network->site_name : null,
            'network_domain' => $network ? $network->domain : null,
            'network_path' => $network ? $network->path : null,
            'site_count' => $site_count,
            'installation_type' => $installation_type,
        );
    }

    /**
     * Ottieni l'elenco di tutti i sottositi della rete
     *
     * @return array
     */
    public static function get_network_sites() {
        if (!is_multisite()) {
            return array();
        }

        // Solo il sito principale puo' elencare tutti i siti
        if (!is_main_site()) {
            return array();
        }

        $sites = get_sites(array(
            'number' => 0, // Tutti i siti
            'fields' => 'all',
        ));

        $sites_data = array();

        foreach ($sites as $site) {
            // Switch al contesto del sito per ottenere informazioni dettagliate
            switch_to_blog($site->blog_id);

            $site_info = array(
                'blog_id' => (int) $site->blog_id,
                'domain' => $site->domain,
                'path' => $site->path,
                'site_name' => get_bloginfo('name'),
                'site_url' => get_site_url(),
                'home_url' => get_home_url(),
                'registered' => $site->registered,
                'last_updated' => $site->last_updated,
                'public' => (bool) $site->public,
                'archived' => (bool) $site->archived,
                'spam' => (bool) $site->spam,
                'deleted' => (bool) $site->deleted,
                'post_count' => (int) wp_count_posts()->publish,
                'is_main_site' => is_main_site($site->blog_id),
            );

            // Ottieni statistiche aggiuntive
            $site_info['users_count'] = count(get_users(array('blog_id' => $site->blog_id)));

            // Conta plugin attivi per questo sito
            $active_plugins = get_option('active_plugins', array());
            $site_info['active_plugins_count'] = count($active_plugins);

            // Ottieni il tema attivo
            $theme = wp_get_theme();
            $site_info['active_theme'] = $theme->get('Name');

            restore_current_blog();

            // Escludi siti archiviati, spam o eliminati
            if (!$site_info['archived'] && !$site_info['spam'] && !$site_info['deleted']) {
                $sites_data[] = $site_info;
            }
        }

        return $sites_data;
    }

    /**
     * Ottieni informazioni sui plugin attivati a livello di rete
     *
     * @return array
     */
    public static function get_network_plugins() {
        if (!is_multisite()) {
            return array();
        }

        $network_plugins = array();

        if (function_exists('get_site_option')) {
            $active_sitewide_plugins = get_site_option('active_sitewide_plugins', array());

            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $all_plugins = get_plugins();

            foreach ($active_sitewide_plugins as $plugin_file => $timestamp) {
                if (isset($all_plugins[$plugin_file])) {
                    $plugin_data = $all_plugins[$plugin_file];
                    $network_plugins[] = array(
                        'name' => $plugin_data['Name'],
                        'slug' => dirname($plugin_file),
                        'version' => $plugin_data['Version'],
                        'network_active' => true,
                    );
                }
            }
        }

        return $network_plugins;
    }

    /**
     * Ottieni tutti i dati multisite
     *
     * @return array
     */
    public static function get_all() {
        $network_info = self::get_network_info();

        $data = array(
            'is_multisite' => $network_info['is_multisite'],
            'is_main_site' => $network_info['is_main_site'],
            'network' => $network_info,
            'subsites' => array(),
            'network_plugins' => array(),
        );

        // Solo il sito principale della rete fornisce l'elenco dei sottositi
        if ($network_info['is_multisite'] && $network_info['is_main_site']) {
            $data['subsites'] = self::get_network_sites();
            $data['network_plugins'] = self::get_network_plugins();
        }

        return $data;
    }

    /**
     * Ottieni l'ID del blog corrente
     *
     * @return int
     */
    public static function get_current_blog_id() {
        return get_current_blog_id();
    }

    /**
     * Ottieni informazioni sul sito corrente nel contesto multisite
     *
     * @return array
     */
    public static function get_current_site_info() {
        if (!is_multisite()) {
            return array(
                'blog_id' => 1,
                'is_main_site' => true,
            );
        }

        $blog_id = get_current_blog_id();
        $site = get_site($blog_id);

        return array(
            'blog_id' => $blog_id,
            'is_main_site' => is_main_site($blog_id),
            'domain' => $site ? $site->domain : null,
            'path' => $site ? $site->path : null,
            'network_id' => $site ? $site->network_id : null,
        );
    }
}
