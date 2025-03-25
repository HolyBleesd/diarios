<?php
require_once 'config.php';

// Verifica se l'utente è loggato e se è amministratore
if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

$message = '';
$error = '';

// Funzione per rinominare i file
function renameFile($oldName, $newName) {
    if (file_exists($oldName)) {
        if (file_exists($newName)) {
            return "Il file $newName esiste già. Impossibile rinominare.";
        }

        if (rename($oldName, $newName)) {
            return "File $oldName rinominato in $newName con successo.";
        } else {
            return "Errore nel rinominare il file $oldName.";
        }
    } else {
        return "Il file $oldName non esiste.";
    }
}

// Funzione per copiare i file
function copyFile($source, $destination) {
    if (file_exists($source)) {
        if (copy($source, $destination)) {
            return "File $source copiato in $destination con successo.";
        } else {
            return "Errore nel copiare il file $source.";
        }
    } else {
        return "Il file $source non esiste.";
    }
}

// Funzione per creare un backup completo
function createFullBackup() {
    $backup_dir = 'backups/style_' . date('Y-m-d_H-i-s');

    // Crea la directory dei backup se non esiste
    if (!file_exists('backups')) {
        mkdir('backups', 0755, true);
    }

    // Crea la directory per questo backup
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0755, true);
    }

    // Lista dei file da includere nel backup
    $files_to_backup = [
        'style.css',
        'fbi-style.css',
        'index.php',
        'index_new.php',
        'dashboard.php',
        'dashboard_new.php',
        'update_style.php'
    ];

    // Aggiungi tutti i file PHP
    $php_files = glob('*.php');
    $files_to_backup = array_merge($files_to_backup, $php_files);
    $files_to_backup = array_unique($files_to_backup);

    $results = [];

    // Copia tutti i file nel backup
    foreach ($files_to_backup as $file) {
        if (file_exists($file)) {
            $result = copyFile($file, $backup_dir . '/' . $file);
            $results[] = $result;
        }
    }

    return [
        'backup_dir' => $backup_dir,
        'results' => $results
    ];
}

// Backup completo
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'full_backup') {
    $backup_result = createFullBackup();
    $message .= "Backup completo creato nella directory: " . $backup_result['backup_dir'] . "<br>";
    foreach ($backup_result['results'] as $result) {
        $message .= $result . "<br>";
    }
}

// Aggiorna lo stile
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_style') {
    // Crea un backup completo prima di aggiornare
    $backup_result = createFullBackup();
    $message .= "Backup completo creato prima dell'aggiornamento: " . $backup_result['backup_dir'] . "<br>";

    // Backup del file di stile originale
    $message .= copyFile('style.css', 'style.css.bak') . "<br>";

    // Backup del file index.php originale
    $message .= copyFile('index.php', 'index.php.bak') . "<br>";

    // Backup del file dashboard.php originale
    $message .= copyFile('dashboard.php', 'dashboard.php.bak') . "<br>";

    // Aggiorna i file con i nuovi stili
    if (file_exists('index_new.php')) {
        $message .= copyFile('index_new.php', 'index.php') . "<br>";
    } else {
        $error .= "Il file index_new.php non esiste.<br>";
    }

    if (file_exists('dashboard_new.php')) {
        $message .= copyFile('dashboard_new.php', 'dashboard.php') . "<br>";
    } else {
        $error .= "Il file dashboard_new.php non esiste.<br>";
    }

    // Aggiorna i riferimenti allo stile in tutti i file PHP
    $phpFiles = glob('*.php');
    foreach ($phpFiles as $file) {
        if ($file != 'index.php' && $file != 'dashboard.php' && $file != 'update_style.php') {
            $content = file_get_contents($file);
            $updatedContent = str_replace('href="style.css"', 'href="fbi-style.css"', $content);
            $updatedContent = str_replace("href='style.css'", "href='fbi-style.css'", $updatedContent);

            // Aggiorna i font
            $updatedContent = str_replace("family=Inter:wght@400;500;600;700&family=Fira+Code:wght@400;500", "family=Montserrat:wght@400;500;600;700&family=Fira+Code:wght@400;500;600", $updatedContent);

            if ($content != $updatedContent) {
                file_put_contents($file, $updatedContent);
                $message .= "File $file aggiornato con successo.<br>";
            }
        }
    }

    $message .= "Aggiornamento dello stile completato con successo!";
}

// Ripristina lo stile originale
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'restore_style') {
    // Ripristina il file di stile originale
    if (file_exists('style.css.bak')) {
        $message .= copyFile('style.css.bak', 'style.css') . "<br>";
    } else {
        $error .= "Il backup del file style.css non esiste.<br>";
    }
    
    // Ripristina il file index.php originale
    if (file_exists('index.php.bak')) {
        $message .= copyFile('index.php.bak', 'index.php') . "<br>";
    } else {
        $error .= "Il backup del file index.php non esiste.<br>";
    }
    
    // Ripristina il file dashboard.php originale
    if (file_exists('dashboard.php.bak')) {
        $message .= copyFile('dashboard.php.bak', 'dashboard.php') . "<br>";
    } else {
        $error .= "Il backup del file dashboard.php non esiste.<br>";
    }
    
    // Aggiorna i riferimenti allo stile in tutti i file PHP
    $phpFiles = glob('*.php');
    foreach ($phpFiles as $file) {
        if ($file != 'index.php' && $file != 'dashboard.php' && $file != 'update_style.php') {
            $content = file_get_contents($file);
            $updatedContent = str_replace('href="fbi-style.css"', 'href="style.css"', $content);
            $updatedContent = str_replace("href='fbi-style.css'", "href='style.css'", $updatedContent);
            
            // Ripristina i font
            $updatedContent = str_replace("family=Montserrat:wght@400;500;600;700&family=Fira+Code:wght@400;500;600", "family=Inter:wght@400;500;600;700&family=Fira+Code:wght@400;500", $updatedContent);
            
            if ($content != $updatedContent) {
                file_put_contents($file, $updatedContent);
                $message .= "File $file ripristinato con successo.<br>";
            }
        }
    }
    
    $message .= "Ripristino dello stile originale completato con successo!";
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diario - Aggiornamento Stile</title>
    <link rel="stylesheet" href="fbi-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Fira+Code:wght@400;500;600&display=swap">
    <?php require_once 'theme_loader.php'; ?>
</head>
<body>
    <div class="sidebar">
        <div class="user-info">
            <h2><?php echo $_SESSION['username']; ?></h2>
            <p><?php echo isAdmin() ? 'ADMIN LEVEL' : 'AGENT LEVEL'; ?></p>
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
            <li><a href="tema_sito.php"><i class="fas fa-palette"></i> Tema del Sito</a></li>
            <li><a href="archive.php"><i class="fas fa-archive"></i> Archivio Storico</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Disconnessione</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1 class="page-title"><i class="fas fa-paint-brush"></i> Aggiornamento Stile</h1>
        
        <?php if ($message): ?>
            <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Informazioni</h3>
            </div>
            <div class="card-body">
                <p>Questa pagina ti permette di aggiornare lo stile dell'applicazione con un tema ispirato alle agenzie governative/FBI.</p>
                <p>L'aggiornamento modificherà l'aspetto visivo dell'applicazione, ma non influenzerà le funzionalità.</p>
                <p>Prima di procedere, verranno creati dei backup dei file originali.</p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-save"></i> Backup</h3>
            </div>
            <div class="card-body">
                <p>Crea un backup completo di tutti i file di stile e delle pagine principali prima di apportare modifiche.</p>
                <form method="post" action="">
                    <input type="hidden" name="action" value="full_backup">
                    <button type="submit" class="btn"><i class="fas fa-download"></i> Crea Backup Completo</button>
                </form>
            </div>
        </div>

        <div class="action-buttons" style="margin-top: 20px;">
            <form method="post" action="" onsubmit="return confirm('Sei sicuro di voler aggiornare lo stile dell\'applicazione?');">
                <input type="hidden" name="action" value="update_style">
                <button type="submit" class="btn"><i class="fas fa-sync"></i> Aggiorna Stile</button>
            </form>

            <form method="post" action="" onsubmit="return confirm('Sei sicuro di voler ripristinare lo stile originale?');">
                <input type="hidden" name="action" value="restore_style">
                <button type="submit" class="btn btn-danger"><i class="fas fa-undo"></i> Ripristina Stile Originale</button>
            </form>
        </div>
        
        <div class="card" style="margin-top: 30px;">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Modifiche</h3>
            </div>
            <div class="card-body">
                <ul style="list-style-type: disc; padding-left: 20px;">
                    <li>Nuovo tema ispirato alle agenzie governative/FBI</li>
                    <li>Nuovi colori e stili per tutti gli elementi dell'interfaccia</li>
                    <li>Nuovi font monospace per un aspetto più tecnico</li>
                    <li>Effetti visivi migliorati</li>
                    <li>Layout ottimizzato per una migliore esperienza utente</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>