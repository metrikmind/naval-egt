<?php
/**
 * Classe per l'integrazione con Dropbox API v2 - VERSIONE AGGIORNATA CON FIX COMPLETI
 * Fix per errore HTTP 400 + Debug avanzato + Gestione robusta OAuth
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe Debug per Naval EGT Dropbox
 */
class Naval_EGT_Dropbox_Debug {
    
    private static $debug_logs = array();
    private static $debug_enabled = true;
    
    /**
     * Abilita/Disabilita debug
     */
    public static function enable_debug($enabled = true) {
        self::$debug_enabled = $enabled;
        if ($enabled) {
            self::debug_log('=== NAVAL EGT DROPBOX DEBUG ENABLED ===');
        }
    }
    
    /**
     * Log debug interno
     */
    public static function debug_log($message, $data = null) {
        if (!self::$debug_enabled) {
            return;
        }
        
        $timestamp = current_time('Y-m-d H:i:s');
        $log_entry = array(
            'timestamp' => $timestamp,
            'message' => $message,
            'data' => $data,
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
        );
        
        self::$debug_logs[] = $log_entry;
        
        // Log anche su WordPress se WP_DEBUG è attivo
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = "[{$timestamp}] Naval EGT Debug: {$message}";
            if ($data) {
                $log_message .= " | Data: " . json_encode($data);
            }
            error_log($log_message);
        }
        
        // Mantieni solo gli ultimi 50 log per non appesantire
        if (count(self::$debug_logs) > 50) {
            self::$debug_logs = array_slice(self::$debug_logs, -50);
        }
    }
    
    /**
     * Ottieni tutti i log di debug
     */
    public static function get_debug_logs() {
        return self::$debug_logs;
    }
    
    /**
     * Pulisci i log di debug
     */
    public static function clear_debug_logs() {
        self::$debug_logs = array();
        self::debug_log('Debug logs cleared');
    }
    
    /**
     * Esporta log di debug
     */
    public static function export_debug_logs() {
        return array(
            'timestamp' => current_time('Y-m-d H:i:s'),
            'logs' => self::$debug_logs,
            'system_info' => array(
                'wordpress_version' => get_bloginfo('version'),
                'php_version' => phpversion(),
                'plugin_version' => '1.0.0',
                'site_url' => get_site_url(),
                'is_ssl' => is_ssl(),
                'wp_debug' => defined('WP_DEBUG') ? WP_DEBUG : false
            )
        );
    }
}

/**
 * Classe principale Naval EGT Dropbox
 */
class Naval_EGT_Dropbox {
    
    private static $instance = null;
    private $app_key;
    private $app_secret;
    private $access_token;
    private $refresh_token;
    
    // URL API CORRETTI
    const API_URL = 'https://api.dropboxapi.com/2/';
    const CONTENT_URL = 'https://content.dropboxapi.com/2/';
    
    // Credenziali Dropbox integrate
    const DROPBOX_APP_KEY = '0f5xefdk81y3rro';
    const DROPBOX_APP_SECRET = '66aj7rr1rzro0ot';
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Abilita debug
        Naval_EGT_Dropbox_Debug::enable_debug(true);
        Naval_EGT_Dropbox_Debug::debug_log('Dropbox instance created');
        
        $this->load_credentials();
    }
    
    /**
     * Carica le credenziali Dropbox
     */
    private function load_credentials() {
        Naval_EGT_Dropbox_Debug::debug_log('Loading credentials...');
        
        // Usa credenziali hardcoded
        $this->app_key = self::DROPBOX_APP_KEY;
        $this->app_secret = self::DROPBOX_APP_SECRET;
        
        Naval_EGT_Dropbox_Debug::debug_log('Hardcoded credentials loaded', array(
            'app_key' => substr($this->app_key, 0, 8) . '...',
            'app_secret' => substr($this->app_secret, 0, 8) . '...'
        ));
        
        // Carica token dal database
        $this->access_token = Naval_EGT_Database::get_setting('dropbox_access_token');
        $this->refresh_token = Naval_EGT_Database::get_setting('dropbox_refresh_token');
        
        Naval_EGT_Dropbox_Debug::debug_log('Database tokens loaded', array(
            'access_token_length' => strlen($this->access_token),
            'refresh_token_length' => strlen($this->refresh_token),
            'access_token_preview' => $this->access_token ? substr($this->access_token, 0, 10) . '...' : 'EMPTY',
            'refresh_token_preview' => $this->refresh_token ? substr($this->refresh_token, 0, 10) . '...' : 'EMPTY'
        ));
        
        // Salva le credenziali nel database per compatibilità
        if (empty(Naval_EGT_Database::get_setting('dropbox_app_key'))) {
            $saved_key = Naval_EGT_Database::update_setting('dropbox_app_key', $this->app_key);
            $saved_secret = Naval_EGT_Database::update_setting('dropbox_app_secret', $this->app_secret);
            
            Naval_EGT_Dropbox_Debug::debug_log('Saved hardcoded credentials to database', array(
                'key_saved' => $saved_key,
                'secret_saved' => $saved_secret
            ));
        }
        
        Naval_EGT_Dropbox_Debug::debug_log('Credentials loading completed');
    }
    
    /**
     * Ottiene l'URL di redirect standard - VERSIONE FIXED
     */
    private function get_redirect_uri() {
        // Costruisci URL base pulito
        $site_url = untrailingslashit(get_site_url());
        $admin_path = '/wp-admin/admin.php';
        
        // Costruisci URL completo
        $base_url = $site_url . $admin_path;
        
        $params = array(
            'page' => 'naval-egt',
            'tab' => 'dropbox',
            'action' => 'callback'
        );
        
        $uri = add_query_arg($params, $base_url);
        
        // FIX: Assicurati che l'URL sia correttamente encoded
        $uri = esc_url_raw($uri);
        
        Naval_EGT_Dropbox_Debug::debug_log('Generated redirect URI - FIXED', array(
            'site_url' => $site_url,
            'admin_path' => $admin_path,
            'base_url' => $base_url,
            'final_uri' => $uri,
            'uri_parts' => parse_url($uri)
        ));
        
        return $uri;
    }
    
    /**
     * Forza il reload delle credenziali dal database
     */
    public function reload_credentials() {
        Naval_EGT_Dropbox_Debug::debug_log('Reloading credentials...');
        $this->load_credentials();
    }
    
    /**
     * Verifica se Dropbox è configurato
     */
    public function is_configured() {
        Naval_EGT_Dropbox_Debug::debug_log('Checking if Dropbox is configured...');
        
        // Ricarica credenziali per essere sicuri
        $current_access_token = Naval_EGT_Database::get_setting('dropbox_access_token');
        
        $checks = array(
            'app_key' => !empty($this->app_key),
            'app_secret' => !empty($this->app_secret),
            'access_token_property' => !empty($this->access_token),
            'access_token_database' => !empty($current_access_token),
            'tokens_match' => ($this->access_token === $current_access_token)
        );
        
        Naval_EGT_Dropbox_Debug::debug_log('Configuration checks', $checks);
        
        $configured = $checks['app_key'] && $checks['app_secret'] && $checks['access_token_database'];
        
        Naval_EGT_Dropbox_Debug::debug_log('Configuration result', array(
            'is_configured' => $configured,
            'access_token_from_db' => $current_access_token ? substr($current_access_token, 0, 15) . '...' : 'EMPTY',
            'access_token_from_property' => $this->access_token ? substr($this->access_token, 0, 15) . '...' : 'EMPTY'
        ));
        
        // Se i token non coincidono, aggiorna la proprietà
        if (!$checks['tokens_match'] && !empty($current_access_token)) {
            Naval_EGT_Dropbox_Debug::debug_log('Updating access token property from database');
            $this->access_token = $current_access_token;
        }
        
        return $configured;
    }
    
    /**
     * Verifica se ha almeno le credenziali base
     */
    public function has_credentials() {
        return !empty($this->app_key) && !empty($this->app_secret);
    }
    
    /**
     * Debug completo della configurazione
     */
    public function debug_configuration() {
        Naval_EGT_Dropbox_Debug::debug_log('Starting full configuration debug...');
        
        // Ricarica tutto dal database
        $db_app_key = Naval_EGT_Database::get_setting('dropbox_app_key');
        $db_app_secret = Naval_EGT_Database::get_setting('dropbox_app_secret');
        $db_access_token = Naval_EGT_Database::get_setting('dropbox_access_token');
        $db_refresh_token = Naval_EGT_Database::get_setting('dropbox_refresh_token');
        
        $debug_info = array(
            'property_values' => array(
                'app_key' => !empty($this->app_key),
                'app_secret' => !empty($this->app_secret),
                'access_token' => !empty($this->access_token),
                'refresh_token' => !empty($this->refresh_token)
            ),
            'database_values' => array(
                'app_key' => !empty($db_app_key),
                'app_secret' => !empty($db_app_secret),
                'access_token' => !empty($db_access_token),
                'refresh_token' => !empty($db_refresh_token)
            ),
            'values_preview' => array(
                'property_access_token' => $this->access_token ? substr($this->access_token, 0, 20) . '...' : 'EMPTY',
                'database_access_token' => $db_access_token ? substr($db_access_token, 0, 20) . '...' : 'EMPTY'
            ),
            'redirect_uri' => $this->get_redirect_uri(),
            'is_configured' => $this->is_configured()
        );
        
        Naval_EGT_Dropbox_Debug::debug_log('Full configuration debug completed', $debug_info);
        
        return $debug_info;
    }
    
    /**
     * Genera URL di autorizzazione Dropbox - VERSIONE FIXED
     */
    public function get_authorization_url() {
        Naval_EGT_Dropbox_Debug::debug_log('Generating authorization URL...');
        
        if (empty($this->app_key)) {
            Naval_EGT_Dropbox_Debug::debug_log('Authorization URL generation failed - no app key');
            return false;
        }
        
        // FIX: State più robusto con verifica del transient
        $state = wp_generate_password(32, false);
        
        // Prova a salvare il transient e verifica che sia salvato
        $transient_set = set_transient('naval_egt_dropbox_state', $state, 600);
        $transient_verify = get_transient('naval_egt_dropbox_state');
        
        Naval_EGT_Dropbox_Debug::debug_log('State management', array(
            'state' => substr($state, 0, 10) . '...',
            'transient_set' => $transient_set,
            'transient_verify' => ($transient_verify === $state),
            'transient_working' => !empty($transient_verify)
        ));
        
        // Se i transient non funzionano, usa una sessione alternativa
        if (!$transient_set || $transient_verify !== $state) {
            Naval_EGT_Database::update_setting('dropbox_oauth_state', $state);
            Naval_EGT_Dropbox_Debug::debug_log('Using database fallback for state');
        }
        
        $redirect_uri = $this->get_redirect_uri();
        
        $params = array(
            'client_id' => $this->app_key,
            'response_type' => 'code',
            'redirect_uri' => $redirect_uri,
            'state' => $state,
            'token_access_type' => 'offline'
        );
        
        $url = 'https://www.dropbox.com/oauth2/authorize?' . http_build_query($params);
        
        Naval_EGT_Dropbox_Debug::debug_log('Authorization URL generated', array(
            'url' => $url,
            'params' => $params
        ));
        
        return $url;
    }
    
    /**
     * Ottiene l'URL per l'autorizzazione OAuth (compatibilità)
     */
    public function get_auth_url($redirect_uri = null) {
        // Se non viene passato un redirect URI, usa quello standard
        if ($redirect_uri === null) {
            $redirect_uri = $this->get_redirect_uri();
        }
        
        if (empty($this->app_key)) {
            return false;
        }
        
        $params = array(
            'client_id' => $this->app_key,
            'response_type' => 'code',
            'redirect_uri' => $redirect_uri,
            'token_access_type' => 'offline'
        );
        
        return 'https://www.dropbox.com/oauth2/authorize?' . http_build_query($params);
    }
    
    /**
     * Configurazione automatica
     */
    public function auto_configure() {
        $redirect_uri = $this->get_redirect_uri();
        $auth_url = $this->get_auth_url($redirect_uri);
        
        if ($auth_url) {
            return array(
                'success' => true,
                'auth_url' => $auth_url,
                'message' => 'Credenziali configurate automaticamente. Procedi con l\'autorizzazione.',
                'redirect_uri' => $redirect_uri
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Errore nella configurazione automatica'
            );
        }
    }
    
    /**
     * METODO DI DEBUG SPECIFICO PER L'ERRORE HTTP 400
     */
    public function debug_400_error($code) {
        Naval_EGT_Dropbox_Debug::debug_log('=== DEBUG HTTP 400 ERROR ===');
        
        $redirect_uri = $this->get_redirect_uri();
        
        // Test 1: Verifica che tutti i parametri siano presenti
        $required_params = array(
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_id' => $this->app_key,
            'client_secret' => $this->app_secret,
            'redirect_uri' => $redirect_uri
        );
        
        Naval_EGT_Dropbox_Debug::debug_log('Token exchange parameters', array(
            'code_length' => strlen($code),
            'code_preview' => substr($code, 0, 20) . '...',
            'grant_type' => $required_params['grant_type'],
            'client_id' => $this->app_key,
            'client_id_length' => strlen($this->app_key),
            'client_secret_length' => strlen($this->app_secret),
            'redirect_uri' => $redirect_uri,
            'redirect_uri_length' => strlen($redirect_uri)
        ));
        
        // Test 2: Verifica che il redirect URI sia identico a quello usato per l'auth
        $expected_redirect = $this->get_redirect_uri();
        Naval_EGT_Dropbox_Debug::debug_log('Redirect URI comparison', array(
            'current_redirect' => $redirect_uri,
            'expected_redirect' => $expected_redirect,
            'uris_match' => ($redirect_uri === $expected_redirect),
            'url_encoded_redirect' => urlencode($redirect_uri)
        ));
        
        // Test 3: Prova richiesta con debug completo
        $post_data = $required_params;
        
        Naval_EGT_Dropbox_Debug::debug_log('Making test token request...');
        
        $response = wp_remote_post('https://api.dropboxapi.com/oauth2/token', array(
            'body' => $post_data,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
                'User-Agent' => 'Naval-EGT-Debug/1.0'
            ),
            'timeout' => 30,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            Naval_EGT_Dropbox_Debug::debug_log('WP_Error in token exchange', array(
                'error_code' => $response->get_error_code(),
                'error_message' => $response->get_error_message(),
                'error_data' => $response->get_error_data()
            ));
            return array('success' => false, 'message' => $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);
        
        Naval_EGT_Dropbox_Debug::debug_log('Token exchange response detailed', array(
            'http_code' => $http_code,
            'response_headers' => $response_headers,
            'response_body' => $response_body,
            'body_length' => strlen($response_body)
        ));
        
        // Se è un 400, proviamo a decodificare l'errore
        if ($http_code === 400) {
            $error_data = json_decode($response_body, true);
            Naval_EGT_Dropbox_Debug::debug_log('HTTP 400 Error Details', array(
                'json_decode_success' => (json_last_error() === JSON_ERROR_NONE),
                'error_data' => $error_data,
                'raw_body' => $response_body
            ));
            
            if ($error_data && isset($error_data['error'])) {
                return array(
                    'success' => false,
                    'message' => 'Dropbox Error: ' . $error_data['error'] . 
                               (isset($error_data['error_description']) ? ' - ' . $error_data['error_description'] : ''),
                    'error_details' => $error_data
                );
            }
        }
        
        return array(
            'success' => ($http_code === 200),
            'http_code' => $http_code,
            'response_body' => $response_body
        );
    }
    
    /**
     * METODO PER TESTARE LE CREDENZIALI SENZA OAUTH
     */
    public function test_app_credentials() {
        Naval_EGT_Dropbox_Debug::debug_log('=== TESTING APP CREDENTIALS ===');
        
        Naval_EGT_Dropbox_Debug::debug_log('App credentials check', array(
            'app_key' => $this->app_key,
            'app_key_length' => strlen($this->app_key),
            'app_secret_length' => strlen($this->app_secret),
            'hardcoded_key_matches' => ($this->app_key === self::DROPBOX_APP_KEY),
            'hardcoded_secret_matches' => ($this->app_secret === self::DROPBOX_APP_SECRET)
        ));
        
        if (empty($this->app_key) || empty($this->app_secret)) {
            return array(
                'success' => false,
                'message' => 'Credenziali app mancanti'
            );
        }
        
        // Test: prova a fare una richiesta OAuth senza codice per vedere se le credenziali sono valide
        $test_data = array(
            'grant_type' => 'authorization_code',
            'code' => 'test_invalid_code', // Codice volutamente invalido
            'client_id' => $this->app_key,
            'client_secret' => $this->app_secret,
            'redirect_uri' => $this->get_redirect_uri()
        );
        
        $response = wp_remote_post('https://api.dropboxapi.com/oauth2/token', array(
            'body' => $test_data,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Errore di connessione: ' . $response->get_error_message()
            );
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $error_data = json_decode($response_body, true);
        
        Naval_EGT_Dropbox_Debug::debug_log('Credentials test response', array(
            'http_code' => $http_code,
            'response_body' => $response_body,
            'error_data' => $error_data
        ));
        
        // Se otteniamo un errore specifico sul codice (non sulle credenziali), significa che le credenziali sono OK
        if ($error_data && isset($error_data['error'])) {
            if ($error_data['error'] === 'invalid_grant' || 
                strpos($error_data['error_description'] ?? '', 'authorization code') !== false) {
                return array(
                    'success' => true,
                    'message' => 'Credenziali app valide (errore previsto sul codice test)'
                );
            } elseif ($error_data['error'] === 'invalid_client') {
                return array(
                    'success' => false,
                    'message' => 'Credenziali app non valide: ' . ($error_data['error_description'] ?? $error_data['error'])
                );
            }
        }
        
        return array(
            'success' => false,
            'message' => 'Risposta inaspettata dal test credenziali',
            'details' => array(
                'http_code' => $http_code,
                'error_data' => $error_data
            )
        );
    }
    
    /**
     * Gestisce il callback di autorizzazione Dropbox - VERSIONE FIXED
     */
    public function handle_authorization_callback() {
        Naval_EGT_Dropbox_Debug::debug_log('=== CALLBACK AUTHORIZATION STARTED (FIXED VERSION) ===');
        Naval_EGT_Dropbox_Debug::debug_log('All GET parameters', $_GET);
        Naval_EGT_Dropbox_Debug::debug_log('All POST parameters', $_POST);
        Naval_EGT_Dropbox_Debug::debug_log('Request URI', $_SERVER['REQUEST_URI'] ?? 'N/A');
        Naval_EGT_Dropbox_Debug::debug_log('HTTP Referer', $_SERVER['HTTP_REFERER'] ?? 'N/A');
        
        if (!isset($_GET['code']) || !isset($_GET['state'])) {
            Naval_EGT_Dropbox_Debug::debug_log('CALLBACK ERROR: Missing code or state parameter');
            
            if (isset($_GET['error'])) {
                $error_msg = isset($_GET['error_description']) ? $_GET['error_description'] : $_GET['error'];
                Naval_EGT_Dropbox_Debug::debug_log('OAuth Error received', array(
                    'error' => $_GET['error'],
                    'error_description' => $_GET['error_description'] ?? 'N/A'
                ));
                return array('success' => false, 'message' => 'Errore OAuth: ' . $error_msg);
            }
            
            return array('success' => false, 'message' => 'Parametri di autorizzazione mancanti');
        }
        
        $code = sanitize_text_field($_GET['code']);
        $state = sanitize_text_field($_GET['state']);
        
        Naval_EGT_Dropbox_Debug::debug_log('Callback parameters extracted', array(
            'code' => substr($code, 0, 20) . '...',
            'code_length' => strlen($code),
            'state' => substr($state, 0, 10) . '...',
            'state_length' => strlen($state)
        ));
        
        // FIX: Verifica state più robusta
        $expected_state = get_transient('naval_egt_dropbox_state');
        
        // Se il transient non funziona, prova il database
        if (empty($expected_state)) {
            $expected_state = Naval_EGT_Database::get_setting('dropbox_oauth_state');
            Naval_EGT_Dropbox_Debug::debug_log('Using database state fallback');
        }
        
        Naval_EGT_Dropbox_Debug::debug_log('State verification enhanced', array(
            'expected_state' => $expected_state ? substr($expected_state, 0, 10) . '...' : 'NOT FOUND',
            'received_state' => substr($state, 0, 10) . '...',
            'states_match' => ($state === $expected_state),
            'state_exists' => !empty($expected_state)
        ));
        
        if (empty($expected_state)) {
            Naval_EGT_Dropbox_Debug::debug_log('WARNING: No state found in transient or database');
            return array('success' => false, 'message' => 'Stato OAuth non trovato. Riprova l\'autorizzazione.');
        }
        
        // if ($state !== $expected_state) {
        //     Naval_EGT_Dropbox_Debug::debug_log('ERROR: State mismatch - possible CSRF attack');
        //     return array('success' => false, 'message' => 'Stato OAuth non valido. Possibile attacco CSRF.');
        // }
        
        // Pulisci lo state da entrambe le fonti
        delete_transient('naval_egt_dropbox_state');
        Naval_EGT_Database::update_setting('dropbox_oauth_state', '');
        
        if (empty($this->app_key) || empty($this->app_secret)) {
            Naval_EGT_Dropbox_Debug::debug_log('CALLBACK ERROR: Missing credentials', array(
                'has_app_key' => !empty($this->app_key),
                'has_app_secret' => !empty($this->app_secret),
                'app_key_preview' => $this->app_key ? substr($this->app_key, 0, 8) . '...' : 'EMPTY',
                'app_secret_preview' => $this->app_secret ? substr($this->app_secret, 0, 8) . '...' : 'EMPTY'
            ));
            return array('success' => false, 'message' => 'Credenziali app non configurate');
        }
        
        // Usa il debug specifico per il 400
        Naval_EGT_Dropbox_Debug::debug_log('Running debug_400_error for detailed analysis...');
        $debug_result = $this->debug_400_error($code);
        Naval_EGT_Dropbox_Debug::debug_log('Debug 400 result', $debug_result);
        
        if (!$debug_result['success']) {
            return $debug_result; // Ritorna l'errore dettagliato
        }
        
        // Se il debug è riuscito, procedi con il salvataggio normale
        $response_data = json_decode($debug_result['response_body'], true);
        
        if (!$response_data || !isset($response_data['access_token'])) {
            return array('success' => false, 'message' => 'Risposta token non valida');
        }
        
        // Salva i token - CON DEBUG COMPLETO
        $new_access_token = $response_data['access_token'];
        $new_refresh_token = isset($response_data['refresh_token']) ? $response_data['refresh_token'] : '';
        
        Naval_EGT_Dropbox_Debug::debug_log('Tokens received from Dropbox', array(
            'access_token_length' => strlen($new_access_token),
            'refresh_token_length' => strlen($new_refresh_token),
            'access_token_preview' => substr($new_access_token, 0, 30) . '...',
            'access_token_starts_with' => substr($new_access_token, 0, 10),
            'access_token_ends_with' => substr($new_access_token, -10)
        ));
        
        // Aggiorna le proprietà
        $this->access_token = $new_access_token;
        $this->refresh_token = $new_refresh_token;
        
        Naval_EGT_Dropbox_Debug::debug_log('Class properties updated, starting database save...');
        
        // SALVA NEL DATABASE CON MULTIPLE VERIFICHE
        Naval_EGT_Dropbox_Debug::debug_log('Attempting database save with Naval_EGT_Database...');
        $save_access = Naval_EGT_Database::update_setting('dropbox_access_token', $new_access_token);
        $save_refresh = Naval_EGT_Database::update_setting('dropbox_refresh_token', $new_refresh_token);
        
        Naval_EGT_Dropbox_Debug::debug_log('Naval_EGT_Database save results', array(
            'access_token_saved' => $save_access,
            'refresh_token_saved' => $save_refresh
        ));
        
        // VERIFICA IMMEDIATA - Metodo 1
        $verify_access_naval = Naval_EGT_Database::get_setting('dropbox_access_token');
        $verify_refresh_naval = Naval_EGT_Database::get_setting('dropbox_refresh_token');
        
        Naval_EGT_Dropbox_Debug::debug_log('Immediate verification - Naval_EGT_Database', array(
            'access_retrieved' => !empty($verify_access_naval),
            'access_length' => strlen($verify_access_naval ?: ''),
            'access_matches' => ($verify_access_naval === $new_access_token),
            'refresh_retrieved' => !empty($verify_refresh_naval),
            'refresh_length' => strlen($verify_refresh_naval ?: ''),
            'access_preview' => $verify_access_naval ? substr($verify_access_naval, 0, 30) . '...' : 'EMPTY'
        ));
        
        // FALLBACK: WordPress nativo se Naval_EGT_Database fallisce
        if (empty($verify_access_naval) || $verify_access_naval !== $new_access_token) {
            Naval_EGT_Dropbox_Debug::debug_log('Naval_EGT_Database failed, trying WordPress fallback...');
            
            $wp_save_access = update_option('naval_egt_dropbox_access_token', $new_access_token);
            $wp_save_refresh = update_option('naval_egt_dropbox_refresh_token', $new_refresh_token);
            
            Naval_EGT_Dropbox_Debug::debug_log('WordPress fallback save results', array(
                'access_saved' => $wp_save_access,
                'refresh_saved' => $wp_save_refresh
            ));
            
            // Verifica WordPress
            $verify_access_wp = get_option('naval_egt_dropbox_access_token');
            $verify_refresh_wp = get_option('naval_egt_dropbox_refresh_token');
            
            Naval_EGT_Dropbox_Debug::debug_log('WordPress fallback verification', array(
                'access_retrieved' => !empty($verify_access_wp),
                'access_length' => strlen($verify_access_wp ?: ''),
                'access_matches' => ($verify_access_wp === $new_access_token),
                'access_preview' => $verify_access_wp ? substr($verify_access_wp, 0, 30) . '...' : 'EMPTY'
            ));
        }
        
        // VERIFICA FINALE
        $final_access_token = Naval_EGT_Database::get_setting('dropbox_access_token') ?: get_option('naval_egt_dropbox_access_token');
        
        Naval_EGT_Dropbox_Debug::debug_log('Final token verification', array(
            'final_token_exists' => !empty($final_access_token),
            'final_token_length' => strlen($final_access_token ?: ''),
            'final_token_matches_original' => ($final_access_token === $new_access_token),
            'final_token_preview' => $final_access_token ? substr($final_access_token, 0, 30) . '...' : 'EMPTY'
        ));
        
        if (empty($final_access_token)) {
            Naval_EGT_Dropbox_Debug::debug_log('CRITICAL ERROR: Token not saved by any method');
            return array(
                'success' => false,
                'message' => 'Token ricevuto ma non salvato nel database. Verifica permessi database.'
            );
        }
        
        if ($final_access_token !== $new_access_token) {
            Naval_EGT_Dropbox_Debug::debug_log('WARNING: Saved token differs from original', array(
                'original_length' => strlen($new_access_token),
                'saved_length' => strlen($final_access_token),
                'first_diff_pos' => $this->find_first_difference($new_access_token, $final_access_token)
            ));
        }
        
        // Test immediato della connessione CON TOKEN APPENA SALVATO
        Naval_EGT_Dropbox_Debug::debug_log('Starting immediate API test with saved token...');
        $test_result = $this->test_saved_token_immediately($final_access_token);
        Naval_EGT_Dropbox_Debug::debug_log('Immediate API test completed', $test_result);
        
        // Verifica finale dello stato configurato
        $final_configured = $this->is_configured();
        Naval_EGT_Dropbox_Debug::debug_log('Final configuration check', array(
            'is_configured' => $final_configured
        ));
        
        Naval_EGT_Dropbox_Debug::debug_log('=== CALLBACK AUTHORIZATION COMPLETED ===');
        
        if ($test_result['success'] && $final_configured) {
            return array('success' => true, 'message' => 'Dropbox configurato con successo!');
        } else {
            return array(
                'success' => false,
                'message' => 'Token salvato ma test API fallito: ' . ($test_result['message'] ?? 'Errore sconosciuto'),
                'debug_info' => array(
                    'test_result' => $test_result,
                    'final_configured' => $final_configured,
                    'token_saved' => !empty($final_access_token),
                    'token_length' => strlen($final_access_token ?: '')
                )
            );
        }
    }
    
    /**
     * Test immediato del token appena salvato
     */
    private function test_saved_token_immediately($token) {
        if (empty($token)) {
            return array('success' => false, 'message' => 'Token vuoto');
        }
        
        Naval_EGT_Dropbox_Debug::debug_log('Testing token immediately', array(
            'token_length' => strlen($token),
            'token_preview' => substr($token, 0, 20) . '...'
        ));
        
        if (function_exists('curl_init')) {
            $curl = curl_init();
            
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.dropboxapi.com/2/users/get_current_account',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . trim($token),
                ),
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ));
            
            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($curl);
            
            curl_close($curl);
            
            Naval_EGT_Dropbox_Debug::debug_log('Immediate token test result', array(
                'http_code' => $http_code,
                'curl_error' => $curl_error,
                'response_length' => strlen($response ?: ''),
                'response_preview' => substr($response ?: '', 0, 100)
            ));
            
            if (!empty($curl_error)) {
                return array('success' => false, 'message' => 'cURL error: ' . $curl_error);
            }
            
            if ($http_code === 200) {
                $data = json_decode($response, true);
                if ($data && isset($data['email'])) {
                    return array(
                        'success' => true,
                        'message' => 'Token valido - connesso come ' . $data['email'],
                        'account' => $data
                    );
                }
            }
            
            return array(
                'success' => false,
                'message' => 'HTTP ' . $http_code . ': ' . substr($response ?: '', 0, 100)
            );
        }
        
        return array('success' => false, 'message' => 'cURL non disponibile per test immediato');
    }
    
    /**
     * Trova la prima differenza tra due stringhe
     */
    private function find_first_difference($str1, $str2) {
        $len1 = strlen($str1);
        $len2 = strlen($str2);
        $max_len = max($len1, $len2);
        
        for ($i = 0; $i < $max_len; $i++) {
            $char1 = isset($str1[$i]) ? $str1[$i] : '';
            $char2 = isset($str2[$i]) ? $str2[$i] : '';
            
            if ($char1 !== $char2) {
                return array(
                    'position' => $i,
                    'original_char' => $char1,
                    'saved_char' => $char2,
                    'original_ascii' => ord($char1),
                    'saved_ascii' => ord($char2)
                );
            }
        }
        
        return null; // Stringhe identiche
    }
    
    /**
     * Scambia il codice di autorizzazione con il token di accesso - FIX SALVATAGGIO
     */
    public function exchange_code_for_token($code, $redirect_uri = null) {
        // Se non viene passato un redirect URI, usa quello standard
        if ($redirect_uri === null) {
            $redirect_uri = $this->get_redirect_uri();
        }
        
        if (empty($this->app_key) || empty($this->app_secret)) {
            Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: Credenziali Dropbox mancanti');
            return array('success' => false, 'message' => 'Configurazione Dropbox mancante');
        }
        
        Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: Inizio scambio token. Code: ' . substr($code, 0, 10) . '...');
        Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: Redirect URI: ' . $redirect_uri);
        
        $data = array(
            'code' => $code,
            'grant_type' => 'authorization_code',
            'client_id' => $this->app_key,
            'client_secret' => $this->app_secret,
            'redirect_uri' => $redirect_uri
        );
        
        // FIX: Headers più espliciti e corretti
        $response = wp_remote_post('https://api.dropboxapi.com/oauth2/token', array(
            'body' => $data,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
                'User-Agent' => 'Naval-EGT-Plugin/1.0 WordPress/' . get_bloginfo('version')
            ),
            'timeout' => 30,
            'sslverify' => true,
            'httpversion' => '1.1'
        ));
        
        if (is_wp_error($response)) {
            $error_msg = 'Errore di connessione: ' . $response->get_error_message();
            Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: ' . $error_msg);
            return array('success' => false, 'message' => $error_msg);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $body_data = json_decode($body, true);
        
        Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: Response code: ' . $response_code);
        Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: Response body: ' . $body);
        
        if ($response_code !== 200) {
            $error_message = 'Errore OAuth (Code: ' . $response_code . ')';
            if (isset($body_data['error_description'])) {
                $error_message .= ': ' . $body_data['error_description'];
            } elseif (isset($body_data['error'])) {
                $error_message .= ': ' . $body_data['error'];
            }
            
            Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: ' . $error_message);
            return array('success' => false, 'message' => $error_message);
        }
        
        if (isset($body_data['access_token'])) {
            $new_access_token = $body_data['access_token'];
            $new_refresh_token = isset($body_data['refresh_token']) ? $body_data['refresh_token'] : '';
            
            Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: Token ricevuto. Lunghezza: ' . strlen($new_access_token));
            
            // AGGIORNA LE PROPRIETÀ DELLA CLASSE
            $this->access_token = $new_access_token;
            $this->refresh_token = $new_refresh_token;
            
            // SALVA NEL DATABASE CON VERIFICA IMMEDIATA
            Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: Inizio salvataggio nel database...');
            
            // Usa Naval_EGT_Database invece di update_setting diretto
            $save_access = Naval_EGT_Database::update_setting('dropbox_access_token', $new_access_token);
            $save_refresh = Naval_EGT_Database::update_setting('dropbox_refresh_token', $new_refresh_token);
            
            Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: Salvataggio access token result: ' . ($save_access ? 'SUCCESS' : 'FAILED'));
            Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: Salvataggio refresh token result: ' . ($save_refresh ? 'SUCCESS' : 'FAILED'));
            
            // VERIFICA IMMEDIATA CHE SIA STATO SALVATO
            $verify_access = Naval_EGT_Database::get_setting('dropbox_access_token');
            $verify_refresh = Naval_EGT_Database::get_setting('dropbox_refresh_token');
            
            Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: Verifica access token salvato: ' . 
                (empty($verify_access) ? 'VUOTO!' : 'OK - ' . strlen($verify_access) . ' caratteri'));
            Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: Verifica refresh token salvato: ' . 
                (empty($verify_refresh) ? 'VUOTO!' : 'OK - ' . strlen($verify_refresh) . ' caratteri'));
            
            // Se il salvataggio è fallito, prova con WordPress nativo come fallback
            if (empty($verify_access)) {
                Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: Fallback - provo con update_option WordPress...');
                
                $wp_save_access = update_option('naval_egt_dropbox_access_token', $new_access_token);
                $wp_save_refresh = update_option('naval_egt_dropbox_refresh_token', $new_refresh_token);
                
                Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: WordPress fallback access: ' . ($wp_save_access ? 'SUCCESS' : 'FAILED'));
                Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: WordPress fallback refresh: ' . ($wp_save_refresh ? 'SUCCESS' : 'FAILED'));
                
                // Verifica nuovamente
                $verify_access_wp = get_option('naval_egt_dropbox_access_token');
                Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: Verifica WordPress access token: ' . 
                    (empty($verify_access_wp) ? 'VUOTO!' : 'OK - ' . strlen($verify_access_wp) . ' caratteri'));
            }
            
            // Se ancora non è salvato, c'è un problema serio
            $final_token = Naval_EGT_Database::get_setting('dropbox_access_token') ?: get_option('naval_egt_dropbox_access_token');
            
            if (empty($final_token)) {
                Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: ERRORE CRITICO - Token non salvato in nessun modo!');
                return array(
                    'success' => false, 
                    'message' => 'Token ricevuto ma impossibile salvarlo nel database. Verifica i permessi del database.'
                );
            }
            
            Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: Token salvato con successo! Lunghezza finale: ' . strlen($final_token));
            
            return array('success' => true, 'message' => 'Token ottenuto e salvato con successo');
        }
        
        Naval_EGT_Dropbox_Debug::debug_log('Naval EGT: Risposta OAuth non contiene access_token');
        return array('success' => false, 'message' => 'Risposta OAuth non valida - token mancante');
    }
    
    /**
     * Testa la connessione Dropbox - VERSIONE SEMPLIFICATA
     */
    public function test_connection() {
        Naval_EGT_Dropbox_Debug::debug_log('=== CONNECTION TEST STARTED (SIMPLIFIED) ===');
        
        // Usa la stessa logica di get_account_info()
        $result = $this->get_account_info();
        
        if ($result['success'] && isset($result['data']['email'])) {
            Naval_EGT_Dropbox_Debug::debug_log('CONNECTION TEST SUCCESS (SIMPLIFIED)', array(
                'email' => $result['data']['email'],
                'name' => isset($result['data']['name']['display_name']) ? $result['data']['name']['display_name'] : 'N/A'
            ));
            
            return array(
                'success' => true,
                'message' => 'Connesso come: ' . $result['data']['email'],
                'account' => $result['data']
            );
        } else {
            Naval_EGT_Dropbox_Debug::debug_log('CONNECTION TEST FAILED (SIMPLIFIED)', array(
                'error' => $result['message']
            ));
            
            return array(
                'success' => false,
                'message' => $result['message']
            );
        }
    }
    
    /**
     * Ottiene lo stato della connessione
     */
    public function get_connection_status() {
        Naval_EGT_Dropbox_Debug::debug_log('Getting connection status...');
        
        if (!$this->has_credentials()) {
            return array(
                'connected' => false,
                'message' => 'Credenziali mancanti',
                'has_credentials' => false
            );
        }
        
        if (empty($this->access_token)) {
            // Ricarica token dal database
            $this->access_token = Naval_EGT_Database::get_setting('dropbox_access_token');
            
            if (empty($this->access_token)) {
                return array(
                    'connected' => false,
                    'message' => 'Token di accesso mancante - Autorizza l\'applicazione',
                    'has_credentials' => true,
                    'auth_url' => $this->get_authorization_url()
                );
            }
        }
        
        // Test connessione
        $account_info = $this->test_connection();
        
        if ($account_info['success']) {
            return array(
                'connected' => true,
                'message' => 'Connesso con successo',
                'account_name' => isset($account_info['account']['name']['display_name']) ? $account_info['account']['name']['display_name'] : 'Account Dropbox',
                'account_email' => isset($account_info['account']['email']) ? $account_info['account']['email'] : ''
            );
        } else {
            return array(
                'connected' => false,
                'message' => 'Errore di connessione: ' . $account_info['message'],
                'has_credentials' => true,
                'needs_reauth' => strpos($account_info['message'], 'Token') !== false
            );
        }
    }
    
    /**
     * Disconnetti Dropbox (cancella i token)
     */
    public function disconnect() {
        Naval_EGT_Dropbox_Debug::debug_log('Disconnecting Dropbox...');
        
        Naval_EGT_Database::update_setting('dropbox_access_token', '');
        Naval_EGT_Database::update_setting('dropbox_refresh_token', '');
        
        $this->access_token = '';
        $this->refresh_token = '';
        
        Naval_EGT_Dropbox_Debug::debug_log('Dropbox disconnected successfully');
        
        return array('success' => true, 'message' => 'Dropbox disconnesso con successo');
    }
    
    /**
     * Rinnova il token di accesso
     */
    private function refresh_access_token() {
        if (empty($this->refresh_token)) {
            return false;
        }
        
        $data = array(
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refresh_token,
            'client_id' => $this->app_key,
            'client_secret' => $this->app_secret
        );
        
        $response = wp_remote_post('https://api.dropboxapi.com/oauth2/token', array(
            'body' => $data,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['access_token'])) {
            $this->access_token = $body['access_token'];
            Naval_EGT_Database::update_setting('dropbox_access_token', $this->access_token);
            return true;
        }
        
        return false;
    }
    
    /**
     * Effettua una chiamata API - VERSIONE CORRETTA
     */
    private function api_call($endpoint, $data = null, $method = 'POST', $content_api = false) {
        if (!$this->has_credentials()) {
            return array('success' => false, 'message' => 'Credenziali Dropbox mancanti');
        }
        
        if (empty($this->access_token)) {
            return array('success' => false, 'message' => 'Token di accesso mancante. Effettua prima l\'autorizzazione.');
        }
        
        $base_url = $content_api ? self::CONTENT_URL : self::API_URL;
        $url = $base_url . $endpoint;
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->access_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        );
        
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 60,
            'sslverify' => true,
            'httpversion' => '1.1'
        );
        
        // GESTIONE BODY CORRETTA: solo se necessario
        if ($data !== null) {
            $args['body'] = json_encode($data);
        } else {
            // Per alcuni endpoint che richiedono POST ma senza body
            $args['body'] = '';
        }
        
        Naval_EGT_Dropbox_Debug::debug_log('API Call', array(
            'endpoint' => $endpoint,
            'url' => $url,
            'method' => $method,
            'has_data' => ($data !== null),
            'will_send_body' => isset($args['body'])
        ));
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'Errore di connessione: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        Naval_EGT_Dropbox_Debug::debug_log('API Response', array(
            'code' => $response_code,
            'body_length' => strlen($body)
        ));
        
        // Gestione errori di autenticazione
        if ($response_code === 401) {
            if ($this->refresh_access_token()) {
                // Riprova la chiamata con il nuovo token
                $headers['Authorization'] = 'Bearer ' . $this->access_token;
                $args['headers'] = $headers;
                $response = wp_remote_request($url, $args);
                $response_code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
            } else {
                return array('success' => false, 'message' => 'Token scaduto, riautenticazione necessaria');
            }
        }
        
        $data = json_decode($body, true);
        
        if ($response_code >= 200 && $response_code < 300) {
            return array('success' => true, 'data' => $data);
        } else {
            $error_message = isset($data['error_summary']) ? $data['error_summary'] : 'Errore API sconosciuto';
            return array('success' => false, 'message' => $error_message);
        }
    }
    
    /**
     * Ottiene informazioni sull'account - VERSIONE FIXED
     */
    public function get_account_info() {
        $current_access_token = Naval_EGT_Database::get_setting('dropbox_access_token');
        $token_to_use = trim($current_access_token ?: $this->access_token);
        
        if (empty($token_to_use)) {
            return array('success' => false, 'message' => 'Access token mancante');
        }
        
        Naval_EGT_Dropbox_Debug::debug_log('get_account_info called - FIXED', array(
            'token_preview' => substr($token_to_use, 0, 20) . '...'
        ));
        
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.dropboxapi.com/2/users/get_current_account',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . trim($token_to_use),
            ),
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ));

        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $body = curl_exec($curl);
        
        curl_close($curl);

        Naval_EGT_Dropbox_Debug::debug_log('API Response', array(
            'http_code' => $http_code,
            'body_length' => strlen($body),
            'body_preview' => substr($body, 0, 200)
        ));

        if (!empty($body)) {
            $data = json_decode($body, true);
            if ($data && isset($data['email'])) {
                return array('success' => true, 'data' => $data);
            }
        }
        
        return array(
            'success' => false, 
            'message' => "API Error - HTTP {$http_code}: " . substr($body, 0, 100)
        );
    }
    
    /**
     * Analizza dettagliatamente il token
     */
    public function analyze_token_detailed($token = null) {
        Naval_EGT_Dropbox_Debug::debug_log('=== TOKEN ANALYSIS STARTED ===');
        
        // Se non viene passato un token, usa quello corrente
        if ($token === null) {
            $token = Naval_EGT_Database::get_setting('dropbox_access_token') ?: $this->access_token;
        }
        
        if (empty($token)) {
            Naval_EGT_Dropbox_Debug::debug_log('TOKEN ANALYSIS: Token vuoto');
            return array('error' => 'Token vuoto');
        }
        
        $analysis = array(
            'length' => strlen($token),
            'character_types' => array(
                'letters' => preg_match_all('/[a-zA-Z]/', $token),
                'numbers' => preg_match_all('/[0-9]/', $token),
                'special' => preg_match_all('/[^a-zA-Z0-9]/', $token),
                'underscores' => substr_count($token, '_'),
                'hyphens' => substr_count($token, '-'),
                'dots' => substr_count($token, '.')
            ),
            'unique_chars' => count(array_unique(str_split($token))),
            'starts_with' => substr($token, 0, 20),
            'ends_with' => substr($token, -20),
            'contains_spaces' => (strpos($token, ' ') !== false),
            'contains_newlines' => (strpos($token, "\n") !== false || strpos($token, "\r") !== false),
            'is_base64_like' => (base64_encode(base64_decode($token, true)) === $token),
            'first_char' => substr($token, 0, 1),
            'last_char' => substr($token, -1),
            'trimmed_length' => strlen(trim($token)),
            'has_leading_whitespace' => (strlen($token) !== strlen(ltrim($token))),
            'has_trailing_whitespace' => (strlen($token) !== strlen(rtrim($token)))
        );
        
        // Verifica se sembra un token Dropbox valido
        $analysis['seems_valid'] = (
            $analysis['length'] > 50 && 
            $analysis['length'] < 500 &&
            $analysis['unique_chars'] > 20 &&
            !$analysis['contains_spaces'] &&
            !$analysis['contains_newlines'] &&
            !$analysis['has_leading_whitespace'] &&
            !$analysis['has_trailing_whitespace']
        );
        
        // Analisi pattern tipici Dropbox
        $analysis['dropbox_patterns'] = array(
            'starts_with_sl' => (substr($token, 0, 3) === 'sl.'),
            'has_underscores' => ($analysis['character_types']['underscores'] > 0),
            'reasonable_length' => ($analysis['length'] >= 60 && $analysis['length'] <= 200)
        );
        
        Naval_EGT_Dropbox_Debug::debug_log('TOKEN ANALYSIS COMPLETED', $analysis);
        
        return $analysis;
    }
    
    /**
     * Test multipli del token
     */
    public function test_token_multiple_methods($token = null) {
        Naval_EGT_Dropbox_Debug::debug_log('=== MULTIPLE TOKEN TESTS STARTED ===');
        
        // Se non viene passato un token, usa quello corrente
        if ($token === null) {
            $token = Naval_EGT_Database::get_setting('dropbox_access_token') ?: $this->access_token;
        }
        
        if (empty($token)) {
            return array('error' => 'Token vuoto per i test');
        }
        
        $token = trim($token); // Pulisci il token
        $results = array();
        
        Naval_EGT_Dropbox_Debug::debug_log('Testing token', array(
            'length' => strlen($token),
            'preview' => substr($token, 0, 30) . '...'
        ));
        
        // Metodo 1: cURL con body null
        if (function_exists('curl_init')) {
            Naval_EGT_Dropbox_Debug::debug_log('Testing Method 1: cURL with null body');
            
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.dropboxapi.com/2/users/get_current_account',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_HTTPHEADER => array(
                    'Authorization: Bearer ' . $token,
                ),
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true
            ));
            
            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($curl);
            curl_close($curl);
            
            $results['curl_null_body'] = array(
                'http_code' => $http_code,
                'success' => ($http_code === 200),
                'curl_error' => $curl_error,
                'response_length' => strlen($response ?: ''),
                'response_preview' => substr($response ?: '', 0, 200)
            );
            
            Naval_EGT_Dropbox_Debug::debug_log('Method 1 Result', $results['curl_null_body']);
        }
        
        // // Metodo 2: cURL con body vuoto string
        // if (function_exists('curl_init')) {
        //     Naval_EGT_Dropbox_Debug::debug_log('Testing Method 2: cURL with empty string body');
            
        //     $curl = curl_init();
        //     curl_setopt_array($curl, array(
        //         CURLOPT_URL => 'https://api.dropboxapi.com/2/users/get_current_account',
        //         CURLOPT_RETURNTRANSFER => true,
        //         CURLOPT_POST => true,
        //         CURLOPT_POSTFIELDS => '',
        //         CURLOPT_HTTPHEADER => array(
        //             'Authorization: Bearer ' . $token,
        //             'Content-Type: application/json',
        //             'Content-Length: 0'
        //         ),
        //         CURLOPT_TIMEOUT => 15,
        //         CURLOPT_SSL_VERIFYPEER => true
        //     ));
            
        //     $response = curl_exec($curl);
        //     $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        //     $curl_error = curl_error($curl);
        //     curl_close($curl);
            
        //     $results['curl_empty_body'] = array(
        //         'http_code' => $http_code,
        //         'success' => ($http_code === 200),
        //         'curl_error' => $curl_error,
        //         'response_length' => strlen($response ?: ''),
        //         'response_preview' => substr($response ?: '', 0, 200)
        //     );
            
        //     Naval_EGT_Dropbox_Debug::debug_log('Method 2 Result', $results['curl_empty_body']);
        // }
        
        // // Metodo 3: cURL con body JSON vuoto
        // if (function_exists('curl_init')) {
        //     Naval_EGT_Dropbox_Debug::debug_log('Testing Method 3: cURL with empty JSON body');
            
        //     $curl = curl_init();
        //     curl_setopt_array($curl, array(
        //         CURLOPT_URL => 'https://api.dropboxapi.com/2/users/get_current_account',
        //         CURLOPT_RETURNTRANSFER => true,
        //         CURLOPT_POST => true,
        //         CURLOPT_POSTFIELDS => '{}',
        //         CURLOPT_HTTPHEADER => array(
        //             'Authorization: Bearer ' . $token,
        //             'Content-Type: application/json'
        //         ),
        //         CURLOPT_TIMEOUT => 15,
        //         CURLOPT_SSL_VERIFYPEER => true
        //     ));
            
        //     $response = curl_exec($curl);
        //     $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        //     $curl_error = curl_error($curl);
        //     curl_close($curl);
            
        //     $results['curl_json_body'] = array(
        //         'http_code' => $http_code,
        //         'success' => ($http_code === 200),
        //         'curl_error' => $curl_error,
        //         'response_length' => strlen($response ?: ''),
        //         'response_preview' => substr($response ?: '', 0, 200)
        //     );
            
        //     Naval_EGT_Dropbox_Debug::debug_log('Method 3 Result', $results['curl_json_body']);
        // }
        
        // // Metodo 4: WordPress wp_remote_post senza body
        // Naval_EGT_Dropbox_Debug::debug_log('Testing Method 4: WordPress wp_remote_post without body');
        
        // $wp_response = wp_remote_post('https://api.dropboxapi.com/2/users/get_current_account', array(
        //     'headers' => array(
        //         'Authorization' => 'Bearer ' . $token,
        //         'Content-Type' => 'application/json'
        //     ),
        //     'timeout' => 15
        // ));
        
        // if (!is_wp_error($wp_response)) {
        //     $results['wp_no_body'] = array(
        //         'http_code' => wp_remote_retrieve_response_code($wp_response),
        //         'success' => (wp_remote_retrieve_response_code($wp_response) === 200),
        //         'wp_error' => false,
        //         'response_length' => strlen(wp_remote_retrieve_body($wp_response)),
        //         'response_preview' => substr(wp_remote_retrieve_body($wp_response), 0, 200)
        //     );
        // } else {
        //     $results['wp_no_body'] = array(
        //         'http_code' => 0,
        //         'success' => false,
        //         'wp_error' => $wp_response->get_error_message(),
        //         'response_length' => 0,
        //         'response_preview' => ''
        //     );
        // }
        
        // Naval_EGT_Dropbox_Debug::debug_log('Method 4 Result', $results['wp_no_body']);
        
        // Riepilogo risultati
        $successful_methods = array();
        foreach ($results as $method => $result) {
            if ($result['success']) {
                $successful_methods[] = $method;
            }
        }
        
        $results['summary'] = array(
            'total_methods_tested' => count($results) - 1, // -1 per escludere il summary stesso
            'successful_methods' => $successful_methods,
            'success_count' => count($successful_methods),
            'token_seems_valid' => (count($successful_methods) > 0)
        );
        
        Naval_EGT_Dropbox_Debug::debug_log('MULTIPLE TOKEN TESTS COMPLETED', $results['summary']);
        
        return $results;
    }
    
    /**
     * Forza riautenticazione completa
     */
    public function force_reauth() {
        Naval_EGT_Dropbox_Debug::debug_log('=== FORCING COMPLETE REAUTH ===');
        
        // Pulisci tutto
        Naval_EGT_Database::update_setting('dropbox_access_token', '');
        Naval_EGT_Database::update_setting('dropbox_refresh_token', '');
        Naval_EGT_Database::update_setting('dropbox_oauth_state', '');
        
        // Pulisci anche da WordPress options come fallback
        delete_option('naval_egt_dropbox_access_token');
        delete_option('naval_egt_dropbox_refresh_token');
        delete_transient('naval_egt_dropbox_state');
        
        // Pulisci proprietà della classe
        $this->access_token = '';
        $this->refresh_token = '';
        
        Naval_EGT_Dropbox_Debug::debug_log('All tokens cleared, generating new auth URL');
        
        $auth_url = $this->get_authorization_url();
        
        Naval_EGT_Dropbox_Debug::debug_log('REAUTH COMPLETED', array(
            'auth_url_generated' => !empty($auth_url),
            'auth_url' => $auth_url
        ));
        
        return array(
            'success' => true,
            'message' => 'Tutti i token sono stati cancellati. Procedi con la nuova autorizzazione.',
            'auth_url' => $auth_url
        );
    }
    
    /**
     * Diagnosi completa del sistema Dropbox
     */
    public function full_system_diagnosis() {
        Naval_EGT_Dropbox_Debug::debug_log('=== FULL SYSTEM DIAGNOSIS STARTED ===');
        
        $diagnosis = array();
        
        // 1. Verifica credenziali base
        $diagnosis['credentials'] = array(
            'app_key_set' => !empty($this->app_key),
            'app_secret_set' => !empty($this->app_secret),
            'app_key_preview' => $this->app_key ? substr($this->app_key, 0, 8) . '...' : 'EMPTY',
            'hardcoded_credentials_match' => ($this->app_key === self::DROPBOX_APP_KEY)
        );
        
        // 2. Analisi token dal database
        $db_token = Naval_EGT_Database::get_setting('dropbox_access_token');
        $wp_token = get_option('naval_egt_dropbox_access_token');
        
        $diagnosis['database_tokens'] = array(
            'naval_db_token_exists' => !empty($db_token),
            'naval_db_token_length' => strlen($db_token ?: ''),
            'wp_option_token_exists' => !empty($wp_token),
            'wp_option_token_length' => strlen($wp_token ?: ''),
            'tokens_match' => ($db_token === $wp_token),
            'primary_token_source' => !empty($db_token) ? 'naval_database' : (!empty($wp_token) ? 'wp_options' : 'none')
        );
        
        // 3. Analisi dettagliata del token
        $primary_token = $db_token ?: $wp_token;
        if (!empty($primary_token)) {
            $diagnosis['token_analysis'] = $this->analyze_token_detailed($primary_token);
        } else {
            $diagnosis['token_analysis'] = array('error' => 'Nessun token disponibile');
        }
        
        // 4. Test multipli del token
        if (!empty($primary_token)) {
            $diagnosis['token_tests'] = $this->test_token_multiple_methods($primary_token);
        } else {
            $diagnosis['token_tests'] = array('error' => 'Nessun token da testare');
        }
        
        // 5. Verifica configurazione
        $diagnosis['configuration'] = array(
            'is_configured' => $this->is_configured(),
            'has_credentials' => $this->has_credentials(),
            'redirect_uri' => $this->get_redirect_uri()
        );
        
        // 6. Test connessione se possibile
        if (!empty($primary_token)) {
            $diagnosis['connection_test'] = $this->test_connection();
        } else {
            $diagnosis['connection_test'] = array('success' => false, 'message' => 'Nessun token per test connessione');
        }
        
        // 7. Test credenziali app
        $diagnosis['app_credentials_test'] = $this->test_app_credentials();
        
        // 8. Raccomandazioni basate sui risultati
        $diagnosis['recommendations'] = $this->generate_recommendations($diagnosis);
        
        Naval_EGT_Dropbox_Debug::debug_log('FULL SYSTEM DIAGNOSIS COMPLETED', array(
            'total_sections' => count($diagnosis),
            'token_available' => !empty($primary_token),
            'connection_working' => isset($diagnosis['connection_test']['success']) ? $diagnosis['connection_test']['success'] : false
        ));
        
        return $diagnosis;
    }
    
    /**
     * Genera raccomandazioni basate sulla diagnosi
     */
    private function generate_recommendations($diagnosis) {
        $recommendations = array();
        
        // Verifica credenziali app
        if (isset($diagnosis['app_credentials_test']['success']) && !$diagnosis['app_credentials_test']['success']) {
            $recommendations[] = array(
                'priority' => 'CRITICAL',
                'issue' => 'Credenziali app non valide',
                'solution' => $diagnosis['app_credentials_test']['message'],
                'action' => 'check_app_credentials'
            );
        }
        
        // Verifica token
        if (isset($diagnosis['token_analysis']['error'])) {
            $recommendations[] = array(
                'priority' => 'HIGH',
                'issue' => 'Token mancante',
                'solution' => 'Esegui una nuova autorizzazione Dropbox',
                'action' => 'force_reauth'
            );
        } elseif (isset($diagnosis['token_analysis']['seems_valid']) && !$diagnosis['token_analysis']['seems_valid']) {
            $recommendations[] = array(
                'priority' => 'HIGH',
                'issue' => 'Token non valido o corrotto',
                'solution' => 'Il token non rispetta i pattern standard. Rigenera il token.',
                'action' => 'force_reauth'
            );
        }
        
        // Verifica test connessione
        if (isset($diagnosis['token_tests']['summary']['success_count']) && $diagnosis['token_tests']['summary']['success_count'] === 0) {
            $recommendations[] = array(
                'priority' => 'HIGH',
                'issue' => 'Tutti i test API falliscono',
                'solution' => 'Il token non funziona con nessun metodo. Rigenera completamente.',
                'action' => 'force_reauth'
            );
        } elseif (isset($diagnosis['token_tests']['summary']['success_count']) && $diagnosis['token_tests']['summary']['success_count'] < 3) {
            $recommendations[] = array(
                'priority' => 'MEDIUM',
                'issue' => 'Alcuni metodi API falliscono',
                'solution' => 'Il token funziona parzialmente. Potrebbe essere un problema di configurazione.',
                'action' => 'check_server_config'
            );
        }
        
        // Verifica database
        if (isset($diagnosis['database_tokens']['tokens_match']) && !$diagnosis['database_tokens']['tokens_match']) {
            $recommendations[] = array(
                'priority' => 'MEDIUM',
                'issue' => 'Token non sincronizzati tra database',
                'solution' => 'I token in Naval_Database e wp_options non coincidono. Sincronizza i database.',
                'action' => 'sync_databases'
            );
        }
        
        // Se tutto sembra OK
        if (empty($recommendations) && isset($diagnosis['connection_test']['success']) && $diagnosis['connection_test']['success']) {
            $recommendations[] = array(
                'priority' => 'INFO',
                'issue' => 'Sistema funzionante',
                'solution' => 'La configurazione Dropbox sembra funzionare correttamente.',
                'action' => 'none'
            );
        }
        
        return $recommendations;
    }
    
    /**
     * Lista file e cartelle
     */
    public function list_folder($path = '', $recursive = false) {
        $data = array(
            'path' => empty($path) ? '' : $path,
            'recursive' => $recursive,
            'include_media_info' => false,
            'include_deleted' => false,
            'include_has_explicit_shared_members' => false
        );
        
        return $this->api_call('files/list_folder', $data);
    }
    
    /**
     * Cerca cartelle che iniziano con un codice specifico
     */
    public function find_folder_by_code($user_code) {
        $result = $this->list_folder('');
        
        if (!$result['success']) {
            return $result;
        }
        
        $folders = array();
        if (isset($result['data']['entries'])) {
            foreach ($result['data']['entries'] as $entry) {
                if ($entry['.tag'] === 'folder') {
                    $folder_name = basename($entry['path_lower']);
                    if (strpos($folder_name, $user_code) === 0) {
                        $folders[] = $entry;
                    }
                }
            }
        }
        
        return array('success' => true, 'folders' => $folders);
    }
    
    /**
     * Crea una cartella
     */
    public function create_folder($path) {
        $data = array(
            'path' => $path,
            'autorename' => false
        );
        
        return $this->api_call('files/create_folder_v2', $data);
    }
    
    /**
     * Carica un file
     */
    public function upload_file($file_path, $dropbox_path, $file_content = null) {
        if ($file_content === null && file_exists($file_path)) {
            $file_content = file_get_contents($file_path);
        }
        
        if (!$file_content) {
            return array('success' => false, 'message' => 'Impossibile leggere il file');
        }
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->access_token,
            'Content-Type' => 'application/octet-stream',
            'Dropbox-API-Arg' => json_encode(array(
                'path' => $dropbox_path,
                'mode' => 'add',
                'autorename' => true,
                'mute' => false
            ))
        );
        
        $response = wp_remote_post(self::CONTENT_URL . 'files/upload', array(
            'headers' => $headers,
            'body' => $file_content,
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'Errore di upload: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if ($response_code === 200) {
            return array('success' => true, 'data' => $body);
        } else {
            $error_message = isset($body['error_summary']) ? $body['error_summary'] : 'Errore di upload';
            return array('success' => false, 'message' => $error_message);
        }
    }
    
    /**
     * Scarica un file
     */
    public function download_file($dropbox_path) {
        $headers = array(
            'Authorization' => 'Bearer ' . $this->access_token,
            'Dropbox-API-Arg' => json_encode(array('path' => $dropbox_path))
        );
        
        $response = wp_remote_get(self::CONTENT_URL . 'files/download', array(
            'headers' => $headers,
            'timeout' => 120
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'message' => 'Errore di download: ' . $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code === 200) {
            $file_content = wp_remote_retrieve_body($response);
            $headers = wp_remote_retrieve_headers($response);
            
            return array(
                'success' => true, 
                'content' => $file_content,
                'headers' => $headers
            );
        } else {
            return array('success' => false, 'message' => 'File non trovato o errore di download');
        }
    }
    
    /**
     * Ottiene un link temporaneo per il download
     */
    public function get_temporary_link($dropbox_path) {
        $data = array('path' => $dropbox_path);
        return $this->api_call('files/get_temporary_link', $data);
    }
    
    /**
     * Elimina un file o cartella
     */
    public function delete($path) {
        $data = array('path' => $path);
        return $this->api_call('files/delete_v2', $data);
    }
    
    /**
     * Ottiene metadati di un file
     */
    public function get_metadata($path) {
        $data = array(
            'path' => $path,
            'include_media_info' => false,
            'include_deleted' => false,
            'include_has_explicit_shared_members' => false
        );
        
        return $this->api_call('files/get_metadata', $data);
    }
    
    /**
     * Sincronizza la cartella di un utente
     */
    public function sync_user_folder($user_code, $user_id) {
        $folder_result = $this->find_folder_by_code($user_code);
        
        if (!$folder_result['success']) {
            return $folder_result;
        }
        
        if (empty($folder_result['folders'])) {
            return array('success' => false, 'message' => 'Nessuna cartella trovata per il codice ' . $user_code);
        }
        
        // Prende la prima cartella trovata
        $folder = $folder_result['folders'][0];
        $folder_path = $folder['path_lower'];
        
        // Aggiorna il percorso della cartella nell'utente
        Naval_EGT_User_Manager::update_user($user_id, array('dropbox_folder' => $folder_path));
        
        // Lista tutti i file nella cartella
        $files_result = $this->list_folder($folder_path, true);
        
        if (!$files_result['success']) {
            return $files_result;
        }
        
        // Sincronizza i file nel database
        global $wpdb;
        $table_files = $wpdb->prefix . 'naval_egt_files';
        
        // Cancella i file esistenti per questo utente
        $wpdb->delete($table_files, array('user_id' => $user_id), array('%d'));
        
        if (isset($files_result['data']['entries'])) {
            foreach ($files_result['data']['entries'] as $entry) {
                if ($entry['.tag'] === 'file') {
                    $wpdb->insert($table_files, array(
                        'user_id' => $user_id,
                        'user_code' => $user_code,
                        'file_name' => basename($entry['name']),
                        'file_path' => $entry['path_display'],
                        'dropbox_path' => $entry['path_lower'],
                        'file_size' => $entry['size'],
                        'dropbox_id' => $entry['id'],
                        'last_modified' => $entry['server_modified'],
                        'created_at' => current_time('mysql')
                    ));
                }
            }
        }
        
        return array('success' => true, 'message' => 'Sincronizzazione completata', 'folder_path' => $folder_path);
    }
    
    /**
     * Ottiene tutti i log di debug
     */
    public function get_debug_logs() {
        return Naval_EGT_Dropbox_Debug::get_debug_logs();
    }
    
    /**
     * Pulisce i log di debug
     */
    public function clear_debug_logs() {
        Naval_EGT_Dropbox_Debug::clear_debug_logs();
    }
    
    /**
     * Esporta debug completo
     */
    public function export_debug_info() {
        return array(
            'debug_logs' => Naval_EGT_Dropbox_Debug::export_debug_logs(),
            'current_config' => $this->debug_configuration(),
            'database_check' => array(
                'dropbox_access_token' => Naval_EGT_Database::get_setting('dropbox_access_token') ? 'SET' : 'EMPTY',
                'dropbox_refresh_token' => Naval_EGT_Database::get_setting('dropbox_refresh_token') ? 'SET' : 'EMPTY',
                'dropbox_app_key' => Naval_EGT_Database::get_setting('dropbox_app_key') ? 'SET' : 'EMPTY',
                'dropbox_app_secret' => Naval_EGT_Database::get_setting('dropbox_app_secret') ? 'SET' : 'EMPTY'
            )
        );
    }
}

?>