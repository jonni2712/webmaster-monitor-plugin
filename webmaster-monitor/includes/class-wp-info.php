<?php
/**
 * Classe per raccogliere informazioni su WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

class WM_Monitor_WP_Info {

    /**
     * Ottieni tutte le informazioni WordPress
     */
    public static function get_all() {
        return array(
            'core' => self::get_core_info(),
            'plugins' => self::get_plugins_info(),
            'themes' => self::get_themes_info(),
            'users' => self::get_users_info(),
            'site_health' => self::get_site_health(),
            'constants' => self::get_wp_constants(),
        );
    }

    /**
     * Informazioni core WordPress
     */
    public static function get_core_info() {
        global $wp_version;

        // Controlla aggiornamenti
        $update_available = false;
        $latest_version = $wp_version;

        $update_core = get_site_transient('update_core');
        if ($update_core && isset($update_core->updates) && !empty($update_core->updates)) {
            $update = $update_core->updates[0];
            if (isset($update->response) && $update->response === 'upgrade') {
                $update_available = true;
                $latest_version = $update->version;
            }
        }

        return array(
            'version' => $wp_version,
            'update_available' => $update_available,
            'latest_version' => $latest_version,
            'multisite' => is_multisite(),
            'site_url' => get_site_url(),
            'home_url' => get_home_url(),
            'admin_email' => get_option('admin_email'),
            'language' => get_locale(),
            'timezone' => wp_timezone_string(),
            'permalink_structure' => get_option('permalink_structure'),
            'blog_public' => get_option('blog_public'),
        );
    }

    /**
     * Informazioni plugin
     */
    public static function get_plugins_info() {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', array());
        $update_plugins = get_site_transient('update_plugins');

        $plugins = array(
            'total' => count($all_plugins),
            'active' => count($active_plugins),
            'inactive' => count($all_plugins) - count($active_plugins),
            'updates_available' => 0,
            'list' => array(),
        );

        foreach ($all_plugins as $plugin_path => $plugin_data) {
            $is_active = in_array($plugin_path, $active_plugins);
            $has_update = false;
            $new_version = '';

            if ($update_plugins && isset($update_plugins->response[$plugin_path])) {
                $has_update = true;
                $new_version = $update_plugins->response[$plugin_path]->new_version;
                $plugins['updates_available']++;
            }

            $plugins['list'][] = array(
                'name' => $plugin_data['Name'],
                'slug' => dirname($plugin_path),
                'version' => $plugin_data['Version'],
                'author' => $plugin_data['Author'],
                'active' => $is_active,
                'update_available' => $has_update,
                'new_version' => $new_version,
            );
        }

        return $plugins;
    }

    /**
     * Informazioni temi
     */
    public static function get_themes_info() {
        $all_themes = wp_get_themes();
        $active_theme = wp_get_theme();
        $update_themes = get_site_transient('update_themes');

        $themes = array(
            'total' => count($all_themes),
            'active' => array(
                'name' => $active_theme->get('Name'),
                'version' => $active_theme->get('Version'),
                'author' => $active_theme->get('Author'),
                'template' => $active_theme->get_template(),
                'stylesheet' => $active_theme->get_stylesheet(),
                'is_child' => $active_theme->parent() !== false,
            ),
            'updates_available' => 0,
            'list' => array(),
        );

        if ($active_theme->parent()) {
            $themes['active']['parent'] = $active_theme->parent()->get('Name');
        }

        foreach ($all_themes as $theme_slug => $theme) {
            $has_update = false;
            $new_version = '';

            if ($update_themes && isset($update_themes->response[$theme_slug])) {
                $has_update = true;
                $new_version = $update_themes->response[$theme_slug]['new_version'];
                $themes['updates_available']++;
            }

            $themes['list'][] = array(
                'name' => $theme->get('Name'),
                'slug' => $theme_slug,
                'version' => $theme->get('Version'),
                'active' => $theme_slug === $active_theme->get_stylesheet(),
                'update_available' => $has_update,
                'new_version' => $new_version,
            );
        }

        return $themes;
    }

    /**
     * Informazioni utenti
     */
    public static function get_users_info() {
        $users = array(
            'total' => count_users()['total_users'],
            'by_role' => count_users()['avail_roles'],
            'administrators' => array(),
        );

        // Lista amministratori (senza dati sensibili)
        $admins = get_users(array('role' => 'administrator'));
        foreach ($admins as $admin) {
            $users['administrators'][] = array(
                'id' => $admin->ID,
                'username' => $admin->user_login,
                'email' => $admin->user_email,
                'registered' => $admin->user_registered,
                'last_login' => get_user_meta($admin->ID, 'last_login', true),
            );
        }

        return $users;
    }

    /**
     * Site Health status
     */
    public static function get_site_health() {
        if (!class_exists('WP_Site_Health')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
        }

        $health = array(
            'status' => 'unknown',
            'tests' => array(),
        );

        // Ottieni risultati cached
        $health_check_site_status = get_transient('health-check-site-status-result');
        if ($health_check_site_status) {
            $decoded = json_decode($health_check_site_status, true);
            if ($decoded) {
                $health['status'] = isset($decoded['status']) ? $decoded['status'] : 'unknown';
            }
        }

        return $health;
    }

    /**
     * Costanti WordPress importanti
     */
    public static function get_wp_constants() {
        return array(
            'WP_DEBUG' => defined('WP_DEBUG') ? WP_DEBUG : false,
            'WP_DEBUG_LOG' => defined('WP_DEBUG_LOG') ? WP_DEBUG_LOG : false,
            'WP_DEBUG_DISPLAY' => defined('WP_DEBUG_DISPLAY') ? WP_DEBUG_DISPLAY : true,
            'SCRIPT_DEBUG' => defined('SCRIPT_DEBUG') ? SCRIPT_DEBUG : false,
            'WP_CACHE' => defined('WP_CACHE') ? WP_CACHE : false,
            'CONCATENATE_SCRIPTS' => defined('CONCATENATE_SCRIPTS') ? CONCATENATE_SCRIPTS : true,
            'COMPRESS_SCRIPTS' => defined('COMPRESS_SCRIPTS') ? COMPRESS_SCRIPTS : false,
            'COMPRESS_CSS' => defined('COMPRESS_CSS') ? COMPRESS_CSS : false,
            'WP_AUTO_UPDATE_CORE' => defined('WP_AUTO_UPDATE_CORE') ? WP_AUTO_UPDATE_CORE : 'minor',
            'DISALLOW_FILE_EDIT' => defined('DISALLOW_FILE_EDIT') ? DISALLOW_FILE_EDIT : false,
            'DISALLOW_FILE_MODS' => defined('DISALLOW_FILE_MODS') ? DISALLOW_FILE_MODS : false,
            'WP_MEMORY_LIMIT' => defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : '40M',
            'WP_MAX_MEMORY_LIMIT' => defined('WP_MAX_MEMORY_LIMIT') ? WP_MAX_MEMORY_LIMIT : '256M',
        );
    }
}
