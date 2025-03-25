<?php
require_once 'config.php';

// Verifica se l'utente è loggato e amministratore
if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

$message = '';

// Verifica se la tabella punishments esiste
$check_table = "SHOW TABLES LIKE 'punishments'";
$table_result = $conn->query($check_table);
if ($table_result->num_rows == 0) {
    // Crea la tabella punishments
    $create_table = "CREATE TABLE IF NOT EXISTS punishments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        manual_username VARCHAR(100) NULL,
        description TEXT NOT NULL,
        reason TEXT NULL,
        duration VARCHAR(100) DEFAULT 'Non specificato',
        created_by INT NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )";
    
    if ($conn->query($create_table) === TRUE) {
        $message .= "Tabella punishments creata con successo.<br>";
    } else {
        $message .= "Errore nella creazione della tabella: " . $conn->error . "<br>";
    }
} else {
    $message .= "La tabella punishments esiste già.<br>";
}

// Aggiungi la colonna reason se non esiste
$check_column = "SHOW COLUMNS FROM punishments LIKE 'reason'";
$column_result = $conn->query($check_column);
if ($column_result->num_rows == 0) {
    $add_column = "ALTER TABLE punishments ADD COLUMN reason TEXT NULL AFTER description";
    if ($conn->query($add_column) === TRUE) {
        $message .= "Colonna reason aggiunta con successo.<br>";
    } else {
        $message .= "Errore nell'aggiunta della colonna: " . $conn->error . "<br>";
    }
} else {
    $message .= "La colonna reason esiste già.<br>";
}

// Aggiungi la colonna duration se non esiste
$check_column = "SHOW COLUMNS FROM punishments LIKE 'duration'";
$column_result = $conn->query($check_column);
if ($column_result->num_rows == 0) {
    $add_column = "ALTER TABLE punishments ADD COLUMN duration VARCHAR(100) DEFAULT 'Non specificato'";
    if ($conn->query($add_column) === TRUE) {
        $message .= "Colonna duration aggiunta con successo.<br>";
    } else {
        $message .= "Errore nell'aggiunta della colonna: " . $conn->error . "<br>";
    }
} else {
    $message .= "La colonna duration esiste già.<br>";
}

// Aggiungi la colonna manual_username se non esiste
$check_column = "SHOW COLUMNS FROM punishments LIKE 'manual_username'";
$column_result = $conn->query($check_column);
if ($column_result->num_rows == 0) {
    $add_column = "ALTER TABLE punishments ADD COLUMN manual_username VARCHAR(100) NULL AFTER user_id";
    if ($conn->query($add_column) === TRUE) {
        $message .= "Colonna manual_username aggiunta con successo.<br>";
    } else {
        $message .= "Errore nell'aggiunta della colonna: " . $conn->error . "<br>";
    }
} else {
    $message .= "La colonna manual_username esiste già.<br>";
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diario - Fix Tabella Punizioni</title>
    <link rel="stylesheet" href="fbi-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="sidebar">
        <div class="user-info">
            <h2><?php echo $_SESSION['username']; ?></h2>
            <p><?php echo isAdmin() ? 'Amministratore' : 'Utente'; ?></p>
        </div>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="diary.php"><i class="fas fa-book"></i> Diario Personale</a></li>
            <?php if (isAdmin()): ?>
                <li><a href="users.php"><i class="fas fa-users"></i> Gestione Utenti</a></li>
                <li><a href="ragazzi.php"><i class="fas fa-child"></i> Ragazzi</a></li>
                <li><a href="documenti.php"><i class="fas fa-folder"></i> Documenti</a></li>
            <?php endif; ?>
            <li><a href="punishments.php"><i class="fas fa-gavel"></i> Punizioni</a></li>
            <li><a href="permissions.php"><i class="fas fa-check-circle"></i> Permessi</a></li>
            <li><a href="archive.php"><i class="fas fa-archive"></i> Archivio</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Esci</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1 class="page-title"><i class="fas fa-wrench"></i> Fix Tabella Punizioni</h1>
        
        <div class="success-message">
            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
        </div>
        
        <div class="action-buttons">
            <a href="punishments.php" class="btn"><i class="fas fa-arrow-left"></i> Torna alle Punizioni</a>
        </div>
    </div>
</body>
</html>