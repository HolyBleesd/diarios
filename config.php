<?php
// Configurazione del database
$host = "localhost";
$username = "root";
$password = "";
$database = "diario_db";

// Connessione al database
$conn = new mysqli($host, $username, $password, $database);

// Verifica della connessione
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// Impostazione del charset
$conn->set_charset("utf8");

// Funzione per pulire gli input
function clean_input($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $conn->real_escape_string($data);
}

// Inizializzazione della sessione
session_start();

// Funzione per verificare se l'utente è loggato
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Funzione per verificare se l'utente è amministratore
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

// Funzione per reindirizzare
function redirect($url) {
    header("Location: $url");
    exit();
}

// Funzione per ottenere il timestamp corrente
function getCurrentTimestamp() {
    return date("Y-m-d H:i:s");
}
?>