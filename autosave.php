<?php
require_once 'config.php';

// Verifica se l'utente è loggato
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Utente non autenticato']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Gestione del salvataggio automatico
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['content'])) {
    header('Content-Type: application/json');
    
    $content = clean_input($_POST['content']);
    
    // Non salvare se il contenuto è vuoto
    if (empty(trim($content))) {
        echo json_encode(['success' => false, 'message' => 'Contenuto vuoto']);
        exit;
    }
    
    $timestamp = getCurrentTimestamp();
    
    $sql = "INSERT INTO diary_entries (user_id, content, timestamp) VALUES ($user_id, '$content', '$timestamp')";
    
    if ($conn->query($sql) === TRUE) {
        echo json_encode([
            'success' => true, 
            'message' => 'Salvato automaticamente', 
            'timestamp' => $timestamp,
            'entry_id' => $conn->insert_id
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Errore: ' . $conn->error]);
    }
    exit;
}

// Se non è una richiesta POST valida
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Richiesta non valida']);
exit;
?>