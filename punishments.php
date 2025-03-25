<?php
require_once 'config.php';

// Verifica se l'utente è loggato
if (!isLoggedIn()) {
    redirect('index.php');
}

$user_id = $_SESSION['user_id'];
$message = '';

// Verifica se la colonna reason esiste nella tabella punishments, altrimenti creala
$check_column = "SHOW COLUMNS FROM punishments LIKE 'reason'";
$column_result = $conn->query($check_column);
if ($column_result->num_rows == 0) {
    $add_column = "ALTER TABLE punishments ADD COLUMN reason TEXT NULL AFTER description";
    $conn->query($add_column);
}

// Gestione dell'aggiunta di una nuova punizione (per tutti gli utenti)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    $description = clean_input($_POST['description']);
    $reason = clean_input($_POST['reason']);
    $duration = clean_input($_POST['duration']);
    $timestamp = getCurrentTimestamp();
    $user_type = $_POST['user_type'];

    // Utente manuale
    $manual_username = clean_input($_POST['manual_username']);
    if (empty($manual_username)) {
        $message = "Errore: Inserisci il nome dell'utente.";
    } else {
        // Cerchiamo un utente esistente con questo nome
        $check_user = "SELECT id FROM users WHERE username = '$manual_username'";
        $user_result = $conn->query($check_user);

        if ($user_result->num_rows > 0) {
            // Se l'utente esiste, usiamo il suo ID
            $user_row = $user_result->fetch_assoc();
            $target_user_id = $user_row['id'];
            $sql = "INSERT INTO punishments (user_id, description, reason, duration, created_by, timestamp)
                    VALUES ($target_user_id, '$description', '$reason', '$duration', $user_id, '$timestamp')";
        } else {
            // Se l'utente non esiste, usiamo l'ID dell'utente corrente ma salviamo il nome manuale
            $sql = "INSERT INTO punishments (user_id, manual_username, description, reason, duration, created_by, timestamp)
                    VALUES ($user_id, '$manual_username', '$description', '$reason', '$duration', $user_id, '$timestamp')";
        }

        if ($conn->query($sql) === TRUE) {
            $message = "Punizione aggiunta con successo!";
        } else {
            $message = "Errore: " . $conn->error;
        }
    }
}

// Gestione dell'eliminazione di una punizione
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $punishment_id = (int)$_POST['punishment_id'];

    // Verifica se l'utente è l'amministratore o il creatore della punizione
    $check_punishment = "SELECT created_by FROM punishments WHERE id = $punishment_id";
    $punishment_result = $conn->query($check_punishment);

    if ($punishment_result->num_rows > 0) {
        $punishment_data = $punishment_result->fetch_assoc();

        if (isAdmin() || $punishment_data['created_by'] == $user_id) {
            $sql = "DELETE FROM punishments WHERE id = $punishment_id";

            if ($conn->query($sql) === TRUE) {
                $message = "Punizione eliminata con successo!";
            } else {
                $message = "Errore: " . $conn->error;
            }
        } else {
            $message = "Errore: Non hai i permessi per eliminare questa punizione.";
        }
    } else {
        $message = "Errore: Punizione non trovata.";
    }
}

// Gestione del salvataggio automatico (AJAX)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'autosave') {
    header('Content-Type: application/json');

    $description = isset($_POST['description']) ? clean_input($_POST['description']) : '';
    $reason = isset($_POST['reason']) ? clean_input($_POST['reason']) : '';
    $duration = isset($_POST['duration']) ? clean_input($_POST['duration']) : '';
    $timestamp = getCurrentTimestamp();

    if (empty($description) || empty($duration)) {
        echo json_encode(['success' => false, 'message' => 'Dati insufficienti']);
        exit;
    }

    // Utente manuale
    if (!isset($_POST['manual_username']) || empty($_POST['manual_username'])) {
        echo json_encode(['success' => false, 'message' => 'Inserisci il nome dell\'utente']);
        exit;
    }

    $manual_username = clean_input($_POST['manual_username']);

    // Cerchiamo un utente esistente con questo nome
    $check_user = "SELECT id FROM users WHERE username = '$manual_username'";
    $user_result = $conn->query($check_user);

    if ($user_result->num_rows > 0) {
        // Se l'utente esiste, usiamo il suo ID
        $user_row = $user_result->fetch_assoc();
        $target_user_id = $user_row['id'];
        $sql = "INSERT INTO punishments (user_id, description, reason, duration, created_by, timestamp)
                VALUES ($target_user_id, '$description', '$reason', '$duration', $user_id, '$timestamp')";
    } else {
        // Se l'utente non esiste, usiamo l'ID dell'utente corrente ma salviamo il nome manuale
        $sql = "INSERT INTO punishments (user_id, manual_username, description, reason, duration, created_by, timestamp)
                VALUES ($user_id, '$manual_username', '$description', '$reason', '$duration', $user_id, '$timestamp')";
    }

    if ($conn->query($sql) === TRUE) {
        echo json_encode(['success' => true, 'message' => 'Salvato automaticamente', 'timestamp' => $timestamp]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore: ' . $conn->error]);
    }
    exit;
}

// Aggiungi la colonna duration alla tabella punishments se non esiste
$check_column = "SHOW COLUMNS FROM punishments LIKE 'duration'";
$column_result = $conn->query($check_column);
if ($column_result->num_rows == 0) {
    $add_column = "ALTER TABLE punishments ADD COLUMN duration VARCHAR(100) DEFAULT 'Non specificato'";
    $conn->query($add_column);
}

// Aggiungi la colonna manual_username alla tabella punishments se non esiste
$check_column = "SHOW COLUMNS FROM punishments LIKE 'manual_username'";
$column_result = $conn->query($check_column);
if ($column_result->num_rows == 0) {
    $add_column = "ALTER TABLE punishments ADD COLUMN manual_username VARCHAR(100) NULL";
    $conn->query($add_column);
}

// Ottieni tutte le punizioni (visibili a tutti)
$sql = "SELECT p.*, a.username as admin_name,
        CASE
            WHEN p.manual_username IS NOT NULL THEN p.manual_username
            ELSE u.username
        END as user_name
        FROM punishments p
        LEFT JOIN users u ON p.user_id = u.id
        JOIN users a ON p.created_by = a.id
        ORDER BY p.timestamp DESC";

$result = $conn->query($sql);

// Se è amministratore, ottieni l'elenco degli utenti per il form di aggiunta
if (isAdmin()) {
    $users_sql = "SELECT id, username FROM users WHERE is_admin = 0 ORDER BY username ASC";
    $users_result = $conn->query($users_sql);
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIARIO - Registro Sanzioni</title>
    <link rel="stylesheet" href="fbi-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Fira+Code:wght@400;500;600&display=swap">
    <?php require_once 'theme_loader.php'; ?>
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
            <?php endif; ?>
            <li><a href="documenti.php"><i class="fas fa-folder"></i> Archivio Documenti</a></li>
            <li><a href="punishments.php" class="active"><i class="fas fa-gavel"></i> Sanzioni</a></li>
            <li><a href="permissions.php"><i class="fas fa-check-circle"></i> Autorizzazioni</a></li>
            <li><a href="tema_sito.php"><i class="fas fa-palette"></i> Tema del Sito</a></li>
            <li><a href="archive.php"><i class="fas fa-archive"></i> Archivio Storico</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Disconnessione</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1 class="page-title"><i class="fas fa-gavel"></i> Registro Sanzioni</h1>

        <?php if ($message): ?>
            <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>

        <div class="form-container">
            <h2><i class="fas fa-plus-circle"></i> Aggiungi Nuova Punizione</h2>
            <form id="punishmentForm" method="post" action="">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label for="manual_username"><i class="fas fa-user-edit"></i> Nome utente:</label>
                    <input type="text" id="manual_username" name="manual_username" placeholder="Inserisci il nome dell'utente" required>
                    <input type="hidden" name="user_type" value="manual">
                </div>

                <div class="form-group">
                    <label for="reason"><i class="fas fa-exclamation-circle"></i> Causa della punizione:</label>
                    <textarea id="reason" name="reason" rows="3" placeholder="Descrivi perché è stata assegnata questa punizione..." required></textarea>
                </div>

                <div class="form-group">
                    <label for="description"><i class="fas fa-edit"></i> Descrizione della punizione:</label>
                    <textarea id="description" name="description" rows="3" placeholder="Descrivi la punizione..." required></textarea>
                </div>

                <div class="form-group">
                    <label for="duration"><i class="fas fa-clock"></i> Durata della punizione:</label>
                    <input type="text" id="duration" name="duration" placeholder="Es: 3 giorni, 1 settimana, ecc." required>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn"><i class="fas fa-save"></i> Aggiungi Punizione</button>
                </div>
            </form>
        </div>

        <div id="autosave-status" class="autosave-status">
            <span id="autosave-icon"></span>
            <span id="autosave-text">Pronto per scrivere</span>
        </div>

        <h2><i class="fas fa-list"></i> Registro Completo delle Punizioni</h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th><i class="fas fa-user"></i> Utente</th>
                    <th><i class="fas fa-exclamation-circle"></i> Causa</th>
                    <th><i class="fas fa-gavel"></i> Punizione</th>
                    <th><i class="fas fa-hourglass-half"></i> Durata</th>
                    <th><i class="fas fa-user-shield"></i> Amministratore</th>
                    <th><i class="fas fa-calendar-alt"></i> Data e Ora</th>
                    <th><i class="fas fa-cogs"></i> Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($punishment = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $punishment['user_name']; ?></td>
                            <td><?php echo nl2br(htmlspecialchars($punishment['reason'] ?? 'Non specificata')); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($punishment['description'])); ?></td>
                            <td><?php echo isset($punishment['duration']) ? $punishment['duration'] : 'Non specificato'; ?></td>
                            <td><?php echo $punishment['admin_name']; ?></td>
                            <td><?php echo $punishment['timestamp']; ?></td>
                            <td>
                                <?php if (isAdmin() || $punishment['created_by'] == $user_id): ?>
                                    <form method="post" action="">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="punishment_id" value="<?php echo $punishment['id']; ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questa punizione?')"><i class="fas fa-trash"></i> Elimina</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">Nessuna azione disponibile</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo isAdmin() ? '6' : '5'; ?>">Nessuna punizione trovata.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (isAdmin()): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const autosaveStatus = document.getElementById('autosave-status');

            // Nascondi lo stato di autosalvataggio poiché non lo utilizziamo
            if (autosaveStatus) {
                autosaveStatus.style.display = 'none';
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>