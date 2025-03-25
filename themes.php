<?php
require_once 'config.php';

// Verifica se l'utente è loggato
if (!isLoggedIn()) {
    redirect('index.php');
}

// Ottieni le informazioni dell'utente
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = $user_id";
$result = $conn->query($sql);
$user = $result->fetch_assoc();

// Gestione del cambio tema
$message = '';
$error = '';

// Verifica se la tabella user_preferences esiste
$check_table = "SHOW TABLES LIKE 'user_preferences'";
$table_exists = $conn->query($check_table);

if ($table_exists->num_rows == 0) {
    // Crea la tabella user_preferences
    $create_table = "CREATE TABLE user_preferences (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        user_id INT(11) NOT NULL,
        theme VARCHAR(50) NOT NULL DEFAULT 'default',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    if ($conn->query($create_table) === TRUE) {
        $message = "Tabella delle preferenze creata con successo.";
    } else {
        $error = "Errore nella creazione della tabella delle preferenze: " . $conn->error;
    }
}

// Controlla se l'utente ha già una preferenza
$check_pref = "SELECT * FROM user_preferences WHERE user_id = $user_id";
$pref_result = $conn->query($check_pref);

if ($pref_result->num_rows == 0) {
    // Inserisci la preferenza predefinita
    $insert_pref = "INSERT INTO user_preferences (user_id, theme) VALUES ($user_id, 'default')";
    $conn->query($insert_pref);
    $current_theme = 'default';
} else {
    $pref = $pref_result->fetch_assoc();
    $current_theme = $pref['theme'];
}

// Gestione del cambio tema
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['theme'])) {
    $new_theme = clean_input($_POST['theme']);
    
    // Aggiorna la preferenza dell'utente
    $update_pref = "UPDATE user_preferences SET theme = '$new_theme' WHERE user_id = $user_id";
    
    if ($conn->query($update_pref) === TRUE) {
        $message = "Tema aggiornato con successo a '$new_theme'.";
        $current_theme = $new_theme;
    } else {
        $error = "Errore nell'aggiornamento del tema: " . $conn->error;
    }
}

// Definizione dei temi disponibili
$themes = [
    'default' => [
        'name' => 'Default FBI',
        'description' => 'Tema predefinito in stile FBI con colori blu scuro e accenti blu.',
        'preview' => 'theme_default.jpg',
        'colors' => [
            'bg-dark' => '#0d1117',
            'accent-color' => '#003366',
            'text-primary' => '#c9d1d9'
        ]
    ],
    'dark-red' => [
        'name' => 'Dark Red',
        'description' => 'Tema scuro con accenti rossi, ispirato alle operazioni notturne.',
        'preview' => 'theme_dark_red.jpg',
        'colors' => [
            'bg-dark' => '#0a0000',
            'accent-color' => '#990000',
            'text-primary' => '#e6e6e6'
        ]
    ],
    'cyber-green' => [
        'name' => 'Cyber Green',
        'description' => 'Tema ispirato ai film di hacking con predominanza di verde su sfondo scuro.',
        'preview' => 'theme_cyber_green.jpg',
        'colors' => [
            'bg-dark' => '#001a00',
            'accent-color' => '#00cc66',
            'text-primary' => '#ccffcc'
        ]
    ],
    'midnight-purple' => [
        'name' => 'Midnight Purple',
        'description' => 'Elegante tema viola scuro per operazioni di intelligence.',
        'preview' => 'theme_midnight_purple.jpg',
        'colors' => [
            'bg-dark' => '#0d0033',
            'accent-color' => '#6600cc',
            'text-primary' => '#e6e6ff'
        ]
    ],
    'tactical-orange' => [
        'name' => 'Tactical Orange',
        'description' => 'Tema tattico con accenti arancioni su sfondo scuro.',
        'preview' => 'theme_tactical_orange.jpg',
        'colors' => [
            'bg-dark' => '#1a1000',
            'accent-color' => '#cc7700',
            'text-primary' => '#ffe6cc'
        ]
    ],
    'arctic-blue' => [
        'name' => 'Arctic Blue',
        'description' => 'Tema chiaro con tonalità di blu ghiaccio, per un aspetto moderno e pulito.',
        'preview' => 'theme_arctic_blue.jpg',
        'colors' => [
            'bg-dark' => '#e6f2ff',
            'accent-color' => '#0077cc',
            'text-primary' => '#003366'
        ]
    ]
];

// Funzione per generare il CSS del tema
function generateThemeCSS($theme_key, $themes) {
    if (!isset($themes[$theme_key])) {
        return '';
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

// Genera il CSS per il tema corrente
$theme_css = generateThemeCSS($current_theme, $themes);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIARIO - Gestione Temi</title>
    <link rel="stylesheet" href="fbi-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Fira+Code:wght@400;500;600&display=swap">
    <?php echo $theme_css; ?>
    <style>
        .themes-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .theme-card {
            background-color: var(--bg-darker);
            border: 1px solid var(--border-color);
            border-radius: 5px;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .theme-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        .theme-preview {
            height: 150px;
            background-color: var(--bg-light);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .theme-preview-inner {
            width: 90%;
            height: 80%;
            border-radius: 5px;
            position: relative;
        }
        
        .theme-preview-sidebar {
            position: absolute;
            left: 0;
            top: 0;
            width: 20%;
            height: 100%;
            background-color: var(--bg-darker);
            border-right: 1px solid var(--border-color);
        }
        
        .theme-preview-content {
            position: absolute;
            right: 0;
            top: 0;
            width: 80%;
            height: 100%;
            background-color: var(--bg-dark);
            display: flex;
            flex-direction: column;
        }
        
        .theme-preview-header {
            height: 20%;
            background-color: var(--accent-color);
            opacity: 0.8;
        }
        
        .theme-preview-body {
            height: 80%;
            padding: 5px;
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .theme-preview-card {
            width: 45%;
            height: 30px;
            background-color: var(--bg-darker);
            border: 1px solid var(--border-color);
            border-radius: 3px;
        }
        
        .theme-info {
            padding: 15px;
        }
        
        .theme-info h3 {
            margin: 0 0 10px 0;
            color: var(--text-primary);
            font-size: 1.2rem;
        }
        
        .theme-info p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .theme-colors {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .color-swatch {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
        }
        
        .theme-actions {
            display: flex;
            justify-content: space-between;
        }
        
        .current-theme-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--accent-color);
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
            font-size: 0.8rem;
            font-weight: 500;
            z-index: 1;
        }
        
        .theme-description {
            margin-top: 30px;
            background-color: var(--bg-darker);
            padding: 20px;
            border-radius: 5px;
            border: 1px solid var(--border-color);
        }
        
        .theme-description h2 {
            margin-top: 0;
            color: var(--text-primary);
            font-size: 1.5rem;
            margin-bottom: 15px;
        }
        
        .theme-description p {
            color: var(--text-secondary);
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="user-info">
            <h2><?php echo $_SESSION['username']; ?></h2>
            <p><?php echo isAdmin() ? 'ADMIN LEVEL' : 'OPERATOR LEVEL'; ?></p>
        </div>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="diary.php"><i class="fas fa-book"></i> Diario Operativo</a></li>
            <?php if (isAdmin()): ?>
                <li><a href="users.php"><i class="fas fa-users"></i> Gestione Operatori</a></li>
                <li><a href="ragazzi.php"><i class="fas fa-child"></i> Soggetti</a></li>
                <li><a href="database_admin.php"><i class="fas fa-database"></i> Database</a></li>
            <?php endif; ?>
            <li><a href="documenti.php"><i class="fas fa-folder"></i> Archivio Documenti</a></li>
            <li><a href="punishments.php"><i class="fas fa-gavel"></i> Sanzioni</a></li>
            <li><a href="permissions.php"><i class="fas fa-check-circle"></i> Autorizzazioni</a></li>
            <li><a href="themes.php" class="active"><i class="fas fa-paint-brush"></i> Temi</a></li>
            <li><a href="archive.php"><i class="fas fa-archive"></i> Archivio Storico</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Disconnessione</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1 class="page-title"><i class="fas fa-paint-brush"></i> Gestione Temi</h1>
        
        <?php if ($message): ?>
            <div class="success-message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="theme-description">
            <h2>Personalizza la tua esperienza</h2>
            <p>Scegli tra i diversi temi disponibili per personalizzare l'aspetto dell'applicazione. Ogni tema offre un'esperienza visiva unica mantenendo la funzionalità e l'usabilità del sistema. Il tema selezionato sarà applicato a tutte le pagine dell'applicazione.</p>
        </div>
        
        <div class="themes-container">
            <?php foreach ($themes as $theme_key => $theme): ?>
                <div class="theme-card">
                    <?php if ($theme_key === $current_theme): ?>
                        <div class="current-theme-badge">Tema Attuale</div>
                    <?php endif; ?>
                    
                    <div class="theme-preview">
                        <div class="theme-preview-inner">
                            <div class="theme-preview-sidebar"></div>
                            <div class="theme-preview-content">
                                <div class="theme-preview-header"></div>
                                <div class="theme-preview-body">
                                    <div class="theme-preview-card"></div>
                                    <div class="theme-preview-card"></div>
                                    <div class="theme-preview-card"></div>
                                    <div class="theme-preview-card"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="theme-info">
                        <h3><?php echo $theme['name']; ?></h3>
                        <p><?php echo $theme['description']; ?></p>
                        
                        <div class="theme-colors">
                            <div class="color-swatch" style="background-color: <?php echo $theme['colors']['bg-dark']; ?>"></div>
                            <div class="color-swatch" style="background-color: <?php echo $theme['colors']['accent-color']; ?>"></div>
                            <div class="color-swatch" style="background-color: <?php echo $theme['colors']['text-primary']; ?>"></div>
                        </div>
                        
                        <div class="theme-actions">
                            <?php if ($theme_key !== $current_theme): ?>
                                <form method="post" action="">
                                    <input type="hidden" name="theme" value="<?php echo $theme_key; ?>">
                                    <button type="submit" class="btn"><i class="fas fa-check"></i> Seleziona</button>
                                </form>
                            <?php else: ?>
                                <button class="btn" disabled><i class="fas fa-check"></i> Attivo</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
        // Animazione per le card dei temi
        document.addEventListener('DOMContentLoaded', function() {
            const themeCards = document.querySelectorAll('.theme-card');
            
            themeCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                
                setTimeout(function() {
                    card.style.transition = 'all 0.6s cubic-bezier(0.22, 1, 0.36, 1)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 + (index * 100));
            });
            
            // Anteprima dinamica dei temi
            themeCards.forEach(card => {
                const previewSidebar = card.querySelector('.theme-preview-sidebar');
                const previewHeader = card.querySelector('.theme-preview-header');
                const previewContent = card.querySelector('.theme-preview-content');
                const previewCards = card.querySelectorAll('.theme-preview-card');
                
                // Aggiungi effetto hover
                card.addEventListener('mouseenter', function() {
                    previewHeader.style.transition = 'all 0.3s ease';
                    previewHeader.style.height = '25%';
                    
                    previewCards.forEach((previewCard, i) => {
                        previewCard.style.transition = 'all 0.3s ease';
                        previewCard.style.transform = 'scale(1.05)';
                        previewCard.style.transitionDelay = `${i * 0.05}s`;
                    });
                });
                
                card.addEventListener('mouseleave', function() {
                    previewHeader.style.height = '20%';
                    
                    previewCards.forEach(previewCard => {
                        previewCard.style.transform = 'scale(1)';
                    });
                });
            });
        });
    </script>
</body>
</html>