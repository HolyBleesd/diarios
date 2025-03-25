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

// Verifica se c'è un messaggio di successo da mostrare
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $theme_name = isset($themes[$current_theme]) ? $themes[$current_theme]['name'] : 'personalizzato';
    $message = "Tema aggiornato con successo a '$theme_name'. Le modifiche sono state applicate.";
}

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

    // Verifica se il tema esiste
    if (array_key_exists($new_theme, $themes)) {
        // Controlla se l'utente ha già una preferenza
        $check_pref = "SELECT * FROM user_preferences WHERE user_id = $user_id";
        $pref_result = $conn->query($check_pref);

        if ($pref_result->num_rows == 0) {
            // Inserisci la preferenza
            $insert_pref = "INSERT INTO user_preferences (user_id, theme) VALUES ($user_id, '$new_theme')";

            if ($conn->query($insert_pref) === TRUE) {
                $message = "Tema impostato con successo a '" . $themes[$new_theme]['name'] . "'.";
                $current_theme = $new_theme;

                // Aggiorna la sessione
                $_SESSION['user_theme'] = $new_theme;

                // Reindirizza per ricaricare la pagina con il nuovo tema
                header("Location: tema_sito.php?success=1");
                exit;
            } else {
                $error = "Errore nell'impostazione del tema: " . $conn->error;
            }
        } else {
            // Aggiorna la preferenza dell'utente
            $update_pref = "UPDATE user_preferences SET theme = '$new_theme' WHERE user_id = $user_id";

            if ($conn->query($update_pref) === TRUE) {
                $message = "Tema aggiornato con successo a '" . $themes[$new_theme]['name'] . "'.";
                $current_theme = $new_theme;

                // Aggiorna la sessione
                $_SESSION['user_theme'] = $new_theme;

                // Reindirizza per ricaricare la pagina con il nuovo tema
                header("Location: tema_sito.php?success=1");
                exit;
            } else {
                $error = "Errore nell'aggiornamento del tema: " . $conn->error;
            }
        }
    } else {
        $error = "Il tema selezionato non esiste.";
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
    <title>DIARIO - Tema del Sito</title>
    <link rel="stylesheet" href="fbi-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Fira+Code:wght@400;500;600&display=swap">
    <?php echo $theme_css; ?>
    <style>
        .themes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .theme-card {
            background-color: var(--bg-darker);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            position: relative;
            border: 1px solid var(--border-color);
        }
        
        .theme-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        
        .theme-preview {
            height: 160px;
            position: relative;
            overflow: hidden;
        }
        
        .theme-preview-inner {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
        }
        
        .theme-preview-sidebar {
            width: 25%;
            height: 100%;
            background-color: var(--bg-darker);
            border-right: 1px solid var(--border-color);
        }
        
        .theme-preview-content {
            width: 75%;
            height: 100%;
            background-color: var(--bg-dark);
            padding: 10px;
            display: flex;
            flex-direction: column;
        }
        
        .theme-preview-header {
            height: 30px;
            background-color: var(--accent-color);
            margin-bottom: 10px;
            border-radius: 4px;
        }
        
        .theme-preview-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            flex-grow: 1;
        }
        
        .theme-preview-card {
            width: calc(50% - 5px);
            height: 30px;
            background-color: var(--bg-darker);
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }
        
        .theme-info {
            padding: 20px;
        }
        
        .theme-info h3 {
            margin: 0 0 15px 0;
            color: var(--text-primary);
            font-size: 1.4rem;
        }
        
        .theme-info p {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .theme-colors {
            display: flex;
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .color-swatch {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
        }
        
        .theme-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .current-theme-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: var(--accent-color);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            z-index: 1;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        
        .page-description {
            background-color: var(--bg-darker);
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }
        
        .page-description h2 {
            margin-top: 0;
            color: var(--text-primary);
            font-size: 1.8rem;
            margin-bottom: 15px;
        }
        
        .page-description p {
            color: var(--text-secondary);
            line-height: 1.6;
            font-size: 1.1rem;
        }
        
        .theme-select-btn {
            padding: 12px 20px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .theme-select-btn:hover {
            background-color: var(--accent-hover);
            transform: translateY(-2px);
        }
        
        .theme-select-btn:disabled {
            background-color: var(--bg-light);
            color: var(--text-secondary);
            cursor: not-allowed;
            transform: none;
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
            <li><a href="tema_sito.php" class="active"><i class="fas fa-palette"></i> Tema del Sito</a></li>
            <li><a href="archive.php"><i class="fas fa-archive"></i> Archivio Storico</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Disconnessione</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1 class="page-title"><i class="fas fa-palette"></i> Tema del Sito</h1>
        
        <?php if ($message): ?>
            <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="page-description">
            <h2>Personalizza l'aspetto del sistema</h2>
            <p>Scegli tra i diversi temi disponibili per personalizzare l'aspetto visivo dell'applicazione. Ogni tema offre un'esperienza visiva unica mantenendo tutte le funzionalità del sistema. Il tema selezionato sarà applicato immediatamente a tutte le pagine.</p>
        </div>
        
        <div class="themes-grid">
            <?php foreach ($themes as $theme_key => $theme): ?>
                <div class="theme-card">
                    <?php if ($theme_key === $current_theme): ?>
                        <div class="current-theme-badge"><i class="fas fa-check"></i> Tema Attuale</div>
                    <?php endif; ?>
                    
                    <div class="theme-preview">
                        <div class="theme-preview-inner" style="background-color: <?php echo $theme['colors']['bg-dark']; ?>;">
                            <div class="theme-preview-sidebar" style="background-color: <?php echo adjustBrightness($theme['colors']['bg-dark'], -10); ?>;"></div>
                            <div class="theme-preview-content">
                                <div class="theme-preview-header" style="background-color: <?php echo $theme['colors']['accent-color']; ?>;"></div>
                                <div class="theme-preview-cards">
                                    <div class="theme-preview-card" style="background-color: <?php echo adjustBrightness($theme['colors']['bg-dark'], -5); ?>;"></div>
                                    <div class="theme-preview-card" style="background-color: <?php echo adjustBrightness($theme['colors']['bg-dark'], -5); ?>;"></div>
                                    <div class="theme-preview-card" style="background-color: <?php echo adjustBrightness($theme['colors']['bg-dark'], -5); ?>;"></div>
                                    <div class="theme-preview-card" style="background-color: <?php echo adjustBrightness($theme['colors']['bg-dark'], -5); ?>;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="theme-info">
                        <h3><?php echo $theme['name']; ?></h3>
                        <p><?php echo $theme['description']; ?></p>
                        
                        <div class="theme-colors">
                            <div class="color-swatch" style="background-color: <?php echo $theme['colors']['bg-dark']; ?>" title="Colore di sfondo"></div>
                            <div class="color-swatch" style="background-color: <?php echo $theme['colors']['accent-color']; ?>" title="Colore di accento"></div>
                            <div class="color-swatch" style="background-color: <?php echo $theme['colors']['text-primary']; ?>" title="Colore del testo"></div>
                        </div>
                        
                        <div class="theme-actions">
                            <?php if ($theme_key !== $current_theme): ?>
                                <form method="post" action="" onsubmit="return confirm('Vuoi impostare il tema <?php echo $theme['name']; ?>?');">
                                    <input type="hidden" name="theme" value="<?php echo $theme_key; ?>">
                                    <button type="submit" class="theme-select-btn"><i class="fas fa-check"></i> Seleziona Tema</button>
                                </form>
                            <?php else: ?>
                                <button class="theme-select-btn" disabled><i class="fas fa-check"></i> Tema Attivo</button>
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
            
            // Effetti hover per le anteprime dei temi
            themeCards.forEach(card => {
                const preview = card.querySelector('.theme-preview-inner');
                const cards = card.querySelectorAll('.theme-preview-card');
                
                card.addEventListener('mouseenter', function() {
                    preview.style.transition = 'all 0.3s ease';
                    preview.style.transform = 'scale(1.05)';
                    
                    cards.forEach((previewCard, i) => {
                        previewCard.style.transition = 'all 0.3s ease';
                        previewCard.style.transform = 'translateY(-3px)';
                        previewCard.style.boxShadow = '0 3px 5px rgba(0, 0, 0, 0.2)';
                        previewCard.style.transitionDelay = `${i * 0.05}s`;
                    });
                });
                
                card.addEventListener('mouseleave', function() {
                    preview.style.transform = 'scale(1)';
                    
                    cards.forEach(previewCard => {
                        previewCard.style.transform = 'translateY(0)';
                        previewCard.style.boxShadow = 'none';
                    });
                });
            });
        });
    </script>
</body>
</html>