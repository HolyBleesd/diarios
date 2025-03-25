<?php
require_once 'config.php';

// Verifica se l'utente è loggato e se è amministratore
if (!isLoggedIn() || !isAdmin()) {
    redirect('dashboard.php');
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['folder_id'])) {
    $folder_id = (int)$_POST['folder_id'];
    
    // Elimina tutti i permessi esistenti per questa cartella
    $delete_sql = "DELETE FROM folder_permissions WHERE folder_id = $folder_id";
    $conn->query($delete_sql);
    
    // Aggiungi i nuovi permessi
    if (isset($_POST['user_permissions']) && is_array($_POST['user_permissions'])) {
        foreach ($_POST['user_permissions'] as $user_id) {
            $user_id = (int)$user_id;
            $insert_sql = "INSERT INTO folder_permissions (folder_id, user_id) VALUES ($folder_id, $user_id)";
            $conn->query($insert_sql);
        }
    }
    
    // Reindirizza alla pagina della cartella
    redirect("folder.php?id=$folder_id");
} else {
    // Reindirizza all'archivio se non ci sono dati POST
    redirect('archive.php');
}
?>