<?php
// Configurazione del database
$host = "localhost";
$username = "root";
$password = "";

// Connessione al server MySQL senza selezionare un database
$conn = new mysqli($host, $username, $password);

// Verifica della connessione
if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// Creazione del database
$sql = "CREATE DATABASE IF NOT EXISTS diario_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "Database creato con successo o già esistente<br>";
} else {
    echo "Errore nella creazione del database: " . $conn->error . "<br>";
}

// Seleziona il database
$conn->select_db("diario_db");

// Creazione delle tabelle
$tables = [
    "users" => "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        is_admin TINYINT(1) DEFAULT 0,
        note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "diary_entries" => "CREATE TABLE IF NOT EXISTS diary_entries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        content TEXT NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    
    "punishments" => "CREATE TABLE IF NOT EXISTS punishments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        description TEXT NOT NULL,
        duration VARCHAR(100) DEFAULT 'Non specificato',
        created_by INT NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )",
    
    "permissions" => "CREATE TABLE IF NOT EXISTS permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        description TEXT NOT NULL,
        created_by INT NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )",
    
    "archive_folders" => "CREATE TABLE IF NOT EXISTS archive_folders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        created_by INT NOT NULL,
        start_date DATE NULL,
        end_date DATE NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )",
    
    "archive_documents" => "CREATE TABLE IF NOT EXISTS archive_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        folder_id INT NOT NULL,
        title VARCHAR(100) NOT NULL,
        content TEXT NOT NULL,
        created_by INT NOT NULL,
        user_id INT NULL,
        original_timestamp DATETIME NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (folder_id) REFERENCES archive_folders(id) ON DELETE CASCADE,
        FOREIGN KEY (created_by) REFERENCES users(id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )",
    
    "folder_permissions" => "CREATE TABLE IF NOT EXISTS folder_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        folder_id INT NOT NULL,
        user_id INT NOT NULL,
        FOREIGN KEY (folder_id) REFERENCES archive_folders(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY (folder_id, user_id)
    )"
];

foreach ($tables as $table => $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Tabella $table creata con successo o già esistente<br>";
    } else {
        echo "Errore nella creazione della tabella $table: " . $conn->error . "<br>";
    }
}

// Verifica se l'amministratore esiste già
$check_admin = "SELECT * FROM users WHERE username = 'Amministratore Stefano'";
$result = $conn->query($check_admin);

if ($result->num_rows == 0) {
    // Creazione dell'account amministratore con password "admin123"
    $admin_password = password_hash("admin123", PASSWORD_DEFAULT);
    $insert_admin = "INSERT INTO users (username, password, is_admin, note) 
                     VALUES ('Amministratore Stefano', '$admin_password', 1, 'Account amministratore principale')";
    
    if ($conn->query($insert_admin) === TRUE) {
        echo "Account amministratore creato con successo<br>";
        echo "Username: Amministratore Stefano<br>";
        echo "Password: admin123<br>";
    } else {
        echo "Errore nella creazione dell'account amministratore: " . $conn->error . "<br>";
    }
} else {
    echo "L'account amministratore esiste già<br>";
}

$conn->close();
echo "<br>Inizializzazione del database completata. <a href='index.php'>Torna alla pagina principale</a>";
?>