<?php
require_once 'config.php';

// Verifica se l'utente è loggato e se è amministratore
if (!isLoggedIn() || !isAdmin()) {
    redirect('dashboard.php');
}

$message = '';

// Gestione dell'aggiunta di un nuovo utente
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    $username = clean_input($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $note = clean_input($_POST['note']);
    
    // Verifica se l'utente esiste già
    $check_sql = "SELECT * FROM users WHERE username = '$username'";
    $check_result = $conn->query($check_sql);

    if ($check_result->num_rows > 0) {
        $message = "Errore: L'utente '$username' esiste già.";
    } else {
        $sql = "INSERT INTO users (username, password, note) VALUES ('$username', '$password', '$note')";

        if ($conn->query($sql) === TRUE) {
            $new_user_id = $conn->insert_id;

            // Verifica se la tabella folders esiste
            $check_folders_table = "SHOW TABLES LIKE 'folders'";
            $folders_result = $conn->query($check_folders_table);

            if ($folders_result->num_rows > 0) {
                // Crea una cartella per il nuovo utente
                $folder_name = "Utente: " . $username;
                $create_folder_sql = "INSERT INTO folders (name, parent_id, created_by)
                                     VALUES ('$folder_name', NULL, " . $_SESSION['user_id'] . ")";

                if ($conn->query($create_folder_sql) === TRUE) {
                    $message = "Utente '$username' aggiunto con successo e cartella personale creata!";
                } else {
                    $message = "Utente '$username' aggiunto con successo, ma errore nella creazione della cartella: " . $conn->error;
                }
            } else {
                $message = "Utente '$username' aggiunto con successo! (Tabella folders non trovata)";
            }
        } else {
            $message = "Errore: " . $conn->error;
        }
    }
}

// Gestione dell'eliminazione di un utente
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $user_id = (int)$_POST['user_id'];
    
    // Impedisci l'eliminazione dell'amministratore
    $check_sql = "SELECT is_admin FROM users WHERE id = $user_id";
    $check_result = $conn->query($check_sql);
    $user_to_delete = $check_result->fetch_assoc();
    
    if ($user_to_delete['is_admin'] == 1) {
        $message = "Errore: Non è possibile eliminare un account amministratore.";
    } else {
        $sql = "DELETE FROM users WHERE id = $user_id";
        
        if ($conn->query($sql) === TRUE) {
            $message = "Utente eliminato con successo!";
        } else {
            $message = "Errore: " . $conn->error;
        }
    }
}

// Gestione dell'aggiornamento di un utente
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update') {
    $user_id = (int)$_POST['user_id'];
    $note = clean_input($_POST['note']);
    
    // Aggiorna solo la nota
    $sql = "UPDATE users SET note = '$note' WHERE id = $user_id";
    
    if ($conn->query($sql) === TRUE) {
        $message = "Nota utente aggiornata con successo!";
    } else {
        $message = "Errore: " . $conn->error;
    }
}

// Gestione del cambio password
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'change_password') {
    $user_id = (int)$_POST['user_id'];
    $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);

    $sql = "UPDATE users SET password = '$new_password' WHERE id = $user_id";

    if ($conn->query($sql) === TRUE) {
        $message = "Password utente aggiornata con successo!";
    } else {
        $message = "Errore: " . $conn->error;
    }
}

// Gestione della modifica del nome utente
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'change_username') {
    $user_id = (int)$_POST['user_id'];
    $new_username = clean_input($_POST['new_username']);
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;

    // Verifica se il nuovo nome utente è già in uso
    $check_sql = "SELECT id FROM users WHERE username = '$new_username' AND id != $user_id";
    $check_result = $conn->query($check_sql);

    if ($check_result->num_rows > 0) {
        $message = "Errore: Il nome utente '$new_username' è già in uso.";
    } else {
        $sql = "UPDATE users SET username = '$new_username', is_admin = $is_admin WHERE id = $user_id";

        if ($conn->query($sql) === TRUE) {
            // Se l'utente sta modificando il proprio nome utente, aggiorna la sessione
            if ($user_id == $_SESSION['user_id']) {
                $_SESSION['username'] = $new_username;
            }
            $message = "Nome utente aggiornato con successo!";
        } else {
            $message = "Errore: " . $conn->error;
        }
    }
}

// Ottieni tutti gli utenti
$sql = "SELECT * FROM users ORDER BY is_admin DESC, username ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIARIO - Gestione Operatori</title>
    <link rel="stylesheet" href="fbi-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Fira+Code:wght@400;500;600&display=swap">
    <?php require_once 'theme_loader.php'; ?>
</head>
<body>
    <div class="sidebar">
        <div class="user-info">
            <h2><?php echo $_SESSION['username']; ?></h2>
            <p><?php echo isAdmin() ? 'ADMIN LEVEL' : 'OPERATOR LEVEL'; ?></p>
        </div>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="diary.php"><i class="fas fa-book"></i> Diario Operativo</a></li>
            <li><a href="users.php" class="active"><i class="fas fa-users"></i> Gestione Operatori</a></li>
            <li><a href="ragazzi.php"><i class="fas fa-child"></i> Soggetti</a></li>
            <li><a href="database_admin.php"><i class="fas fa-database"></i> Database</a></li>
            <li><a href="documenti.php"><i class="fas fa-folder"></i> Archivio Documenti</a></li>
            <li><a href="punishments.php"><i class="fas fa-gavel"></i> Sanzioni</a></li>
            <li><a href="permissions.php"><i class="fas fa-check-circle"></i> Autorizzazioni</a></li>
            <li><a href="tema_sito.php"><i class="fas fa-palette"></i> Tema del Sito</a></li>
            <li><a href="archive.php"><i class="fas fa-archive"></i> Archivio Storico</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Disconnessione</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1 class="page-title"><i class="fas fa-users"></i> Gestione Operatori</h1>
        
        <?php if ($message): ?>
            <div class="success-message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <h2><i class="fas fa-user-plus"></i> Aggiungi Nuovo Operatore</h2>
            <form method="post" action="">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label for="username">Nome utente:</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="note">Note:</label>
                    <textarea id="note" name="note" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <button type="submit" class="btn">Aggiungi Utente</button>
                </div>
            </form>
        </div>
        
        <h2>Elenco Utenti</h2>
        
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome Utente</th>
                    <th>Ruolo</th>
                    <th>Note</th>
                    <th>Data Creazione</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while($user = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo $user['username']; ?></td>
                            <td><?php echo $user['is_admin'] ? 'Amministratore' : 'Operatore'; ?></td>
                            <td>
                                <form method="post" action="" style="display: inline;">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <textarea name="note" rows="2" style="width: 100%;"><?php echo $user['note']; ?></textarea>
                                    <button type="submit" class="btn btn-success" style="margin-top: 5px;">Aggiorna Nota</button>
                                </form>
                            </td>
                            <td><?php echo $user['created_at']; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn" onclick="togglePasswordForm(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-key"></i> Cambia Password
                                    </button>

                                    <button class="btn" onclick="toggleUsernameForm(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-user-edit"></i> Modifica Utente
                                    </button>

                                    <?php if (!$user['is_admin']): ?>
                                        <form method="post" action="" style="display: inline; margin-left: 5px;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <button type="submit" class="btn btn-danger" onclick="return confirm('Sei sicuro di voler eliminare questo utente?')">
                                                <i class="fas fa-trash"></i> Elimina
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>

                                <div id="password-form-<?php echo $user['id']; ?>" style="display: none; margin-top: 10px;" class="edit-form">
                                    <form method="post" action="">
                                        <input type="hidden" name="action" value="change_password">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="password" name="new_password" placeholder="Nuova password" required>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save"></i> Salva
                                        </button>
                                    </form>
                                </div>

                                <div id="username-form-<?php echo $user['id']; ?>" style="display: none; margin-top: 10px;" class="edit-form">
                                    <form method="post" action="">
                                        <input type="hidden" name="action" value="change_username">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <div class="form-row">
                                            <input type="text" name="new_username" value="<?php echo $user['username']; ?>" placeholder="Nuovo nome utente" required>
                                        </div>
                                        <div class="form-row">
                                            <label>
                                                <input type="checkbox" name="is_admin" <?php echo $user['is_admin'] ? 'checked' : ''; ?>>
                                                Amministratore
                                            </label>
                                        </div>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save"></i> Salva
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">Nessun utente trovato.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        function togglePasswordForm(userId) {
            var form = document.getElementById('password-form-' + userId);
            var usernameForm = document.getElementById('username-form-' + userId);

            // Nascondi l'altro form
            usernameForm.style.display = 'none';

            // Mostra/nascondi il form della password
            if (form.style.display === 'none') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }

        function toggleUsernameForm(userId) {
            var form = document.getElementById('username-form-' + userId);
            var passwordForm = document.getElementById('password-form-' + userId);

            // Nascondi l'altro form
            passwordForm.style.display = 'none';

            // Mostra/nascondi il form del nome utente
            if (form.style.display === 'none') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }
    </script>

    <style>
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 10px;
        }

        .edit-form {
            background-color: rgba(17, 24, 39, 0.7);
            padding: 10px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .form-row {
            margin-bottom: 8px;
        }

        .form-row input[type="text"] {
            width: 100%;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            background-color: rgba(6, 10, 18, 0.8);
            color: var(--text-primary);
        }

        .form-row label {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }
    </style>
</body>
</html>