<?php
require_once 'config.php';

// Verifica se l'utente è loggato
if (!isLoggedIn()) {
    redirect('index.php');
}

$user_id = $_SESSION['user_id'];
$message = '';

// Genera un ID di sessione unico per il diario se non esiste
if (!isset($_SESSION['diary_session_id'])) {
    $_SESSION['diary_session_id'] = uniqid('diary_', true);
}
$session_id = $_SESSION['diary_session_id'];

// Verifica se la colonna session_id esiste nella tabella diary_entries
$check_column = "SHOW COLUMNS FROM diary_entries LIKE 'session_id'";
$column_result = $conn->query($check_column);
if ($column_result->num_rows == 0) {
    $add_column = "ALTER TABLE diary_entries ADD COLUMN session_id VARCHAR(100) NULL";
    $conn->query($add_column);
}

// Gestione dell'aggiunta di una nuova voce del diario
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['content']) && !isset($_POST['action'])) {
    $content = clean_input($_POST['content']);
    $timestamp = getCurrentTimestamp();

    // Genera un nuovo ID di sessione per separare le voci
    $_SESSION['diary_session_id'] = uniqid('diary_', true);
    $session_id = $_SESSION['diary_session_id'];

    $sql = "INSERT INTO diary_entries (user_id, content, timestamp, session_id)
            VALUES ($user_id, '$content', '$timestamp', '$session_id')";

    if ($conn->query($sql) === TRUE) {
        $message = "Voce del diario aggiunta con successo!";
    } else {
        $message = "Errore: " . $conn->error;
    }
}

// Gestione del salvataggio automatico (AJAX)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'autosave') {
    header('Content-Type: application/json');

    if (isset($_POST['content'])) {
        $content = clean_input($_POST['content']);
        $timestamp = getCurrentTimestamp();
        $current_session = clean_input($_POST['session_id']);
        $inactivity_timeout = isset($_POST['inactivity']) && $_POST['inactivity'] == 'true';

        // Se c'è stato un timeout di inattività, genera un nuovo ID di sessione
        if ($inactivity_timeout) {
            $_SESSION['diary_session_id'] = uniqid('diary_', true);
            $current_session = $_SESSION['diary_session_id'];
        }

        $sql = "INSERT INTO diary_entries (user_id, content, timestamp, session_id)
                VALUES ($user_id, '$content', '$timestamp', '$current_session')";

        if ($conn->query($sql) === TRUE) {
            echo json_encode([
                'success' => true,
                'message' => 'Salvato automaticamente',
                'timestamp' => $timestamp,
                'session_id' => $current_session,
                'inactivity_reset' => $inactivity_timeout
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Errore: ' . $conn->error]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Nessun contenuto fornito']);
    exit;
}

// Ottieni le voci del diario dell'utente raggruppate per sessione
$sql = "SELECT e.*,
        (SELECT username FROM users WHERE id = e.user_id) as username,
        (SELECT MIN(timestamp) FROM diary_entries WHERE session_id = e.session_id) as session_start
        FROM diary_entries e
        WHERE e.user_id = $user_id
        ORDER BY e.timestamp DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIARIO - Diario Operativo</title>
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
            <li><a href="diary.php" class="active"><i class="fas fa-book"></i> Diario Operativo</a></li>
            <?php if (isAdmin()): ?>
                <li><a href="users.php"><i class="fas fa-users"></i> Gestione Utenti</a></li>
                <li><a href="ragazzi.php"><i class="fas fa-child"></i> Ragazzi</a></li>
            <?php endif; ?>
            <li><a href="documenti.php"><i class="fas fa-folder"></i> Documenti</a></li>
            <li><a href="punishments.php"><i class="fas fa-gavel"></i> Punizioni</a></li>
            <li><a href="permissions.php"><i class="fas fa-check-circle"></i> Autorizzazioni</a></li>
            <li><a href="tema_sito.php"><i class="fas fa-palette"></i> Tema del Sito</a></li>
            <li><a href="archive.php"><i class="fas fa-archive"></i> Archivio Storico</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Disconnessione</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1 class="page-title"><i class="fas fa-book"></i> Diario Operativo</h1>

        <?php if ($message): ?>
            <div class="success-message"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="diary-form">
            <h2><i class="fas fa-plus-circle"></i> Aggiungi una nuova registrazione</h2>
            <form id="diaryForm" method="post" action="">
                <textarea id="diaryContent" name="content" placeholder="Scrivi qui la tua registrazione operativa..." required></textarea>
                <button type="submit" class="btn"><i class="fas fa-save"></i> Salva registrazione</button>
            </form>
        </div>

        <div id="autosave-status" class="autosave-status">
            <span id="autosave-icon"></span>
            <span id="autosave-text">Pronto per scrivere</span>
        </div>

        <h2><i class="fas fa-list"></i> Le tue registrazioni operative</h2>

        <?php if ($result->num_rows > 0): ?>
            <?php
            $current_session = null;
            $entries_by_session = [];

            // Raggruppa le voci per sessione
            while($entry = $result->fetch_assoc()) {
                if (!isset($entries_by_session[$entry['session_id']])) {
                    $entries_by_session[$entry['session_id']] = [
                        'entries' => [],
                        'start_time' => $entry['session_start'],
                        'username' => $entry['username']
                    ];
                }
                $entries_by_session[$entry['session_id']]['entries'][] = $entry;
            }

            // Visualizza le voci raggruppate per sessione
            foreach ($entries_by_session as $session_id => $session_data):
                $first_entry = reset($session_data['entries']);
                $last_entry = end($session_data['entries']);
            ?>
                <div class="diary-entry">
                    <h3><i class="fas fa-pen"></i> Sessione di scrittura</h3>
                    <div class="timestamp">
                        <i class="fas fa-clock"></i>
                        Iniziata il <?php echo date('d/m/Y', strtotime($session_data['start_time'])); ?>
                        alle <?php echo date('H:i:s', strtotime($session_data['start_time'])); ?>
                        da <span class="user-prefix"><?php echo $session_data['username']; ?></span>
                    </div>
                    <div class="content">
                        <?php
                        foreach ($session_data['entries'] as $entry) {
                            $lines = explode("\n", htmlspecialchars($entry['content']));
                            foreach ($lines as $line_num => $line) {
                                echo '<div class="line-numbers"><span class="user-prefix">' . $entry['username'] . ':</span> ' . nl2br($line) . '</div>';
                            }
                            // Aggiungi un separatore tra le voci della stessa sessione se non è l'ultima
                            if ($entry !== end($session_data['entries'])) {
                                echo '<div class="entry-divider" data-username="' . $entry['username'] . ' - ' . date('H:i:s', strtotime($entry['timestamp'])) . '"></div>';
                            }
                        }
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <p>Nessuna voce del diario trovata. Inizia a scrivere!</p>
                <a href="#diaryContent" class="btn"><i class="fas fa-pen"></i> Scrivi ora</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const diaryContent = document.getElementById('diaryContent');
            const autosaveStatus = document.getElementById('autosave-status');

            // Nascondi lo stato di autosalvataggio poiché non lo utilizziamo
            if (autosaveStatus) {
                autosaveStatus.style.display = 'none';
            }

            // Focus automatico sul campo di testo
            diaryContent.focus();

            // Aggiungi numeri di riga mentre l'utente digita
            diaryContent.addEventListener('keyup', function() {
                const lines = this.value.split('\n').length;
                this.style.height = (lines * 24) + 'px';
            });
        });
    </script>
</body>
</html>