<?php
require_once 'config.php';

// Verifica se l'utente è loggato
if (!isLoggedIn()) {
    redirect('index.php');
}

// Gli utenti normali possono accedere solo alle proprie cartelle
$is_admin = isAdmin();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Crea la directory per i documenti se non esiste
$upload_dir = __DIR__ . '/uploads/documenti';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Verifica se le tabelle esistono, altrimenti creale
$check_folders_table = "SHOW TABLES LIKE 'folders'";
$folders_result = $conn->query($check_folders_table);
if ($folders_result->num_rows == 0) {
    $create_folders = "CREATE TABLE IF NOT EXISTS folders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        parent_id INT NULL,
        created_by INT NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (parent_id) REFERENCES folders(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )";
    $conn->query($create_folders);
}

$check_documents_table = "SHOW TABLES LIKE 'documents'";
$documents_result = $conn->query($check_documents_table);
if ($documents_result->num_rows == 0) {
    $create_documents = "CREATE TABLE IF NOT EXISTS documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        file_path VARCHAR(255) NULL,
        file_type VARCHAR(50) NULL,
        ragazzo_id INT NULL,
        folder_id INT NULL,
        created_by INT NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (ragazzo_id) REFERENCES ragazzi(id) ON DELETE SET NULL,
        FOREIGN KEY (folder_id) REFERENCES folders(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )";
    $conn->query($create_documents);
}

// Gestione dell'aggiunta di una nuova cartella
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_folder') {
    $folder_name = clean_input($_POST['folder_name']);
    $parent_id = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : 'NULL';
    
    $sql = "INSERT INTO folders (name, parent_id, created_by) VALUES ('$folder_name', $parent_id, $user_id)";
    
    if ($conn->query($sql) === TRUE) {
        $message = "Cartella creata con successo!";
    } else {
        $error = "Errore: " . $conn->error;
    }
}

// Gestione dell'eliminazione di una cartella
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_folder') {
    $folder_id = (int)$_POST['folder_id'];
    
    $sql = "DELETE FROM folders WHERE id = $folder_id";
    
    if ($conn->query($sql) === TRUE) {
        $message = "Cartella eliminata con successo!";
    } else {
        $error = "Errore: " . $conn->error;
    }
}

// Gestione dell'aggiunta di un nuovo documento
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_document') {
    $title = clean_input($_POST['title']);
    $description = clean_input($_POST['description']);
    $folder_id = isset($_POST['folder_id']) && !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : 'NULL';
    $ragazzo_id = isset($_POST['ragazzo_id']) && !empty($_POST['ragazzo_id']) ? (int)$_POST['ragazzo_id'] : 'NULL';
    
    // Gestione del caricamento del file
    $file_path = NULL;
    $file_type = NULL;
    
    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] == 0) {
        $file_name = $_FILES['document_file']['name'];
        $file_tmp = $_FILES['document_file']['tmp_name'];
        $file_type = $_FILES['document_file']['type'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Genera un nome file unico
        $new_file_name = uniqid() . '_' . $file_name;
        $file_path = 'uploads/documenti/' . $new_file_name;
        
        // Sposta il file nella directory di upload
        if (move_uploaded_file($file_tmp, __DIR__ . '/' . $file_path)) {
            // File caricato con successo
        } else {
            $error = "Errore nel caricamento del file.";
        }
    }
    
    if (empty($error)) {
        $sql = "INSERT INTO documents (title, description, file_path, file_type, ragazzo_id, folder_id, created_by)
                VALUES ('$title', '$description', " . ($file_path ? "'$file_path'" : "NULL") . ", " . 
                ($file_type ? "'$file_type'" : "NULL") . ", $ragazzo_id, $folder_id, $user_id)";
        
        if ($conn->query($sql) === TRUE) {
            $message = "Documento aggiunto con successo!";
        } else {
            $error = "Errore: " . $conn->error;
        }
    }
}

// Gestione dell'eliminazione di un documento
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_document') {
    $document_id = (int)$_POST['document_id'];
    
    // Ottieni il percorso del file prima di eliminare il record
    $get_file = "SELECT file_path FROM documents WHERE id = $document_id";
    $file_result = $conn->query($get_file);
    
    if ($file_result->num_rows > 0) {
        $file_data = $file_result->fetch_assoc();
        $file_path = $file_data['file_path'];
        
        // Elimina il file se esiste
        if ($file_path && file_exists(__DIR__ . '/' . $file_path)) {
            unlink(__DIR__ . '/' . $file_path);
        }
    }
    
    $sql = "DELETE FROM documents WHERE id = $document_id";
    
    if ($conn->query($sql) === TRUE) {
        $message = "Documento eliminato con successo!";
    } else {
        $error = "Errore: " . $conn->error;
    }
}

// Ottieni l'ID della cartella corrente
$current_folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : NULL;

// Ottieni informazioni sulla cartella corrente
$current_folder = NULL;
if ($current_folder_id) {
    $folder_sql = "SELECT * FROM folders WHERE id = $current_folder_id";
    $folder_result = $conn->query($folder_sql);
    if ($folder_result->num_rows > 0) {
        $current_folder = $folder_result->fetch_assoc();
    }
}

// Ottieni tutte le cartelle nella cartella corrente
// Gli amministratori vedono tutte le cartelle, gli utenti normali vedono solo le cartelle con il loro nome
if ($is_admin) {
    $folders_sql = "SELECT f.*, u.username as creator_name
                    FROM folders f
                    JOIN users u ON f.created_by = u.id
                    WHERE " . ($current_folder_id ? "f.parent_id = $current_folder_id" : "f.parent_id IS NULL") . "
                    ORDER BY f.name ASC";
} else {
    // Gli utenti normali vedono solo le cartelle che contengono il loro nome utente
    $username = $_SESSION['username'];
    $folders_sql = "SELECT f.*, u.username as creator_name
                    FROM folders f
                    JOIN users u ON f.created_by = u.id
                    WHERE " . ($current_folder_id ? "f.parent_id = $current_folder_id" : "f.parent_id IS NULL") . "
                    AND (f.name LIKE '%$username%' OR f.created_by = $user_id)
                    ORDER BY f.name ASC";
}
$folders = $conn->query($folders_sql);

// Ottieni tutti i documenti nella cartella corrente
if ($is_admin) {
    $documents_sql = "SELECT d.*, u.username as creator_name,
                     CONCAT(r.nome, ' ', r.cognome) as ragazzo_name
                     FROM documents d
                     JOIN users u ON d.created_by = u.id
                     LEFT JOIN ragazzi r ON d.ragazzo_id = r.id
                     WHERE " . ($current_folder_id ? "d.folder_id = $current_folder_id" : "d.folder_id IS NULL") . "
                     ORDER BY d.timestamp DESC";
} else {
    // Gli utenti normali vedono solo i documenti che hanno creato
    $documents_sql = "SELECT d.*, u.username as creator_name,
                     CONCAT(r.nome, ' ', r.cognome) as ragazzo_name
                     FROM documents d
                     JOIN users u ON d.created_by = u.id
                     LEFT JOIN ragazzi r ON d.ragazzo_id = r.id
                     WHERE " . ($current_folder_id ? "d.folder_id = $current_folder_id" : "d.folder_id IS NULL") . "
                     AND d.created_by = $user_id
                     ORDER BY d.timestamp DESC";
}
$documents = $conn->query($documents_sql);

// Ottieni tutti i ragazzi per il menu a tendina (solo per amministratori)
if ($is_admin) {
    $ragazzi_sql = "SELECT id, CONCAT(nome, ' ', cognome) as nome_completo FROM ragazzi ORDER BY cognome, nome";
    $ragazzi = $conn->query($ragazzi_sql);
}

// Ottieni tutte le cartelle per il menu a tendina
$all_folders_sql = "SELECT id, name FROM folders ORDER BY name";
$all_folders = $conn->query($all_folders_sql);

// Costruisci il percorso di navigazione
$breadcrumbs = [];
$temp_folder_id = $current_folder_id;
while ($temp_folder_id) {
    $breadcrumb_sql = "SELECT id, name, parent_id FROM folders WHERE id = $temp_folder_id";
    $breadcrumb_result = $conn->query($breadcrumb_sql);
    if ($breadcrumb_result->num_rows > 0) {
        $breadcrumb = $breadcrumb_result->fetch_assoc();
        array_unshift($breadcrumbs, $breadcrumb);
        $temp_folder_id = $breadcrumb['parent_id'];
    } else {
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diario - Gestione Documenti</title>
    <link rel="stylesheet" href="fbi-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Fira+Code:wght@400;500;600&display=swap">
    <style>
        .documents-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .folder-card, .document-card {
            background-color: var(--bg-darker);
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }
        
        .folder-card:hover, .document-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }
        
        .folder-card {
            border-left: 4px solid var(--accent-color);
        }
        
        .document-card {
            border-left: 4px solid var(--secondary-color);
        }
        
        .card-icon {
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--accent-color);
        }
        
        .document-card .card-icon {
            color: var(--secondary-color);
        }
        
        .card-title {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 1.1rem;
            word-break: break-word;
        }
        
        .card-meta {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-bottom: 10px;
        }
        
        .card-actions {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
            gap: 5px;
        }
        
        .card-actions button {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 1rem;
            padding: 5px;
            border-radius: 4px;
            transition: background-color 0.2s, color 0.2s;
        }
        
        .card-actions button:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
        }
        
        .breadcrumbs {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 20px;
            padding: 10px;
            background-color: var(--bg-darker);
            border-radius: 8px;
        }
        
        .breadcrumbs a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.2s;
            display: flex;
            align-items: center;
        }
        
        .breadcrumbs a:hover {
            color: var(--accent-color);
        }
        
        .breadcrumbs .separator {
            margin: 0 10px;
            color: var(--text-secondary);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            background-color: var(--bg-darker);
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--text-secondary);
            margin-bottom: 20px;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 20px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            overflow: auto;
        }
        
        .modal-content {
            background-color: var(--bg-darker);
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 1.5rem;
            color: var(--text-secondary);
            cursor: pointer;
            transition: color 0.2s;
        }
        
        .close-modal:hover {
            color: var(--text-primary);
        }
        
        .modal-title {
            margin-top: 0;
            margin-bottom: 20px;
            color: var(--text-primary);
        }
        
        .preview-container {
            margin-top: 20px;
            text-align: center;
        }
        
        .preview-container img {
            max-width: 100%;
            max-height: 400px;
            border-radius: 4px;
        }
        
        .preview-container .document-info {
            margin-top: 20px;
            text-align: left;
            padding: 15px;
            background-color: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
        }
        
        .preview-container .document-info p {
            margin: 5px 0;
        }
        
        .file-input-container {
            position: relative;
            margin-bottom: 15px;
        }
        
        .file-input-container input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-label {
            display: block;
            padding: 10px 15px;
            background-color: var(--accent-color);
            color: white;
            text-align: center;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .file-input-label:hover {
            background-color: var(--accent-color-hover);
        }
        
        .file-name {
            margin-top: 5px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
    </style>
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
            <li><a href="documenti.php" class="active"><i class="fas fa-folder"></i> Documenti</a></li>
            <li><a href="punishments.php"><i class="fas fa-gavel"></i> Punizioni</a></li>
            <li><a href="permissions.php"><i class="fas fa-check-circle"></i> Permessi</a></li>
            <li><a href="archive.php"><i class="fas fa-archive"></i> Archivio</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Esci</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1 class="page-title"><i class="fas fa-folder-open"></i> Gestione Documenti</h1>
        
        <?php if ($message): ?>
            <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <button class="btn" onclick="openModal('folderModal')"><i class="fas fa-folder-plus"></i> Nuova Cartella</button>
            <button class="btn" onclick="openModal('documentModal')"><i class="fas fa-file-upload"></i> Nuovo Documento</button>
        </div>
        
        <div class="breadcrumbs">
            <a href="documenti.php"><i class="fas fa-home"></i> Home</a>
            <?php foreach ($breadcrumbs as $breadcrumb): ?>
                <span class="separator"><i class="fas fa-chevron-right"></i></span>
                <a href="documenti.php?folder_id=<?php echo $breadcrumb['id']; ?>"><?php echo $breadcrumb['name']; ?></a>
            <?php endforeach; ?>
        </div>
        
        <div class="documents-container">
            <?php if ($folders->num_rows > 0 || $documents->num_rows > 0): ?>
                <?php while ($folder = $folders->fetch_assoc()): ?>
                    <div class="folder-card">
                        <div class="card-icon">
                            <i class="fas fa-folder"></i>
                        </div>
                        <div class="card-title">
                            <a href="documenti.php?folder_id=<?php echo $folder['id']; ?>"><?php echo htmlspecialchars($folder['name']); ?></a>
                        </div>
                        <div class="card-meta">
                            Creata da: <?php echo $folder['creator_name']; ?><br>
                            Data: <?php echo date('d/m/Y H:i', strtotime($folder['timestamp'])); ?>
                        </div>
                        <div class="card-actions">
                            <form method="post" action="" onsubmit="return confirm('Sei sicuro di voler eliminare questa cartella e tutto il suo contenuto?');">
                                <input type="hidden" name="action" value="delete_folder">
                                <input type="hidden" name="folder_id" value="<?php echo $folder['id']; ?>">
                                <button type="submit" title="Elimina cartella"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
                
                <?php while ($document = $documents->fetch_assoc()): ?>
                    <div class="document-card">
                        <div class="card-icon">
                            <?php
                            $icon = 'fas fa-file';
                            if ($document['file_type']) {
                                if (strpos($document['file_type'], 'image/') !== false) {
                                    $icon = 'fas fa-file-image';
                                } elseif (strpos($document['file_type'], 'pdf') !== false) {
                                    $icon = 'fas fa-file-pdf';
                                } elseif (strpos($document['file_type'], 'word') !== false || strpos($document['file_type'], 'document') !== false) {
                                    $icon = 'fas fa-file-word';
                                } elseif (strpos($document['file_type'], 'excel') !== false || strpos($document['file_type'], 'sheet') !== false) {
                                    $icon = 'fas fa-file-excel';
                                } elseif (strpos($document['file_type'], 'video') !== false) {
                                    $icon = 'fas fa-file-video';
                                } elseif (strpos($document['file_type'], 'audio') !== false) {
                                    $icon = 'fas fa-file-audio';
                                } elseif (strpos($document['file_type'], 'zip') !== false || strpos($document['file_type'], 'archive') !== false) {
                                    $icon = 'fas fa-file-archive';
                                } elseif (strpos($document['file_type'], 'text') !== false) {
                                    $icon = 'fas fa-file-alt';
                                }
                            }
                            ?>
                            <i class="<?php echo $icon; ?>"></i>
                        </div>
                        <div class="card-title">
                            <?php if ($document['file_path']): ?>
                                <a href="<?php echo $document['file_path']; ?>" target="_blank"><?php echo htmlspecialchars($document['title']); ?></a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($document['title']); ?>
                            <?php endif; ?>
                        </div>
                        <div class="card-meta">
                            <?php if ($document['ragazzo_name']): ?>
                                Ragazzo: <?php echo $document['ragazzo_name']; ?><br>
                            <?php endif; ?>
                            Creato da: <?php echo $document['creator_name']; ?><br>
                            Data: <?php echo date('d/m/Y H:i', strtotime($document['timestamp'])); ?>
                        </div>
                        <div class="card-actions">
                            <button onclick="previewDocument(<?php echo $document['id']; ?>, '<?php echo addslashes($document['title']); ?>', '<?php echo addslashes($document['description']); ?>', '<?php echo $document['file_path']; ?>', '<?php echo $document['file_type']; ?>', '<?php echo $document['ragazzo_name']; ?>', '<?php echo $document['creator_name']; ?>', '<?php echo $document['timestamp']; ?>')" title="Visualizza dettagli">
                                <i class="fas fa-eye"></i>
                            </button>
                            <form method="post" action="" onsubmit="return confirm('Sei sicuro di voler eliminare questo documento?');">
                                <input type="hidden" name="action" value="delete_document">
                                <input type="hidden" name="document_id" value="<?php echo $document['id']; ?>">
                                <button type="submit" title="Elimina documento"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>Questa cartella è vuota. Aggiungi cartelle o documenti per iniziare.</p>
                    <div class="action-buttons">
                        <button class="btn" onclick="openModal('folderModal')"><i class="fas fa-folder-plus"></i> Nuova Cartella</button>
                        <button class="btn" onclick="openModal('documentModal')"><i class="fas fa-file-upload"></i> Nuovo Documento</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal per aggiungere una nuova cartella -->
    <div id="folderModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('folderModal')">&times;</span>
            <h2 class="modal-title"><i class="fas fa-folder-plus"></i> Crea Nuova Cartella</h2>
            <form method="post" action="">
                <input type="hidden" name="action" value="add_folder">
                <input type="hidden" name="parent_id" value="<?php echo $current_folder_id; ?>">
                
                <div class="form-group">
                    <label for="folder_name"><i class="fas fa-folder"></i> Nome Cartella:</label>
                    <input type="text" id="folder_name" name="folder_name" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn"><i class="fas fa-save"></i> Crea Cartella</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal per aggiungere un nuovo documento -->
    <div id="documentModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('documentModal')">&times;</span>
            <h2 class="modal-title"><i class="fas fa-file-upload"></i> Aggiungi Nuovo Documento</h2>
            <form method="post" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_document">
                <input type="hidden" name="folder_id" value="<?php echo $current_folder_id; ?>">
                
                <div class="form-group">
                    <label for="title"><i class="fas fa-heading"></i> Titolo:</label>
                    <input type="text" id="title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="description"><i class="fas fa-align-left"></i> Descrizione:</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
                
                <?php if ($is_admin): ?>
                <div class="form-group">
                    <label for="ragazzo_id"><i class="fas fa-child"></i> Ragazzo (opzionale):</label>
                    <select id="ragazzo_id" name="ragazzo_id">
                        <option value="">-- Seleziona un ragazzo --</option>
                        <?php while ($ragazzo = $ragazzi->fetch_assoc()): ?>
                            <option value="<?php echo $ragazzo['id']; ?>"><?php echo $ragazzo['nome_completo']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-group file-input-container">
                    <label class="file-input-label" for="document_file"><i class="fas fa-upload"></i> Seleziona File</label>
                    <input type="file" id="document_file" name="document_file">
                    <div class="file-name" id="file-name">Nessun file selezionato</div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn"><i class="fas fa-save"></i> Aggiungi Documento</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal per visualizzare i dettagli del documento -->
    <div id="previewModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal('previewModal')">&times;</span>
            <h2 class="modal-title" id="preview-title"></h2>
            <div class="preview-container" id="preview-container">
                <!-- Il contenuto verrà inserito dinamicamente -->
            </div>
        </div>
    </div>
    
    <script>
        // Funzione per aprire un modal
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        // Funzione per chiudere un modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Funzione per visualizzare l'anteprima di un documento
        function previewDocument(id, title, description, filePath, fileType, ragazzoName, creatorName, timestamp) {
            const modal = document.getElementById('previewModal');
            const titleElement = document.getElementById('preview-title');
            const container = document.getElementById('preview-container');
            
            titleElement.innerHTML = '<i class="fas fa-file"></i> ' + title;
            
            let previewHtml = '';
            
            // Aggiungi anteprima del file se disponibile
            if (filePath) {
                if (fileType && fileType.startsWith('image/')) {
                    previewHtml += `<img src="${filePath}" alt="${title}">`;
                } else if (fileType && fileType.includes('pdf')) {
                    previewHtml += `<embed src="${filePath}" type="application/pdf" width="100%" height="400px">`;
                } else {
                    previewHtml += `<p><a href="${filePath}" target="_blank" class="btn"><i class="fas fa-download"></i> Scarica File</a></p>`;
                }
            }
            
            // Aggiungi informazioni sul documento
            previewHtml += `
                <div class="document-info">
                    ${description ? `<p><strong>Descrizione:</strong> ${description}</p>` : ''}
                    ${ragazzoName ? `<p><strong>Ragazzo:</strong> ${ragazzoName}</p>` : ''}
                    <p><strong>Creato da:</strong> ${creatorName}</p>
                    <p><strong>Data:</strong> ${new Date(timestamp).toLocaleString()}</p>
                    ${fileType ? `<p><strong>Tipo di file:</strong> ${fileType}</p>` : ''}
                </div>
            `;
            
            container.innerHTML = previewHtml;
            modal.style.display = 'block';
        }
        
        // Mostra il nome del file selezionato
        document.getElementById('document_file').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'Nessun file selezionato';
            document.getElementById('file-name').textContent = fileName;
        });
        
        // Chiudi i modal quando si fa clic all'esterno
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        };
    </script>
</body>
</html>