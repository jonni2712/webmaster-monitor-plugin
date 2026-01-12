<?php
/**
 * Classe per raccogliere informazioni sul server
 */

if (!defined('ABSPATH')) {
    exit;
}

class WM_Monitor_Server_Info {

    /**
     * Ottieni tutte le informazioni del server
     */
    public static function get_all() {
        return array(
            'php' => self::get_php_info(),
            'database' => self::get_database_info(),
            'server' => self::get_server_info(),
            'disk' => self::get_disk_info(),
        );
    }

    /**
     * Informazioni PHP
     */
    public static function get_php_info() {
        return array(
            'version' => phpversion(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_input_vars' => ini_get('max_input_vars'),
            'display_errors' => ini_get('display_errors'),
            'sapi' => php_sapi_name(),
            'extensions' => self::get_php_extensions(),
        );
    }

    /**
     * Estensioni PHP caricate
     */
    private static function get_php_extensions() {
        $important_extensions = array(
            'curl',
            'gd',
            'imagick',
            'json',
            'mbstring',
            'mysqli',
            'openssl',
            'xml',
            'zip',
            'zlib',
            'intl',
            'soap',
            'opcache',
        );

        $loaded = array();
        foreach ($important_extensions as $ext) {
            $loaded[$ext] = extension_loaded($ext);
        }

        return $loaded;
    }

    /**
     * Informazioni Database
     */
    public static function get_database_info() {
        global $wpdb;

        $db_info = array(
            'type' => 'MySQL',
            'version' => '',
            'charset' => $wpdb->charset,
            'collate' => $wpdb->collate,
            'prefix' => $wpdb->prefix,
            'tables_count' => 0,
            'total_size' => 0,
        );

        // Versione database
        $db_version = $wpdb->get_var('SELECT VERSION()');
        if ($db_version) {
            $db_info['version'] = $db_version;
            if (stripos($db_version, 'mariadb') !== false) {
                $db_info['type'] = 'MariaDB';
            }
        }

        // Conteggio tabelle e dimensione
        $tables = $wpdb->get_results("SHOW TABLE STATUS LIKE '{$wpdb->prefix}%'");
        if ($tables) {
            $db_info['tables_count'] = count($tables);
            $total_size = 0;
            foreach ($tables as $table) {
                $total_size += $table->Data_length + $table->Index_length;
            }
            $db_info['total_size'] = self::format_bytes($total_size);
            $db_info['total_size_bytes'] = $total_size;
        }

        return $db_info;
    }

    /**
     * Informazioni Server
     */
    public static function get_server_info() {
        $server_info = array(
            'software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
            'os' => PHP_OS,
            'os_detail' => php_uname(),
            'hostname' => gethostname(),
            'ip' => isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '',
            'document_root' => isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : ABSPATH,
            'https' => is_ssl(),
        );

        // Tipo web server
        $software = strtolower($server_info['software']);
        if (strpos($software, 'apache') !== false) {
            $server_info['web_server'] = 'Apache';
        } elseif (strpos($software, 'nginx') !== false) {
            $server_info['web_server'] = 'Nginx';
        } elseif (strpos($software, 'litespeed') !== false) {
            $server_info['web_server'] = 'LiteSpeed';
        } elseif (strpos($software, 'iis') !== false) {
            $server_info['web_server'] = 'IIS';
        } else {
            $server_info['web_server'] = 'Other';
        }

        return $server_info;
    }

    /**
     * Informazioni Disco
     */
    public static function get_disk_info() {
        $path = ABSPATH;

        $disk_info = array(
            'total' => 0,
            'free' => 0,
            'used' => 0,
            'used_percentage' => 0,
        );

        if (function_exists('disk_total_space') && function_exists('disk_free_space')) {
            $total = @disk_total_space($path);
            $free = @disk_free_space($path);

            if ($total !== false && $free !== false) {
                $used = $total - $free;
                $disk_info = array(
                    'total' => self::format_bytes($total),
                    'total_bytes' => $total,
                    'free' => self::format_bytes($free),
                    'free_bytes' => $free,
                    'used' => self::format_bytes($used),
                    'used_bytes' => $used,
                    'used_percentage' => round(($used / $total) * 100, 2),
                );
            }
        }

        // Dimensione directory WordPress
        $wp_size = self::get_directory_size(ABSPATH);
        $disk_info['wordpress_size'] = self::format_bytes($wp_size);
        $disk_info['wordpress_size_bytes'] = $wp_size;

        // Dimensione uploads
        $upload_dir = wp_upload_dir();
        $uploads_size = self::get_directory_size($upload_dir['basedir']);
        $disk_info['uploads_size'] = self::format_bytes($uploads_size);
        $disk_info['uploads_size_bytes'] = $uploads_size;

        return $disk_info;
    }

    /**
     * Calcola dimensione directory
     */
    private static function get_directory_size($path) {
        $size = 0;

        if (!is_dir($path)) {
            return $size;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * Formatta bytes in formato leggibile
     */
    public static function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
