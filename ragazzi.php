<?php
require_once 'config.php';

// Verifica se l'utente è loggato
if (!isLoggedIn()) {
    redirect('index.php');
}

// Verifica se l'utente è amministratore
if (!isAdmin()) {
    redirect('dashboard.php');
}

$user_id = $_SESSION['user_id'];
$message = '';

// Gestione dell'aggiunta di un nuovo ragazzo
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
    $nome = clean_input($_POST['nome']);
    $cognome = clean_input($_POST['cognome']);
    $data_ora = clean_input($_POST['data_ora']);
    $timestamp = getCurrentTimestamp();

    $sql = "INSERT INTO ragazzi (nome, cognome, data_ora, created_by, timestamp)
            VALUES ('$nome', '$cognome', '$data_ora', $user_id, '$timestamp')";

    if ($conn->query($sql) === TRUE) {
        $message = "Ragazzo aggiunto con successo!";
    } else {
        $message = "Errore: " . $conn->error;
    }
}

// Gestione dell'eliminazione di un ragazzo
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $ragazzo_id = (int)$_POST['ragazzo_id'];

    $sql = "DELETE FROM ragazzi WHERE id = $ragazzo_id";

    if ($conn->query($sql) === TRUE) {
        $message = "Ragazzo eliminato con successo!";
    } else {
        $message = "Errore: " . $conn->error;
    }
}

// Gestione del salvataggio automatico (AJAX)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'autosave') {
    header('Content-Type: application/json');

    $nome = isset($_POST['nome']) ? clean_input($_POST['nome']) : '';
    $cognome = isset($_POST['cognome']) ? clean_input($_POST['cognome']) : '';
    $data_ora = isset($_POST['data_ora']) ? clean_input($_POST['data_ora']) : '';
    $timestamp = getCurrentTimestamp();
    
    if (empty($nome) || empty($cognome) || empty($data_ora)) {
        echo json_encode(['success' => false, 'message' => 'Dati insufficienti']);
        exit;
    }

    $sql = "INSERT INTO ragazzi (nome, cognome, data_ora, created_by, timestamp)
            VALUES ('$nome', '$cognome', '$data_ora', $user_id, '$timestamp')";

    if ($conn->query($sql) === TRUE) {
        echo json_encode(['success' => true, 'message' => 'Salvato automaticamente', 'timestamp' => $timestamp]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore: ' . $conn->error]);
    }
    exit;
}

// Verifica se la tabella ragazzi esiste, altrimenti creala
$check_table = "SHOW TABLES LIKE 'ragazzi'";
$table_result = $conn->query($check_table);
if ($table_result->num_rows == 0) {
    $create_table = "CREATE TABLE IF NOT EXISTS ragazzi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        cognome VARCHAR(100) NOT NULL,
        data_ora DATETIME NOT NULL,
        created_by INT NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )";
    $conn->query($create_table);
}

// Ottieni tutti i ragazzi
$sql = "SELECT r.*, u.username as admin_name
        FROM ragazzi r
        JOIN users u ON r.created_by = u.id
        ORDER BY r.data_ora DESC";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIARIO - Gestione Soggetti</title>
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
                <li><a href="ragazzi.php" class="active"><i class="fas fa-child"></i> Soggetti</a></li>
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
        <h1 class="page-title"><i class="fas fa-child"></i> Gestione Soggetti</h1>

        <?php if ($message): ?>
            <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>

        <div class="form-container">
            <h2><i class="fas fa-plus-circle"></i> Aggiungi Nuovo Soggetto</h2>
            <form id="ragazziForm" method="post" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label for="nome"><i class="fas fa-user"></i> Nome:</label>
                    <input type="text" id="nome" name="nome" placeholder="Inserisci il nome" required>
                </div>
                
                <div class="form-group">
                    <label for="cognome"><i class="fas fa-user-tag"></i> Cognome:</label>
                    <input type="text" id="cognome" name="cognome" placeholder="Inserisci il cognome" required>
                </div>
                
                <div class="form-group">
                    <label for="data_ora"><i class="fas fa-calendar-alt"></i> Data e Ora:</label>
                    <input type="datetime-local" id="data_ora" name="data_ora" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn"><i class="fas fa-save"></i> Aggiungi Ragazzo</button>
                </div>
            </form>
        </div>

        <div id="autosave-status" class="autosave-status">
            <span id="autosave-icon"></span>
            <span id="autosave-text">Pronto per scrivere</span>
        </div>

        <h2><i class="fas fa-list"></i> Lista Ragazzi</h2>

        <table class="data-table">
            <thead>
                <tr>
                    <th><i class="fas fa-user"></i> Nome</th>
                    <th><i class="fas fa-user-tag"></i> Cognome</th>
                    <th><i class="fas fa-calendar-alt"></i> Data e Ora</th>
                    <th><i class="fas fa-user-shield"></i> Aggiunto da</th>
                    <th><i class="fas fa-clock"></i> Data Inserimento</th>
                    <th><i class="fas fa-cogs"></i> Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($ragazzo = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ragazzo['nome']); ?></td>
                            <td><?php echo htmlspecialchars($ragazzo['cognome']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($ragazzo['data_ora'])); ?></td>
                            <td><?php echo $ragazzo['admin_name']; ?></td>
                            <td><?php echo $ragazzo['timestamp']; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="documenti.php?ragazzo_id=<?php echo $ragazzo['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-folder"></i> Documenti
                                    </a>
                                    <form method="post" action="" style="display: inline; margin-left: 5px;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="ragazzo_id" value="<?php echo $ragazzo['id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Sei sicuro di voler eliminare questo soggetto?')">
                                            <i class="fas fa-trash"></i> Elimina
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">Nessun ragazzo trovato.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dataOraField = document.getElementById('data_ora');
            const autosaveStatus = document.getElementById('autosave-status');

            // Nascondi lo stato di autosalvataggio poiché non lo utilizziamo
            if (autosaveStatus) {
                autosaveStatus.style.display = 'none';
            }

            // Imposta la data e ora corrente come valore predefinito
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            dataOraField.value = `${year}-${month}-${day}T${hours}:${minutes}`;
        });
    </script>
</body>
</html>