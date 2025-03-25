<?php
require_once 'config.php';

// Verifica se l'utente è loggato
if (!isLoggedIn()) {
    redirect('index.php');
}

$user_id = $_SESSION['user_id'];
$message = '';

// Verifica se è stato specificato un ID cartella
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirect('archive.php');
}

$folder_id = (int)$_GET['id'];

// Verifica se l'utente ha accesso a questa cartella
if (!isAdmin()) {
    $access_sql = "SELECT * FROM folder_permissions WHERE folder_id = $folder_id AND user_id = $user_id";
    $access_result = $conn->query($access_sql);
    
    if ($access_result->num_rows == 0) {
        // L'utente non ha accesso a questa cartella
        redirect('archive.php');
    }
}

// Ottieni informazioni sulla cartella
$folder_sql = "SELECT af.*, u.username as creator_name 
               FROM archive_folders af 
               JOIN users u ON af.created_by = u.id 
               WHERE af.id = $folder_id";
$folder_result = $conn->query($folder_sql);

if ($folder_result->num_rows == 0) {
    // La cartella non esiste
    redirect('archive.php');
}

$folder = $folder_result->fetch_assoc();

// Gestione dell'aggiunta di un nuovo documento (solo per amministratori)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_document' && isAdmin()) {
    $title = clean_input($_POST['title']);
    $content = clean_input($_POST['content']);
    
    $sql = "INSERT INTO archive_documents (folder_id, title, content, created_by) 
            VALUES ($folder_id, '$title', '$content', $user_id)";
    
    if ($conn->query($sql) === TRUE) {
        $message = "Documento aggiunto con successo!";
    } else {
        $message = "Errore: " . $conn->error;
    }
}

// Gestione dell'eliminazione di un documento (solo per amministratori)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_document' && isAdmin()) {
    $document_id = (int)$_POST['document_id'];
    
    $sql = "DELETE FROM archive_documents WHERE id = $document_id AND folder_id = $folder_id";
    
    if ($conn->query($sql) === TRUE) {
        $message = "Documento eliminato con successo!";
    } else {
        $message = "Errore: " . $conn->error;
    }
}

// Gestione dell'aggiornamento di un documento (solo per amministratori)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_document' && isAdmin()) {
    $document_id = (int)$_POST['document_id'];
    $title = clean_input($_POST['title']);
    $content = clean_input($_POST['content']);
    
    $sql = "UPDATE archive_documents SET title = '$title', content = '$content' 
            WHERE id = $document_id AND folder_id = $folder_id";
    
    if ($conn->query($sql) === TRUE) {
        $message = "Documento aggiornato con successo!";
    } else {
        $message = "Errore: " . $conn->error;
    }
}

// Ottieni i documenti nella cartella
$documents_sql = "SELECT ad.*,
                 u.username as creator_name,
                 IFNULL(u2.username, 'Utente eliminato') as original_user_name
                 FROM archive_documents ad
                 JOIN users u ON ad.created_by = u.id
                 LEFT JOIN users u2 ON ad.user_id = u2.id
                 WHERE ad.folder_id = $folder_id
                 ORDER BY IFNULL(ad.original_timestamp, ad.timestamp) DESC";
$documents_result = $conn->query($documents_sql);

// Ottieni gli utenti con permesso di accesso a questa cartella (solo per amministratori)
if (isAdmin()) {
    $permissions_sql = "SELECT fp.*, u.username 
                       FROM folder_permissions fp 
                       JOIN users u ON fp.user_id = u.id 
                       WHERE fp.folder_id = $folder_id";
    $permissions_result = $conn->query($permissions_sql);
    
    // Ottieni tutti gli utenti per il form di gestione permessi
    $users_sql = "SELECT id, username FROM users WHERE is_admin = 0 ORDER BY username ASC";
    $users_result = $conn->query($users_sql);
    
    // Crea un array di ID utenti con permesso
    $users_with_permission = array();
    while ($perm = $permissions_result->fetch_assoc()) {
        $users_with_permission[] = $perm['user_id'];
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diario - Cartella: <?php echo $folder['name']; ?></title>
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
            <?php endif; ?>
            <li><a href="punishments.php"><i class="fas fa-gavel"></i> Punizioni</a></li>
            <li><a href="permissions.php"><i class="fas fa-check-circle"></i> Permessi</a></li>
            <li><a href="archive.php" class="active"><i class="fas fa-archive"></i> Archivio</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Esci</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div style="margin-bottom: 20px;">
            <a href="archive.php" class="btn"><i class="fas fa-arrow-left"></i> Torna all'Archivio</a>
        </div>
        
        <h1 class="page-title">Cartella: <?php echo $folder['name']; ?></h1>
        <p>Creata da: <?php echo $folder['creator_name']; ?> il <?php echo $folder['timestamp']; ?></p>
        
        <?php if ($message): ?>
            <div class="success-message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if (isAdmin()): ?>
            <div class="form-container">
                <h2>Aggiungi Nuovo Documento</h2>
                <form method="post" action="">
                    <input type="hidden" name="action" value="add_document">
                    <div class="form-group">
                        <label for="title">Titolo:</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    <div class="form-group">
                        <label for="content">Contenuto:</label>
                        <textarea id="content" name="content" rows="5" required></textarea>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn">Aggiungi Documento</button>
                    </div>
                </form>
            </div>
            
            <div class="form-container" style="margin-top: 20px;">
                <h2>Gestione Permessi</h2>
                <form method="post" action="update_permissions.php">
                    <input type="hidden" name="folder_id" value="<?php echo $folder_id; ?>">
                    <div class="form-group">
                        <label>Utenti con accesso a questa cartella:</label>
                        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                            <?php while($user = $users_result->fetch_assoc()): ?>
                                <div>
                                    <input type="checkbox" id="user_<?php echo $user['id']; ?>" name="user_permissions[]" value="<?php echo $user['id']; ?>"
                                        <?php echo in_array($user['id'], $users_with_permission) ? 'checked' : ''; ?>>
                                    <label for="user_<?php echo $user['id']; ?>"><?php echo $user['username']; ?></label>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn">Aggiorna Permessi</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
        <h2>Documenti</h2>
        
        <?php if ($documents_result->num_rows > 0): ?>
            <?php
            $current_date = '';
            $prev_user = '';
            while($document = $documents_result->fetch_assoc()):
                // Determina la data da utilizzare (originale se disponibile, altrimenti timestamp di archiviazione)
                $display_timestamp = !empty($document['original_timestamp']) ? $document['original_timestamp'] : $document['timestamp'];
                $entry_date = date('Y-m-d', strtotime($display_timestamp));

                // Mostra separatore di data se cambia la data
                if ($entry_date != $current_date) {
                    $current_date = $entry_date;
                    echo '<div class="entry-divider" data-username="' . date('d F Y', strtotime($entry_date)) . '"></div>';
                }

                // Mostra separatore di utente se cambia l'utente
                if (isset($document['original_user_name']) && $document['original_user_name'] != $prev_user && $prev_user != '') {
                    echo '<div class="entry-divider" data-username="' . $document['original_user_name'] . '"></div>';
                }
                if (isset($document['original_user_name'])) {
                    $prev_user = $document['original_user_name'];
                }
            ?>
                <div class="diary-entry">
                    <?php if (!empty($document['original_timestamp'])): ?>
                        <h3><i class="fas fa-user"></i> <?php echo $document['original_user_name']; ?></h3>
                        <div class="timestamp">
                            <i class="far fa-calendar-alt"></i> Originariamente scritto il: <?php echo $document['original_timestamp']; ?>
                        </div>
                        <div class="timestamp" style="margin-top: 5px;">
                            <i class="fas fa-archive"></i> Archiviato da: <?php echo $document['creator_name']; ?> il <?php echo $document['timestamp']; ?>
                        </div>
                    <?php else: ?>
                        <h3><?php echo $document['title']; ?></h3>
                        <div class="timestamp">
                            <i class="fas fa-user-edit"></i> Creato da: <?php echo $document['creator_name']; ?> il <?php echo $document['timestamp']; ?>
                        </div>
                    <?php endif; ?>

                    <div class="content">
                        <?php
                            $lines = explode("\n", htmlspecialchars($document['content']));
                            foreach ($lines as $line_num => $line) {
                                if (!empty($document['original_user_name'])) {
                                    echo '<div class="line-numbers"><span class="user-prefix">' . $document['original_user_name'] . ':</span> ' . nl2br($line) . '</div>';
                                } else {
                                    echo '<div class="line-numbers">' . nl2br($line) . '</div>';
                                }
                            }
                        ?>
                    </div>

                    <?php if (isAdmin()): ?>
                        <div style="margin-top: 20px;">
                            <button class="btn" onclick="toggleEditForm(<?php echo $document['id']; ?>)"><i class="fas fa-edit"></i> Modifica</button>

                            <form method="post" action="" style="display: inline; margin-left: 10px;">
                                <input type="hidden" name="action" value="delete_document">
                                <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questo documento?')"><i class="fas fa-trash"></i> Elimina</button>
                            </form>

                            <div id="edit-form-<?php echo $document['id']; ?>" style="display: none; margin-top: 20px;">
                                <form method="post" action="">
                                    <input type="hidden" name="action" value="update_document">
                                    <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                                    <div class="form-group">
                                        <label><i class="fas fa-heading"></i> Titolo:</label>
                                        <input type="text" name="title" value="<?php echo $document['title']; ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label><i class="fas fa-file-alt"></i> Contenuto:</label>
                                        <textarea name="content" rows="5" required><?php echo $document['content']; ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <button type="submit" class="btn"><i class="fas fa-save"></i> Aggiorna</button>
                                        <button type="button" class="btn" onclick="toggleEditForm(<?php echo $document['id']; ?>)"><i class="fas fa-times"></i> Annulla</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>Nessun documento in questa cartella.</p>
        <?php endif; ?>
    </div>

    <script>
        function toggleEditForm(documentId) {
            var form = document.getElementById('edit-form-' + documentId);
            if (form.style.display === 'none') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }
    </script>
</body>
</html>