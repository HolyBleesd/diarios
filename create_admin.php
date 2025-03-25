<?php
require_once 'config.php';

// Verifica se l'utente admin esiste già
$check_admin = "SELECT * FROM users WHERE username = 'admin'";
$result = $conn->query($check_admin);

if ($result->num_rows > 0) {
    echo "L'utente admin esiste già nel sistema.";
} else {
    // Crea l'utente admin con password admin
    $username = 'admin';
    $password = password_hash('admin', PASSWORD_DEFAULT); // Hash della password
    $is_admin = 1; // Imposta come amministratore
    
    $sql = "INSERT INTO users (username, password, is_admin) VALUES ('$username', '$password', $is_admin)";
    
    if ($conn->query($sql) === TRUE) {
        echo "Utente admin creato con successo!<br>";
        echo "Username: admin<br>";
        echo "Password: admin<br>";
        echo "Ruolo: Amministratore<br><br>";
        echo "<a href='index.php'>Vai alla pagina di login</a>";
    } else {
        echo "Errore nella creazione dell'utente admin: " . $conn->error;
    }
}
?>