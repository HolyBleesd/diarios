<?php
require_once 'config.php';

// Verifica se l'utente è loggato
if (!isLoggedIn()) {
    redirect('index.php');
}

$user_id = $_SESSION['user_id'];
$message = '';

// Gestione dell'aggiunta di una nuova cartella (solo per amministratori)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_folder' && isAdmin()) {
    $folder_name = clean_input($_POST['folder_name']);
    $start_date = isset($_POST['start_date']) ? clean_input($_POST['start_date']) : '';
    $end_date = isset($_POST['end_date']) ? clean_input($_POST['end_date']) : '';

    $sql = "INSERT INTO archive_folders (name, created_by, start_date, end_date)
            VALUES ('$folder_name', $user_id, '$start_date', '$end_date')";

    if ($conn->query($sql) === TRUE) {
        $folder_id = $conn->insert_id;

        // Aggiungi permessi per gli utenti selezionati
        if (isset($_POST['user_permissions']) && is_array($_POST['user_permissions'])) {
            foreach ($_POST['user_permissions'] as $perm_user_id) {
                $perm_user_id = (int)$perm_user_id;
                $perm_sql = "INSERT INTO folder_permissions (folder_id, user_id) VALUES ($folder_id, $perm_user_id)";
                $conn->query($perm_sql);
            }
        }

        // Se è stato selezionato "copia" o "sposta", procedi con l'operazione
        if (isset($_POST['operation']) && ($_POST['operation'] == 'copy' || $_POST['operation'] == 'move')) {
            $operation = $_POST['operation'];

            // Costruisci la query per selezionare le voci del diario nel periodo specificato
            $entries_sql = "SELECT * FROM diary_entries WHERE 1=1";

            if (!empty($start_date)) {
                $entries_sql .= " AND DATE(timestamp) >= '$start_date'";
            }

            if (!empty($end_date)) {
                $entries_sql .= " AND DATE(timestamp) <= '$end_date'";
            }

            $entries_result = $conn->query($entries_sql);

            // Copia le voci nella cartella di archivio
            while ($entry = $entries_result->fetch_assoc()) {
                $entry_user_id = $entry['user_id'];
                $entry_content = $conn->real_escape_string($entry['content']);
                $entry_timestamp = $entry['timestamp'];

                $archive_sql = "INSERT INTO archive_documents (folder_id, title, content, created_by, original_timestamp, user_id)
                                VALUES ($folder_id, 'Voce del diario', '$entry_content', $user_id, '$entry_timestamp', $entry_user_id)";
                $conn->query($archive_sql);
            }

            // Se è stato selezionato "sposta", elimina le voci originali
            if ($operation == 'move') {
                $delete_sql = "DELETE FROM diary_entries WHERE 1=1";

                if (!empty($start_date)) {
                    $delete_sql .= " AND DATE(timestamp) >= '$start_date'";
                }

                if (!empty($end_date)) {
                    $delete_sql .= " AND DATE(timestamp) <= '$end_date'";
                }

                $conn->query($delete_sql);
            }
        }

        $message = "Cartella aggiunta con successo!";
    } else {
        $message = "Errore: " . $conn->error;
    }
}

// Aggiungi le colonne start_date e end_date alla tabella archive_folders se non esistono
$check_column = "SHOW COLUMNS FROM archive_folders LIKE 'start_date'";
$column_result = $conn->query($check_column);
if ($column_result->num_rows == 0) {
    $add_column = "ALTER TABLE archive_folders ADD COLUMN start_date DATE NULL, ADD COLUMN end_date DATE NULL";
    $conn->query($add_column);
}

// Aggiungi le colonne original_timestamp e user_id alla tabella archive_documents se non esistono
$check_column = "SHOW COLUMNS FROM archive_documents LIKE 'original_timestamp'";
$column_result = $conn->query($check_column);
if ($column_result->num_rows == 0) {
    $add_column = "ALTER TABLE archive_documents ADD COLUMN original_timestamp DATETIME NULL, ADD COLUMN user_id INT NULL";
    $conn->query($add_column);
}

// Gestione dell'eliminazione di una cartella (solo per amministratori)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_folder' && isAdmin()) {
    $folder_id = (int)$_POST['folder_id'];
    
    $sql = "DELETE FROM archive_folders WHERE id = $folder_id";
    
    if ($conn->query($sql) === TRUE) {
        $message = "Cartella eliminata con successo!";
    } else {
        $message = "Errore: " . $conn->error;
    }
}

// Ottieni le cartelle dell'archivio (tutte se admin, solo quelle con permesso altrimenti)
if (isAdmin()) {
    $folders_sql = "SELECT af.*, u.username as creator_name, 
                   (SELECT COUNT(*) FROM archive_documents WHERE folder_id = af.id) as doc_count
                   FROM archive_folders af 
                   JOIN users u ON af.created_by = u.id 
                   ORDER BY af.name ASC";
} else {
    $folders_sql = "SELECT af.*, u.username as creator_name, 
                   (SELECT COUNT(*) FROM archive_documents WHERE folder_id = af.id) as doc_count
                   FROM archive_folders af 
                   JOIN users u ON af.created_by = u.id 
                   JOIN folder_permissions fp ON af.id = fp.folder_id AND fp.user_id = $user_id
                   ORDER BY af.name ASC";
}

$folders_result = $conn->query($folders_sql);

// Se è amministratore, ottieni l'elenco degli utenti per il form di aggiunta cartella
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
    <title>Diario - Archivio</title>
    <link rel="stylesheet" href="fbi-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500&family=Poppins:wght@300;400;500;600;700&display=swap">
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
            <?php endif; ?>
            <li><a href="punishments.php"><i class="fas fa-gavel"></i> Punizioni</a></li>
            <li><a href="permissions.php"><i class="fas fa-check-circle"></i> Permessi</a></li>
            <li><a href="archive.php" class="active"><i class="fas fa-archive"></i> Archivio</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Esci</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1 class="page-title"><i class="fas fa-archive"></i> Archivio</h1>

        <?php if ($message): ?>
            <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (isAdmin()): ?>
            <div class="form-container">
                <h2><i class="fas fa-folder-plus"></i> Crea Nuova Cartella di Archivio</h2>
                <form method="post" action="">
                    <input type="hidden" name="action" value="add_folder">
                    <div class="form-group">
                        <label for="folder_name"><i class="fas fa-tag"></i> Nome Cartella:</label>
                        <input type="text" id="folder_name" name="folder_name" required>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-calendar-alt"></i> Periodo di tempo da archiviare:</label>
                        <div style="display: flex; gap: 15px;">
                            <div style="flex: 1;">
                                <label for="start_date">Data inizio:</label>
                                <input type="date" id="start_date" name="start_date" class="date-picker">
                            </div>
                            <div style="flex: 1;">
                                <label for="end_date">Data fine:</label>
                                <input type="date" id="end_date" name="end_date" class="date-picker">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-exchange-alt"></i> Operazione:</label>
                        <div style="display: flex; gap: 20px; margin-top: 10px;">
                            <div>
                                <input type="radio" id="op_none" name="operation" value="none" checked>
                                <label for="op_none">Solo creazione cartella</label>
                            </div>
                            <div>
                                <input type="radio" id="op_copy" name="operation" value="copy">
                                <label for="op_copy">Copia voci del diario</label>
                            </div>
                            <div>
                                <input type="radio" id="op_move" name="operation" value="move">
                                <label for="op_move">Sposta voci del diario (elimina originali)</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-users"></i> Utenti con permesso di accesso:</label>
                        <div style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border-color); padding: 15px; border-radius: 8px; background-color: var(--bg-darker);">
                            <?php
                            // Reset del puntatore del risultato
                            $users_result->data_seek(0);
                            while($user = $users_result->fetch_assoc()):
                            ?>
                                <div style="margin-bottom: 8px;">
                                    <input type="checkbox" id="user_<?php echo $user['id']; ?>" name="user_permissions[]" value="<?php echo $user['id']; ?>">
                                    <label for="user_<?php echo $user['id']; ?>"><?php echo $user['username']; ?></label>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn"><i class="fas fa-save"></i> Crea Cartella</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <h2><i class="fas fa-folder-open"></i> Cartelle dell'Archivio</h2>

        <?php if ($folders_result->num_rows > 0): ?>
            <div class="folder-list">
                <?php while($folder = $folders_result->fetch_assoc()): ?>
                    <div class="folder">
                        <i class="fas fa-folder"></i>
                        <h3><a href="folder.php?id=<?php echo $folder['id']; ?>"><?php echo $folder['name']; ?></a></h3>
                        <p><i class="fas fa-file-alt"></i> Documenti: <?php echo $folder['doc_count']; ?></p>
                        <p><i class="fas fa-user"></i> Creato da: <?php echo $folder['creator_name']; ?></p>
                        <p><i class="fas fa-calendar-alt"></i> Data: <?php echo $folder['timestamp']; ?></p>

                        <?php if (!empty($folder['start_date']) || !empty($folder['end_date'])): ?>
                            <p><i class="fas fa-clock"></i> Periodo:
                                <?php
                                    if (!empty($folder['start_date']) && !empty($folder['end_date'])) {
                                        echo 'Dal ' . date('d/m/Y', strtotime($folder['start_date'])) . ' al ' . date('d/m/Y', strtotime($folder['end_date']));
                                    } elseif (!empty($folder['start_date'])) {
                                        echo 'Dal ' . date('d/m/Y', strtotime($folder['start_date']));
                                    } elseif (!empty($folder['end_date'])) {
                                        echo 'Fino al ' . date('d/m/Y', strtotime($folder['end_date']));
                                    }
                                ?>
                            </p>
                        <?php endif; ?>

                        <?php if (isAdmin()): ?>
                            <form method="post" action="" style="margin-top: 15px;">
                                <input type="hidden" name="action" value="delete_folder">
                                <input type="hidden" name="folder_id" value="<?php echo $folder['id']; ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questa cartella e tutti i suoi documenti?')"><i class="fas fa-trash"></i> Elimina</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="diary-entry" style="text-align: center;">
                <i class="fas fa-folder-open" style="font-size: 50px; color: var(--text-secondary); margin-bottom: 20px;"></i>
                <p>Nessuna cartella disponibile nell'archivio.</p>
                <?php if (isAdmin()): ?>
                    <p style="margin-top: 10px;">Crea una nuova cartella per iniziare ad archiviare i documenti.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animazione per le cartelle
            const folders = document.querySelectorAll('.folder');

            folders.forEach((folder, index) => {
                folder.style.opacity = '0';
                folder.style.transform = 'translateY(20px)';

                setTimeout(function() {
                    folder.style.transition = 'all 0.5s ease';
                    folder.style.opacity = '1';
                    folder.style.transform = 'translateY(0)';
                }, 100 * (index + 1));
            });

            // Validazione date
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');

            if (startDateInput && endDateInput) {
                endDateInput.addEventListener('change', function() {
                    if (startDateInput.value && endDateInput.value) {
                        if (new Date(endDateInput.value) < new Date(startDateInput.value)) {
                            alert('La data di fine non può essere precedente alla data di inizio.');
                            endDateInput.value = '';
                        }
                    }
                });

                startDateInput.addEventListener('change', function() {
                    if (startDateInput.value && endDateInput.value) {
                        if (new Date(endDateInput.value) < new Date(startDateInput.value)) {
                            alert('La data di inizio non può essere successiva alla data di fine.');
                            startDateInput.value = '';
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>