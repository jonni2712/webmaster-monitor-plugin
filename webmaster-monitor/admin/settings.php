<?php
/**
 * Pagina impostazioni admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class WM_Monitor_Settings {

    /**
     * Aggiungi menu admin
     */
    public static function add_menu() {
        add_options_page(
            __('Webmaster Monitor', 'webmaster-monitor'),
            __('Webmaster Monitor', 'webmaster-monitor'),
            'manage_options',
            'webmaster-monitor',
            array(__CLASS__, 'render_settings_page')
        );
    }

    /**
     * Registra impostazioni
     */
    public static function register_settings() {
        register_setting('wm_monitor_settings', 'wm_monitor_platform_url');

        // Handle regenerate API key
        if (isset($_POST['wm_monitor_regenerate_key']) && check_admin_referer('wm_monitor_regenerate')) {
            Webmaster_Monitor::regenerate_api_key();
            add_settings_error(
                'wm_monitor_settings',
                'api_key_regenerated',
                __('API Key rigenerata con successo!', 'webmaster-monitor'),
                'success'
            );
        }
    }

    /**
     * Renderizza pagina impostazioni
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $api_key = Webmaster_Monitor::get_api_key();
        $site_url = get_site_url();
        $rest_url = rest_url('webmaster-monitor/v1/status');

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors('wm_monitor_settings'); ?>

            <div class="wm-monitor-card" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2 style="margin-top: 0;"><?php _e('Configurazione API', 'webmaster-monitor'); ?></h2>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('API Key', 'webmaster-monitor'); ?></th>
                        <td>
                            <code id="wm-api-key" style="display: inline-block; padding: 10px; background: #f0f0f1; font-size: 13px; word-break: break-all;"><?php echo esc_html($api_key); ?></code>
                            <br><br>
                            <button type="button" class="button" onclick="copyApiKey()">
                                <?php _e('Copia API Key', 'webmaster-monitor'); ?>
                            </button>

                            <form method="post" style="display: inline-block; margin-left: 10px;">
                                <?php wp_nonce_field('wm_monitor_regenerate'); ?>
                                <input type="hidden" name="wm_monitor_regenerate_key" value="1">
                                <button type="submit" class="button" onclick="return confirm('<?php _e('Sei sicuro? La vecchia API Key smetterà di funzionare.', 'webmaster-monitor'); ?>');">
                                    <?php _e('Rigenera API Key', 'webmaster-monitor'); ?>
                                </button>
                            </form>
                            <p class="description">
                                <?php _e('Usa questa API Key per collegare il sito alla piattaforma Webmaster Monitor.', 'webmaster-monitor'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('URL Sito', 'webmaster-monitor'); ?></th>
                        <td>
                            <code style="padding: 10px; background: #f0f0f1; display: inline-block;"><?php echo esc_url($site_url); ?></code>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Endpoint API', 'webmaster-monitor'); ?></th>
                        <td>
                            <code style="padding: 10px; background: #f0f0f1; display: inline-block; word-break: break-all;"><?php echo esc_url($rest_url); ?></code>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="wm-monitor-card" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2 style="margin-top: 0;"><?php _e('Come collegare alla piattaforma', 'webmaster-monitor'); ?></h2>
                <ol>
                    <li><?php _e('Accedi alla piattaforma Webmaster Monitor', 'webmaster-monitor'); ?></li>
                    <li><?php _e('Vai su "Aggiungi Sito" e inserisci l\'URL del sito', 'webmaster-monitor'); ?></li>
                    <li><?php _e('Quando richiesto, inserisci l\'API Key mostrata sopra', 'webmaster-monitor'); ?></li>
                    <li><?php _e('Clicca su "Verifica Connessione" per testare il collegamento', 'webmaster-monitor'); ?></li>
                </ol>
            </div>

            <div class="wm-monitor-card" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2 style="margin-top: 0;"><?php _e('Test Connessione', 'webmaster-monitor'); ?></h2>
                <p><?php _e('Clicca il pulsante per verificare che l\'API funzioni correttamente:', 'webmaster-monitor'); ?></p>
                <button type="button" class="button button-primary" id="wm-test-connection">
                    <?php _e('Testa Connessione API', 'webmaster-monitor'); ?>
                </button>
                <div id="wm-test-result" style="margin-top: 15px;"></div>
            </div>

            <div class="wm-monitor-card" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h2 style="margin-top: 0;"><?php _e('Informazioni Plugin', 'webmaster-monitor'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Versione Plugin', 'webmaster-monitor'); ?></th>
                        <td><code><?php echo WM_MONITOR_VERSION; ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Versione WordPress', 'webmaster-monitor'); ?></th>
                        <td><code><?php echo get_bloginfo('version'); ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Versione PHP', 'webmaster-monitor'); ?></th>
                        <td><code><?php echo phpversion(); ?></code></td>
                    </tr>
                </table>
            </div>
        </div>

        <script>
        function copyApiKey() {
            var apiKey = document.getElementById('wm-api-key').textContent;
            navigator.clipboard.writeText(apiKey).then(function() {
                alert('<?php _e('API Key copiata negli appunti!', 'webmaster-monitor'); ?>');
            });
        }

        document.getElementById('wm-test-connection').addEventListener('click', function() {
            var resultDiv = document.getElementById('wm-test-result');
            var button = this;

            button.disabled = true;
            button.textContent = '<?php _e('Test in corso...', 'webmaster-monitor'); ?>';
            resultDiv.innerHTML = '';

            fetch('<?php echo esc_url(rest_url('webmaster-monitor/v1/ping')); ?>', {
                headers: {
                    'X-WM-API-Key': '<?php echo esc_js($api_key); ?>'
                }
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.status === 'ok') {
                    resultDiv.innerHTML = '<div style="padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;"><strong><?php _e('Successo!', 'webmaster-monitor'); ?></strong> ' + data.message + '</div>';
                } else {
                    resultDiv.innerHTML = '<div style="padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;"><strong><?php _e('Errore:', 'webmaster-monitor'); ?></strong> ' + JSON.stringify(data) + '</div>';
                }
            })
            .catch(function(error) {
                resultDiv.innerHTML = '<div style="padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;"><strong><?php _e('Errore:', 'webmaster-monitor'); ?></strong> ' + error.message + '</div>';
            })
            .finally(function() {
                button.disabled = false;
                button.textContent = '<?php _e('Testa Connessione API', 'webmaster-monitor'); ?>';
            });
        });
        </script>
        <?php
    }
}
