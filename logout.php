<?php
require_once 'config.php';

// Distrugge la sessione
session_destroy();

// Reindirizza alla pagina di login
redirect('index.php');
?>