<?php
require_once 'config.php';

// Verifica se l'utente è loggato
if (!isLoggedIn()) {
    redirect('index.php');
}

$user_id = $_SESSION['user_id'];
$message = '';

// Verifica se la tabella permissions esiste, altrimenti creala
$check_table = "SHOW TABLES LIKE 'permissions'";
$table_result = $conn->query($check_table);
if ($table_result->num_rows == 0) {
    $create_table = "CREATE TABLE IF NOT EXISTS permissions (
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
    $conn->query($create_table);
}

// Gestione dell'aggiunta di un nuovo permesso
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    $description = clean_input($_POST['description']);
    $reason = clean_input($_POST['reason']);
    $duration = clean_input($_POST['duration']);
    $timestamp = getCurrentTimestamp();
    
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
            $sql = "INSERT INTO permissions (user_id, description, reason, duration, created_by, timestamp)
                    VALUES ($target_user_id, '$description', '$reason', '$duration', $user_id, '$timestamp')";
        } else {
            // Se l'utente non esiste, usiamo l'ID dell'utente corrente ma salviamo il nome manuale
            $sql = "INSERT INTO permissions (user_id, manual_username, description, reason, duration, created_by, timestamp)
                    VALUES ($user_id, '$manual_username', '$description', '$reason', '$duration', $user_id, '$timestamp')";
        }
                
        if ($conn->query($sql) === TRUE) {
            $message = "Permesso aggiunto con successo!";
        } else {
            $message = "Errore: " . $conn->error;
        }
    }
}

// Gestione dell'eliminazione di un permesso
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $permission_id = (int)$_POST['permission_id'];
    
    // Verifica se l'utente è l'amministratore o il creatore del permesso
    $check_permission = "SELECT created_by FROM permissions WHERE id = $permission_id";
    $permission_result = $conn->query($check_permission);
    
    if ($permission_result->num_rows > 0) {
        $permission_data = $permission_result->fetch_assoc();
        
        if (isAdmin() || $permission_data['created_by'] == $user_id) {
            $sql = "DELETE FROM permissions WHERE id = $permission_id";
            
            if ($conn->query($sql) === TRUE) {
                $message = "Permesso eliminato con successo!";
            } else {
                $message = "Errore: " . $conn->error;
            }
        } else {
            $message = "Errore: Non hai i permessi per eliminare questo record.";
        }
    } else {
        $message = "Errore: Permesso non trovato.";
    }
}

// Ottieni tutti i permessi
$sql = "SELECT p.*, a.username as admin_name,
        CASE 
            WHEN p.manual_username IS NOT NULL THEN p.manual_username
            ELSE u.username
        END as user_name
        FROM permissions p
        LEFT JOIN users u ON p.user_id = u.id
        JOIN users a ON p.created_by = a.id
        ORDER BY p.timestamp DESC";

$result = $conn->query($sql);

// Aggiungi la colonna reason se non esiste
$check_column = "SHOW COLUMNS FROM permissions LIKE 'reason'";
$column_result = $conn->query($check_column);
if ($column_result->num_rows == 0) {
    $add_column = "ALTER TABLE permissions ADD COLUMN reason TEXT NULL AFTER description";
    $conn->query($add_column);
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIARIO - Autorizzazioni</title>
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
            <li><a href="diary.php"><i class="fas fa-book"></i> Diario Operativo</a></li>
            <?php if (isAdmin()): ?>
                <li><a href="users.php"><i class="fas fa-users"></i> Gestione Operatori</a></li>
                <li><a href="ragazzi.php"><i class="fas fa-child"></i> Soggetti</a></li>
                <li><a href="database_admin.php"><i class="fas fa-database"></i> Database</a></li>
            <?php endif; ?>
            <li><a href="documenti.php"><i class="fas fa-folder"></i> Archivio Documenti</a></li>
            <li><a href="punishments.php"><i class="fas fa-gavel"></i> Sanzioni</a></li>
            <li><a href="permissions.php" class="active"><i class="fas fa-check-circle"></i> Autorizzazioni</a></li>
            <li><a href="tema_sito.php"><i class="fas fa-palette"></i> Tema del Sito</a></li>
            <li><a href="archive.php"><i class="fas fa-archive"></i> Archivio Storico</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Disconnessione</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1 class="page-title"><i class="fas fa-check-circle"></i> Registro Autorizzazioni</h1>

        <?php if ($message): ?>
            <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>

        <div class="form-container">
            <h2><i class="fas fa-plus-circle"></i> Aggiungi Nuova Autorizzazione</h2>
            <form id="permissionForm" method="post" action="">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label for="manual_username"><i class="fas fa-user-edit"></i> Nome utente:</label>
                    <input type="text" id="manual_username" name="manual_username" placeholder="Inserisci il nome dell'utente" required>
                </div>

                <div class="form-group">
                    <label for="reason"><i class="fas fa-exclamation-circle"></i> Motivo della richiesta:</label>
                    <textarea id="reason" name="reason" rows="3" placeholder="Descrivi perché è stato richiesto questo permesso..." required></textarea>
                </div>

                <div class="form-group">
                    <label for="description"><i class="fas fa-edit"></i> Descrizione del permesso:</label>
                    <textarea id="description" name="description" rows="3" placeholder="Descrivi il permesso..." required></textarea>
                </div>

                <div class="form-group">
                    <label for="duration"><i class="fas fa-clock"></i> Durata del permesso:</label>
                    <input type="text" id="duration" name="duration" placeholder="Es: 3 giorni, 1 settimana, ecc." required>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn"><i class="fas fa-save"></i> Aggiungi Permesso</button>
                </div>
            </form>
        </div>

        <div id="autosave-status" class="autosave-status" style="display: none;">
            <span id="autosave-icon"></span>
            <span id="autosave-text">Pronto per scrivere</span>
        </div>

        <h2><i class="fas fa-list"></i> Registro Completo dei Permessi</h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th><i class="fas fa-user"></i> Utente</th>
                    <th><i class="fas fa-exclamation-circle"></i> Motivo</th>
                    <th><i class="fas fa-check-circle"></i> Permesso</th>
                    <th><i class="fas fa-hourglass-half"></i> Durata</th>
                    <th><i class="fas fa-user-shield"></i> Concesso da</th>
                    <th><i class="fas fa-calendar-alt"></i> Data e Ora</th>
                    <th><i class="fas fa-cogs"></i> Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($permission = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $permission['user_name']; ?></td>
                            <td><?php echo nl2br(htmlspecialchars($permission['reason'] ?? 'Non specificato')); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($permission['description'])); ?></td>
                            <td><?php echo isset($permission['duration']) ? $permission['duration'] : 'Non specificato'; ?></td>
                            <td><?php echo $permission['admin_name']; ?></td>
                            <td><?php echo $permission['timestamp']; ?></td>
                            <td>
                                <?php if (isAdmin() || $permission['created_by'] == $user_id): ?>
                                    <form method="post" action="">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="permission_id" value="<?php echo $permission['id']; ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questa autorizzazione?')"><i class="fas fa-trash"></i> Elimina</button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted">Nessuna azione disponibile</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">Nessuna autorizzazione trovata.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Nascondi lo stato di autosalvataggio
            const autosaveStatus = document.getElementById('autosave-status');
            if (autosaveStatus) {
                autosaveStatus.style.display = 'none';
            }
        });
    </script>
</body>
</html><?php
require_once 'config.php';

// Verifica se l'utente è loggato
if (!isLoggedIn()) {
    redirect('index.php');
}

$user_id = $_SESSION['user_id'];
$message = '';

// Gestione dell'aggiunta di un nuovo permesso (solo per amministratori)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add' && isAdmin()) {
    $target_user_id = (int)$_POST['user_id'];
    $description = clean_input($_POST['description']);
    $timestamp = getCurrentTimestamp();
    
    $sql = "INSERT INTO permissions (user_id, description, created_by, timestamp) 
            VALUES ($target_user_id, '$description', $user_id, '$timestamp')";
    
    if ($conn->query($sql) === TRUE) {
        $message = "Permesso aggiunto con successo!";
    } else {
        $message = "Errore: " . $conn->error;
    }
}

// Gestione dell'eliminazione di un permesso (solo per amministratori)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete' && isAdmin()) {
    $permission_id = (int)$_POST['permission_id'];
    
    $sql = "DELETE FROM permissions WHERE id = $permission_id";
    
    if ($conn->query($sql) === TRUE) {
        $message = "Permesso eliminato con successo!";
    } else {
        $message = "Errore: " . $conn->error;
    }
}

// Ottieni i permessi (tutti se admin, solo quelli dell'utente corrente altrimenti)
if (isAdmin()) {
    $sql = "SELECT p.*, u.username as user_name, a.username as admin_name 
            FROM permissions p 
            JOIN users u ON p.user_id = u.id 
            JOIN users a ON p.created_by = a.id 
            ORDER BY p.timestamp DESC";
} else {
    $sql = "SELECT p.*, a.username as admin_name 
            FROM permissions p 
            JOIN users a ON p.created_by = a.id 
            WHERE p.user_id = $user_id 
            ORDER BY p.timestamp DESC";
}

$result = $conn->query($sql);

// Se è amministratore, ottieni l'elenco degli utenti per il form di aggiunta
if (isAdmin()) {
    $users_sql = "SELECT id, username FROM users WHERE is_admin = 0 ORDER BY username ASC";
    $users_result = $conn->query($users_sql);
}
?>

        
        <?php if ($message): ?>
            <div class="success-message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (isAdmin()): ?>
            <div class="form-container">
                <h2>Aggiungi Nuovo Permesso</h2>
                <form method="post" action="">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label for="user_id">Utente:</label>
                        <select id="user_id" name="user_id" required>
                            <option value="">Seleziona un utente</option>
                            <?php while($user = $users_result->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo $user['username']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="description">Descrizione del permesso:</label>
                        <textarea id="description" name="description" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn">Aggiungi Permesso</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <h2>Elenco Permessi</h2>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <?php if (isAdmin()): ?>
                        <th>Utente</th>
                    <?php endif; ?>
                    <th>Descrizione</th>
                    <th>Amministratore</th>
                    <th>Data</th>
                    <?php if (isAdmin()): ?>
                        <th>Azioni</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($permission = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $permission['id']; ?></td>
                            <?php if (isAdmin()): ?>
                                <td><?php echo $permission['user_name']; ?></td>
                            <?php endif; ?>
                            <td><?php echo nl2br(htmlspecialchars($permission['description'])); ?></td>
                            <td><?php echo $permission['admin_name']; ?></td>
                            <td><?php echo $permission['timestamp']; ?></td>
                            <?php if (isAdmin()): ?>
                                <td>
                                    <form method="post" action="">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="permission_id" value="<?php echo $permission['id']; ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questo permesso?')">Elimina</button>
                                    </form>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo isAdmin() ? '6' : '4'; ?>">Nessun permesso trovato.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>