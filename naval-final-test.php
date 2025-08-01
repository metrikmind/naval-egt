// TEST API CON TOKEN PULITO - VERSIONE FINALE
    echo '<div class="test">';
    echo '<h3>🧪 Test API Finale (Nessun Body JSON)</h3>';
    
    $api_url = 'https://api.dropboxapi.com/2/users/get_current_account';
    $test_token = trim($main_token); // Usa versione pulita
    
    if (function_exists('curl_init')) {
        $curl = curl_init();
        
        // CONFIGURAZIONE CURL ULTRA-PULITA - NESSUN JSON
        curl_setopt_array($curl, array(
            CURLOPT_URL => $api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '', // Stringa vuota invece di null
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $test_token,
                'Content-Length: 0'
                // RIMOSSO Content-Type: application/json che causa problemi
            ),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Naval-EGT-Final-Test/1.0'
        ));
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        $curl_info = curl_getinfo($curl);
        
        curl_close($curl);
        
        if (!empty($curl_error)) {
            echo '<p class="error">❌ <strong>Errore cURL:</strong> ' . htmlspecialchars($curl_error) . '</p>';
        } else {
            echo '<p><strong>HTTP Code:</strong> ' . $http_code . '</p>';
            echo '<p><strong>Risposta lunghezza:</strong> ' . strlen($response ?: '') . ' caratteri</p>';
            
            if ($http_code === 200) {
                echo '<p class="success">🎉 <strong>SUCCESSO TOTALE! DROPBOX FUNZIONA!</strong></p>';
                $data = json_decode($response, true);
                if ($data && isset($data['email'])) {
                    echo '<p><strong>✅ Account connesso:</strong> ' . htmlspecialchars($data['email']) . '</p>';
                    echo '<p><strong>✅ Nome:</strong> ' . htmlspecialchars($data['name']['display_name'] ?? 'N/A') . '</p>';
                    echo '<p><strong>✅ Paese:</strong> ' . htmlspecialchars($data['country'] ?? 'N/A') . '</p>';
                    
                    // Se il test funziona, offri di correggere il plugin
                    echo '<div class="test success">';
                    echo '<h3>🔧 DROPBOX FUNZIONA! Correggiamo il Plugin?</h3>';
                    echo '<p><strong>Il token è perfetto e l\'API Dropbox risponde!</strong><br>';
                    echo 'Il problema è nel plugin che invia header JSON sbagliati.</p>';
                    echo '<form method="post">';
                    echo '<button type="submit" name="fix_plugin_final" class="fix-button">🛠️ APPLICA FIX DEFINITIVO AL PLUGIN</button>';
                    echo '</form>';
                    echo '</div>';
                }
            } else {
                echo '<p class="error">❌ <strong>HTTP Error ' . $http_code . '</strong></p>';
                $error_data = json_decode($response ?: '{}', true);
                if (isset($error_data['error_summary'])) {
                    echo '<p><strong>Errore Dropbox:</strong> ' . htmlspecialchars($error_data['error_summary']) . '</p>';
                } else {
                    echo '<p><strong>Risposta raw:</strong></p>';
                    echo '<pre>' . htmlspecialchars(substr($response ?: '', 0, 500)) . '</pre>';
                }
            }
        }
        
        echo '<details><summary>🔍 Info cURL Complete</summary>';
        echo '<pre>' . print_r($curl_info, true) . '</pre>';
        echo '</details>';
    }
    
    // TEST ALTERNATIVO - SENZA Content-Type
    echo '<h4>Test Alternativo - Senza Content-Type Header</h4>';
    
    if (function_exists('curl_init')) {
        $curl2 = curl_init();
        
        curl_setopt_array($curl2, array(
            CURLOPT_URL => $api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $test_token,
                'Content-Length: 0'
                // COMPLETAMENTE SENZA Content-Type
            ),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ));
        
        $response2 = curl_exec($curl2);
        $http_code2 = curl_getinfo($curl2, CURLINFO_HTTP_CODE);
        $curl_error2 = curl_error($curl2);
        
        curl_close($curl2);
        
        echo '<p><strong>Test senza Content-Type - HTTP Code:</strong> ' . $http_code2 . '</p>';
        
        if ($http_code2 === 200) {
            echo '<p class="success">✅ <strong>SUCCESSO anche senza Content-Type!</strong></p>';
            $data2 = json_decode($response2, true);
            if ($data2 && isset($data2['email'])) {
                echo '<p><strong>Email:</strong> ' . htmlspecialchars($data2['email']) . '</p>';
            }
        } else {
            echo '<p><strong>Risposta:</strong> ' . htmlspecialchars(substr($response2 ?: '', 0, 200<?php
/**
 * TEST FINALE DROPBOX - ANALISI COMPLETA TOKEN
 * Crea questo file come naval-final-test.php nella root del sito
 * Visita: https://naval.vjformazione.it/naval-final-test.php
 */

require_once('wp-config.php');
require_once('wp-load.php');

if (!current_user_can('manage_options')) {
    die('Accesso negato. Effettua il login come amministratore.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Naval EGT - Test Finale Token</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #f5f5f5; }
        .test { border: 1px solid #ccc; margin: 10px 0; padding: 15px; background: white; border-radius: 8px; }
        .success { background: #d4edda; border-color: #c3e6cb; }
        .error { background: #f8d7da; border-color: #f5c6cb; }
        .warning { background: #fff3cd; border-color: #ffeaa7; }
        .info { background: #d1ecf1; border-color: #bee5eb; }
        pre { background: #f8f9fa; padding: 10px; overflow-x: auto; font-size: 12px; }
        .token-analysis { background: #e9ecef; padding: 10px; margin: 10px 0; border-radius: 4px; }
        .hex-dump { font-family: 'Courier New', monospace; font-size: 11px; line-height: 1.2; }
        button { background: #007cba; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; }
        button:hover { background: #005a87; }
        .fix-button { background: #28a745; }
        .fix-button:hover { background: #1e7e34; }
    </style>
</head>
<body>
    <h1>🔬 Naval EGT - Analisi Finale Token Dropbox</h1>
    
    <?php
    global $wpdb;
    
    // Ottieni token in tutti i modi possibili
    $token_db_direct = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'naval_egt_dropbox_access_token'");
    $token_wp_option = get_option('naval_egt_dropbox_access_token');
    $token_naval_class = class_exists('Naval_EGT_Database') ? Naval_EGT_Database::get_setting('dropbox_access_token') : null;
    
    echo '<div class="test info">';
    echo '<h3>🔍 Analisi Token - Tutti i Metodi</h3>';
    echo '<table border="1" cellpadding="5" style="width:100%; border-collapse: collapse;">';
    echo '<tr><th>Metodo</th><th>Presente</th><th>Lunghezza</th><th>Preview</th><th>Identico?</th></tr>';
    
    $tokens = [
        'Database Diretto' => $token_db_direct,
        'get_option()' => $token_wp_option,
        'Naval_EGT_Database' => $token_naval_class
    ];
    
    $main_token = null;
    foreach ($tokens as $method => $token) {
        $present = !empty($token) ? '✅ Sì' : '❌ No';
        $length = $token ? strlen($token) : 0;
        $preview = $token ? substr($token, 0, 30) . '...' : 'N/A';
        
        if (!$main_token && $token) {
            $main_token = $token;
            $identical = '🔵 Riferimento';
        } else {
            $identical = ($token === $main_token) ? '✅ Identico' : '❌ Diverso';
        }
        
        echo "<tr><td><strong>$method</strong></td><td>$present</td><td>$length</td><td><code>$preview</code></td><td>$identity</td></tr>";
    }
    echo '</table>';
    echo '</div>';
    
    if (empty($main_token)) {
        echo '<div class="test error"><h3>❌ Errore Critico</h3><p>Nessun token trovato con nessun metodo!</p></div>';
        exit;
    }
    
    // ANALISI DETTAGLIATA DEL TOKEN
    echo '<div class="test warning">';
    echo '<h3>🔬 Analisi Dettagliata Token</h3>';
    
    $token_clean = trim($main_token);
    $token_length = strlen($main_token);
    $token_clean_length = strlen($token_clean);
    
    echo '<div class="token-analysis">';
    echo '<p><strong>Lunghezza originale:</strong> ' . $token_length . ' caratteri</p>';
    echo '<p><strong>Lunghezza dopo trim:</strong> ' . $token_clean_length . ' caratteri</p>';
    echo '<p><strong>Differenza:</strong> ' . ($token_length - $token_clean_length) . ' caratteri ';
    echo ($token_length !== $token_clean_length ? '<span style="color:red;">⚠️ Contiene spazi/caratteri nascosti!</span>' : '<span style="color:green;">✅ Pulito</span>') . '</p>';
    
    echo '<p><strong>Inizia con:</strong> <code>' . substr($token_clean, 0, 10) . '</code></p>';
    echo '<p><strong>Finisce con:</strong> <code>...' . substr($token_clean, -10) . '</code></p>';
    echo '<p><strong>Formato Dropbox:</strong> ' . (strpos($token_clean, 'sl.') === 0 ? '✅ Corretto' : '❌ Non valido') . '</p>';
    
    // Cerca caratteri non-ASCII
    $non_ascii_chars = [];
    for ($i = 0; $i < strlen($main_token); $i++) {
        $char = $main_token[$i];
        $ascii = ord($char);
        if ($ascii < 32 || $ascii > 126) {
            $non_ascii_chars[] = "Pos $i: " . sprintf("0x%02X", $ascii) . " (" . ($ascii < 32 ? 'control' : 'extended') . ")";
        }
    }
    
    if (!empty($non_ascii_chars)) {
        echo '<p style="color:red;"><strong>⚠️ Caratteri non-ASCII trovati:</strong></p>';
        echo '<ul>';
        foreach ($non_ascii_chars as $char_info) {
            echo '<li><code>' . htmlspecialchars($char_info) . '</code></li>';
        }
        echo '</ul>';
    } else {
        echo '<p style="color:green;"><strong>✅ Solo caratteri ASCII validi</strong></p>';
    }
    echo '</div>';
    
    // HEX DUMP per analisi avanzata
    echo '<details><summary>🔍 Hex Dump Completo (primi 200 caratteri)</summary>';
    echo '<div class="hex-dump">';
    $hex_sample = substr($main_token, 0, 200);
    for ($i = 0; $i < strlen($hex_sample); $i += 16) {
        $chunk = substr($hex_sample, $i, 16);
        $hex = '';
        $ascii = '';
        
        for ($j = 0; $j < strlen($chunk); $j++) {
            $char = $chunk[$j];
            $hex .= sprintf('%02X ', ord($char));
            $ascii .= (ord($char) >= 32 && ord($char) <= 126) ? $char : '.';
        }
        
        printf("%04X: %-48s %s\n", $i, $hex, $ascii);
    }
    echo '</div>';
    echo '</details>';
    
    echo '</div>';
    
    // TEST API CON TOKEN PULITO
    echo '<div class="test">';
    echo '<h3>🧪 Test API con Token Pulito</h3>';
    
    $api_url = 'https://api.dropboxapi.com/2/users/get_current_account';
    $test_token = trim($main_token); // Usa versione pulita
    
    if (function_exists('curl_init')) {
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
            CURLOPT_URL => $api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => null,
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $test_token,
                'Content-Type: application/json',
                'Content-Length: 0'
            ),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Naval-EGT-Final-Test/1.0'
        ));
        
        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($curl);
        $curl_info = curl_getinfo($curl);
        
        curl_close($curl);
        
        if (!empty($curl_error)) {
            echo '<p class="error">❌ <strong>Errore cURL:</strong> ' . htmlspecialchars($curl_error) . '</p>';
        } else {
            echo '<p><strong>HTTP Code:</strong> ' . $http_code . '</p>';
            echo '<p><strong>Risposta lunghezza:</strong> ' . strlen($response ?: '') . ' caratteri</p>';
            
            if ($http_code === 200) {
                echo '<p class="success">🎉 <strong>SUCCESSO! TOKEN FUNZIONA!</strong></p>';
                $data = json_decode($response, true);
                if ($data && isset($data['email'])) {
                    echo '<p><strong>✅ Account connesso:</strong> ' . htmlspecialchars($data['email']) . '</p>';
                    echo '<p><strong>✅ Nome:</strong> ' . htmlspecialchars($data['name']['display_name'] ?? 'N/A') . '</p>';
                    
                    // Se il test funziona, offri di correggere il plugin
                    echo '<div class="test success">';
                    echo '<h3>🔧 Il Token Funziona! Vuoi correggere il plugin?</h3>';
                    echo '<p>Il token funziona perfettamente quando testato direttamente. Il problema è nel plugin.</p>';
                    echo '<form method="post">';
                    echo '<button type="submit" name="fix_plugin" class="fix-button">🛠️ Correggi Plugin Automaticamente</button>';
                    echo '</form>';
                    echo '</div>';
                }
            } else {
                echo '<p class="error">❌ <strong>HTTP Error ' . $http_code . '</strong></p>';
                $error_data = json_decode($response ?: '{}', true);
                if (isset($error_data['error_summary'])) {
                    echo '<p><strong>Errore Dropbox:</strong> ' . htmlspecialchars($error_data['error_summary']) . '</p>';
                }
                echo '<pre>' . htmlspecialchars(substr($response ?: '', 0, 500)) . '</pre>';
            }
        }
        
        echo '<details><summary>🔍 Info cURL Complete</summary>';
        echo '<pre>' . print_r($curl_info, true) . '</pre>';
        echo '</details>';
    }
    echo '</div>';
    
    // AUTO-FIX DEL PLUGIN
    if (isset($_POST['fix_plugin'])) {
        echo '<div class="test warning">';
        echo '<h3>🛠️ Correzione Automatica Plugin</h3>';
        
        // Pulisci e salva nuovamente il token
        $clean_token = trim($main_token);
        
        // Salva con tutti i metodi
        $fixes = [];
        
        // Fix 1: WordPress nativo
        $wp_fix = update_option('naval_egt_dropbox_access_token', $clean_token);
        $fixes[] = 'WordPress update_option: ' . ($wp_fix ? '✅ OK' : '❌ Fallito');
        
        // Fix 2: Database diretto
        $db_fix = $wpdb->update(
            $wpdb->options,
            array('option_value' => $clean_token),
            array('option_name' => 'naval_egt_dropbox_access_token'),
            array('%s'),
            array('%s')
        );
        $fixes[] = 'Database diretto: ' . ($db_fix !== false ? '✅ OK' : '❌ Fallito');
        
        // Fix 3: Naval_EGT_Database
        if (class_exists('Naval_EGT_Database')) {
            $naval_fix = Naval_EGT_Database::update_setting('dropbox_access_token', $clean_token);
            $fixes[] = 'Naval_EGT_Database: ' . ($naval_fix ? '✅ OK' : '❌ Fallito');
        }
        
        echo '<p><strong>Risultati correzione:</strong></p>';
        echo '<ul>';
        foreach ($fixes as $fix) {
            echo '<li>' . $fix . '</li>';
        }
        echo '</ul>';
        
        echo '<p class="success"><strong>✅ Correzione completata! Ricarica la pagina per verificare.</strong></p>';
        echo '<script>setTimeout(function(){ window.location.reload(); }, 3000);</script>';
        echo '</div>';
    }
    
    // DIAGNOSTICA SISTEMA
    echo '<div class="test info">';
    echo '<h3>💻 Diagnostica Sistema</h3>';
    echo '<ul>';
    echo '<li><strong>WordPress:</strong> ' . get_bloginfo('version') . '</li>';
    echo '<li><strong>PHP:</strong> ' . phpversion() . '</li>';
    echo '<li><strong>MySQL:</strong> ' . $wpdb->db_version() . '</li>';
    echo '<li><strong>Charset DB:</strong> ' . $wpdb->charset . '</li>';
    echo '<li><strong>Collate DB:</strong> ' . $wpdb->collate . '</li>';
    echo '<li><strong>cURL:</strong> ' . (function_exists('curl_init') ? '✅ Disponibile' : '❌ Non disponibile') . '</li>';
    echo '<li><strong>OpenSSL:</strong> ' . (extension_loaded('openssl') ? '✅ Disponibile' : '❌ Non disponibile') . '</li>';
    echo '<li><strong>Max Execution Time:</strong> ' . ini_get('max_execution_time') . 's</li>';
    echo '<li><strong>Memory Limit:</strong> ' . ini_get('memory_limit') . '</li>';
    echo '</ul>';
    echo '</div>';
    
    ?>
    
    <hr>
    <p><small>🕒 Test eseguito il: <?php echo date('Y-m-d H:i:s'); ?></small></p>
    <p><small>📧 Per supporto: <a href="mailto:supporto@naval.it">supporto@naval.it</a></small></p>
</body>
</html>