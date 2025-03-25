<?php
// Funzione per caricare il tema dell'utente
function loadUserTheme($conn, $user_id) {
    // Verifica se la tabella user_preferences esiste
    $check_table = "SHOW TABLES LIKE 'user_preferences'";
    $table_exists = $conn->query($check_table);

    if ($table_exists->num_rows == 0) {
        // Crea la tabella user_preferences se non esiste
        $create_table = "CREATE TABLE user_preferences (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NOT NULL,
            theme VARCHAR(50) NOT NULL DEFAULT 'default',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";

        $conn->query($create_table);
        return 'default'; // Tema predefinito se la tabella non esiste
    }

    // Controlla se l'utente ha già una preferenza
    $check_pref = "SELECT theme FROM user_preferences WHERE user_id = $user_id";
    $pref_result = $conn->query($check_pref);

    if ($pref_result->num_rows == 0) {
        // Inserisci la preferenza predefinita
        $insert_pref = "INSERT INTO user_preferences (user_id, theme) VALUES ($user_id, 'default')";
        $conn->query($insert_pref);
        return 'default'; // Tema predefinito se l'utente non ha preferenze
    } else {
        $pref = $pref_result->fetch_assoc();
        return $pref['theme'];
    }
}

// Funzione per generare il CSS del tema
function generateThemeCSS($theme_key) {
    // Definizione dei temi disponibili
    $themes = [
        'default' => [
            'name' => 'Default FBI',
            'colors' => [
                'bg-dark' => '#0d1117',
                'accent-color' => '#003366',
                'text-primary' => '#c9d1d9'
            ]
        ],
        'dark-red' => [
            'name' => 'Dark Red',
            'colors' => [
                'bg-dark' => '#0a0000',
                'accent-color' => '#990000',
                'text-primary' => '#e6e6e6'
            ]
        ],
        'cyber-green' => [
            'name' => 'Cyber Green',
            'colors' => [
                'bg-dark' => '#001a00',
                'accent-color' => '#00cc66',
                'text-primary' => '#ccffcc'
            ]
        ],
        'midnight-purple' => [
            'name' => 'Midnight Purple',
            'colors' => [
                'bg-dark' => '#0d0033',
                'accent-color' => '#6600cc',
                'text-primary' => '#e6e6ff'
            ]
        ],
        'tactical-orange' => [
            'name' => 'Tactical Orange',
            'colors' => [
                'bg-dark' => '#1a1000',
                'accent-color' => '#cc7700',
                'text-primary' => '#ffe6cc'
            ]
        ],
        'arctic-blue' => [
            'name' => 'Arctic Blue',
            'colors' => [
                'bg-dark' => '#e6f2ff',
                'accent-color' => '#0077cc',
                'text-primary' => '#003366'
            ]
        ]
    ];
    
    if (!isset($themes[$theme_key])) {
        return ''; // Ritorna vuoto se il tema non esiste
    }
    
    $theme = $themes[$theme_key];
    $colors = $theme['colors'];
    
    $css = "<style id='dynamic-theme'>\n";
    $css .= ":root {\n";
    
    if (isset($colors['bg-dark'])) {
        $css .= "    --bg-dark: " . $colors['bg-dark'] . ";\n";
        
        // Calcola colori derivati
        $bg_darker = adjustBrightness($colors['bg-dark'], -10);
        $bg_light = adjustBrightness($colors['bg-dark'], 10);
        $bg_lighter = adjustBrightness($colors['bg-dark'], 20);
        
        $css .= "    --bg-darker: " . $bg_darker . ";\n";
        $css .= "    --bg-light: " . $bg_light . ";\n";
        $css .= "    --bg-lighter: " . $bg_lighter . ";\n";
    }
    
    if (isset($colors['accent-color'])) {
        $css .= "    --accent-color: " . $colors['accent-color'] . ";\n";
        
        // Calcola colori derivati
        $accent_hover = adjustBrightness($colors['accent-color'], -10);
        $accent_light = adjustBrightness($colors['accent-color'], 10);
        $accent_dark = adjustBrightness($colors['accent-color'], -20);
        
        $css .= "    --accent-hover: " . $accent_hover . ";\n";
        $css .= "    --accent-light: " . $accent_light . ";\n";
        $css .= "    --accent-dark: " . $accent_dark . ";\n";
        
        // Imposta anche il colore secondario come complementare
        $secondary_color = getComplementaryColor($colors['accent-color']);
        $css .= "    --secondary-color: " . $secondary_color . ";\n";
        $css .= "    --secondary-hover: " . adjustBrightness($secondary_color, -10) . ";\n";
    }
    
    if (isset($colors['text-primary'])) {
        $css .= "    --text-primary: " . $colors['text-primary'] . ";\n";
        
        // Calcola colori derivati
        $text_secondary = adjustOpacity($colors['text-primary'], 0.7);
        
        $css .= "    --text-secondary: " . $text_secondary . ";\n";
    }
    
    $css .= "}\n";
    $css .= "</style>";
    
    return $css;
}

// Funzione per regolare la luminosità di un colore
function adjustBrightness($hex, $steps) {
    // Converte hex in RGB
    $hex = str_replace('#', '', $hex);
    
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Regola la luminosità
    $r = max(0, min(255, $r + $steps));
    $g = max(0, min(255, $g + $steps));
    $b = max(0, min(255, $b + $steps));
    
    // Converte RGB in hex
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

// Funzione per regolare l'opacità di un colore
function adjustOpacity($hex, $opacity) {
    // Converte hex in RGB
    $hex = str_replace('#', '', $hex);
    
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Restituisce colore con opacità
    return "rgba($r, $g, $b, $opacity)";
}

// Funzione per ottenere il colore complementare
function getComplementaryColor($hex) {
    // Converte hex in RGB
    $hex = str_replace('#', '', $hex);
    
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Calcola il complementare
    $r = 255 - $r;
    $g = 255 - $g;
    $b = 255 - $b;
    
    // Converte RGB in hex
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

// Carica il tema dell'utente se è loggato
if (isset($_SESSION['user_id'])) {
    // Usa la funzione loadUserTheme per ottenere il tema dell'utente
    $user_theme = loadUserTheme($conn, $_SESSION['user_id']);

    // Genera e stampa il CSS del tema
    $theme_css = generateThemeCSS($user_theme);
    echo $theme_css;
}
?>