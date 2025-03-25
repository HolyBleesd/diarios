<?php
require_once 'config.php';

// Verifica se l'utente è loggato e se è amministratore
if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

// Inizializza le variabili
$tables = array();
$current_table = '';
$table_data = array();
$columns = array();
$message = '';
$error = '';
$sql_query = '';
$sql_result = '';
$backup_file = '';

// Ottieni l'elenco delle tabelle nel database
$tables_query = "SHOW TABLES";
$tables_result = $conn->query($tables_query);

if ($tables_result) {
    while ($table = $tables_result->fetch_array()) {
        $tables[] = $table[0];
    }
}

// Gestione delle azioni
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Esecuzione di una query SQL personalizzata
    if (isset($_POST['action']) && $_POST['action'] == 'execute_query') {
        $sql_query = $_POST['sql_query'];
        
        try {
            $sql_result_obj = $conn->multi_query($sql_query);
            
            if ($sql_result_obj) {
                $message = "Query eseguita con successo.";
                
                // Raccoglie i risultati
                $sql_result = '';
                do {
                    if ($result = $conn->store_result()) {
                        $sql_result .= "<div class='query-result'>";
                        $sql_result .= "<h3>Risultato:</h3>";
                        $sql_result .= "<table class='data-table'>";
                        
                        // Intestazioni
                        $sql_result .= "<thead><tr>";
                        $fields = $result->fetch_fields();
                        foreach ($fields as $field) {
                            $sql_result .= "<th>" . htmlspecialchars($field->name) . "</th>";
                        }
                        $sql_result .= "</tr></thead><tbody>";
                        
                        // Dati
                        while ($row = $result->fetch_assoc()) {
                            $sql_result .= "<tr>";
                            foreach ($row as $value) {
                                $sql_result .= "<td>" . htmlspecialchars($value) . "</td>";
                            }
                            $sql_result .= "</tr>";
                        }
                        
                        $sql_result .= "</tbody></table>";
                        $sql_result .= "</div>";
                        
                        $result->free();
                    } else {
                        if ($conn->errno == 0) {
                            $sql_result .= "<div class='query-result'>";
                            $sql_result .= "<p>Query eseguita. Righe interessate: " . $conn->affected_rows . "</p>";
                            $sql_result .= "</div>";
                        }
                    }
                } while ($conn->more_results() && $conn->next_result());
            }
        } catch (Exception $e) {
            $error = "Errore nell'esecuzione della query: " . $conn->error;
        }
    }
    
    // Backup del database
    if (isset($_POST['action']) && $_POST['action'] == 'backup_db') {
        $backup_dir = 'backups';
        
        // Crea la directory dei backup se non esiste
        if (!file_exists($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $backup_file = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';
        $command = "mysqldump -u " . DB_USER . " -p" . DB_PASS . " " . DB_NAME . " > " . $backup_file;
        
        // Esegui il comando di backup
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            $message = "Backup del database creato con successo: " . $backup_file;
        } else {
            $error = "Errore durante il backup del database. Codice: " . $return_var;
        }
    }
    
    // Modifica di un record
    if (isset($_POST['action']) && $_POST['action'] == 'edit_record') {
        $table = $_POST['table'];
        $id_column = $_POST['id_column'];
        $id_value = $_POST['id_value'];
        
        $set_clauses = array();
        
        // Costruisci le clausole SET per l'aggiornamento
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'field_') === 0) {
                $column_name = substr($key, 6); // Rimuovi 'field_' dal nome
                $set_clauses[] = "`" . $column_name . "` = '" . $conn->real_escape_string($value) . "'";
            }
        }
        
        if (count($set_clauses) > 0) {
            $update_query = "UPDATE `" . $table . "` SET " . implode(', ', $set_clauses) . " WHERE `" . $id_column . "` = '" . $conn->real_escape_string($id_value) . "'";
            
            if ($conn->query($update_query)) {
                $message = "Record aggiornato con successo.";
            } else {
                $error = "Errore nell'aggiornamento del record: " . $conn->error;
            }
        }
    }
    
    // Eliminazione di un record
    if (isset($_POST['action']) && $_POST['action'] == 'delete_record') {
        $table = $_POST['table'];
        $id_column = $_POST['id_column'];
        $id_value = $_POST['id_value'];
        
        $delete_query = "DELETE FROM `" . $table . "` WHERE `" . $id_column . "` = '" . $conn->real_escape_string($id_value) . "'";
        
        if ($conn->query($delete_query)) {
            $message = "Record eliminato con successo.";
        } else {
            $error = "Errore nell'eliminazione del record: " . $conn->error;
        }
    }
    
    // Inserimento di un nuovo record
    if (isset($_POST['action']) && $_POST['action'] == 'insert_record') {
        $table = $_POST['table'];
        
        $columns = array();
        $values = array();
        
        // Costruisci le colonne e i valori per l'inserimento
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'new_') === 0) {
                $column_name = substr($key, 4); // Rimuovi 'new_' dal nome
                $columns[] = "`" . $column_name . "`";
                $values[] = "'" . $conn->real_escape_string($value) . "'";
            }
        }
        
        if (count($columns) > 0) {
            $insert_query = "INSERT INTO `" . $table . "` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
            
            if ($conn->query($insert_query)) {
                $message = "Record inserito con successo.";
            } else {
                $error = "Errore nell'inserimento del record: " . $conn->error;
            }
        }
    }
}

// Visualizzazione di una tabella
if (isset($_GET['table'])) {
    $current_table = $_GET['table'];
    
    // Ottieni la struttura della tabella
    $columns_query = "SHOW COLUMNS FROM `" . $current_table . "`";
    $columns_result = $conn->query($columns_query);
    
    if ($columns_result) {
        while ($column = $columns_result->fetch_assoc()) {
            $columns[] = $column;
        }
    }
    
    // Ottieni i dati della tabella
    $data_query = "SELECT * FROM `" . $current_table . "` LIMIT 100";
    $data_result = $conn->query($data_query);
    
    if ($data_result) {
        while ($row = $data_result->fetch_assoc()) {
            $table_data[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIARIO - Gestione Database</title>
    <link rel="stylesheet" href="fbi-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Fira+Code:wght@400;500;600&display=swap">
    <?php require_once 'theme_loader.php'; ?>
    <style>
        .database-container {
            display: flex;
            gap: 20px;
        }
        
        .tables-sidebar {
            width: 250px;
            background-color: var(--bg-darker);
            padding: 20px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            height: fit-content;
        }
        
        .tables-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .tables-list li {
            margin-bottom: 5px;
        }
        
        .tables-list li a {
            display: block;
            padding: 10px;
            color: var(--text-secondary);
            text-decoration: none;
            border-left: 3px solid transparent;
            transition: all 0.2s;
            font-family: var(--mono-font);
            font-size: 0.95rem;
        }
        
        .tables-list li a:hover {
            background-color: var(--bg-light);
            border-left-color: var(--accent-light);
            color: var(--text-primary);
        }
        
        .tables-list li a.active {
            background-color: var(--bg-light);
            border-left-color: var(--accent-color);
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .table-content {
            flex: 1;
        }
        
        .sql-editor {
            background-color: var(--bg-darker);
            padding: 20px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        
        .sql-editor textarea {
            width: 100%;
            height: 150px;
            background-color: rgba(0, 0, 0, 0.3);
            color: var(--terminal-green);
            border: 1px solid var(--border-color);
            padding: 15px;
            font-family: var(--mono-font);
            font-size: 1rem;
            resize: vertical;
            margin-bottom: 15px;
        }
        
        .query-result {
            margin-bottom: 30px;
            background-color: var(--bg-darker);
            padding: 20px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }
        
        .table-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .edit-form {
            background-color: var(--bg-darker);
            padding: 20px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            margin-top: 20px;
        }
        
        .edit-form h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: var(--text-primary);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }
        
        .edit-form .form-row {
            display: flex;
            margin-bottom: 15px;
        }
        
        .edit-form .form-row label {
            width: 200px;
            padding-right: 15px;
            display: flex;
            align-items: center;
            color: var(--text-secondary);
            font-family: var(--mono-font);
        }
        
        .edit-form .form-row input, .edit-form .form-row select, .edit-form .form-row textarea {
            flex: 1;
            padding: 10px;
            background-color: rgba(0, 0, 0, 0.3);
            color: var(--terminal-green);
            border: 1px solid var(--border-color);
            font-family: var(--mono-font);
        }
        
        .record-actions {
            width: 100px;
            text-align: center;
        }
        
        .record-actions form {
            display: inline;
        }
        
        .record-actions button {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            padding: 5px;
            transition: color 0.2s;
        }
        
        .record-actions button:hover {
            color: var(--text-primary);
        }
        
        .record-actions button.edit {
            color: var(--accent-light);
        }
        
        .record-actions button.delete {
            color: var(--danger-color);
        }
        
        .backup-section {
            background-color: var(--bg-darker);
            padding: 20px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
        }
        
        .backup-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: var(--text-primary);
        }
        
        .backup-section p {
            margin-bottom: 15px;
            color: var(--text-secondary);
        }
        
        .data-table {
            width: 100%;
            overflow-x: auto;
            display: block;
        }
        
        .data-table th, .data-table td {
            white-space: nowrap;
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="user-info">
            <h2><?php echo $_SESSION['username']; ?></h2>
            <p><?php echo isAdmin() ? 'ADMIN LEVEL' : 'AGENT LEVEL'; ?></p>
        </div>
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="diary.php"><i class="fas fa-book"></i> Diario Operativo</a></li>
            <?php if (isAdmin()): ?>
                <li><a href="users.php"><i class="fas fa-users"></i> Gestione Operatori</a></li>
                <li><a href="ragazzi.php"><i class="fas fa-child"></i> Soggetti</a></li>
                <li><a href="database_admin.php" class="active"><i class="fas fa-database"></i> Database</a></li>
            <?php endif; ?>
            <li><a href="documenti.php"><i class="fas fa-folder"></i> Archivio Documenti</a></li>
            <li><a href="punishments.php"><i class="fas fa-gavel"></i> Sanzioni</a></li>
            <li><a href="permissions.php"><i class="fas fa-check-circle"></i> Autorizzazioni</a></li>
            <li><a href="tema_sito.php"><i class="fas fa-palette"></i> Tema del Sito</a></li>
            <li><a href="archive.php"><i class="fas fa-archive"></i> Archivio Storico</a></li>
            <li><a href="update_style.php"><i class="fas fa-paint-brush"></i> Gestione Stile</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Disconnessione</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1 class="page-title"><i class="fas fa-database"></i> Gestione Database</h1>
        
        <?php if ($message): ?>
            <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="backup-section">
            <h3><i class="fas fa-save"></i> Backup del Database</h3>
            <p>Crea un backup completo del database. Il file verrà salvato nella cartella "backups".</p>
            <form method="post" action="">
                <input type="hidden" name="action" value="backup_db">
                <button type="submit" class="btn"><i class="fas fa-download"></i> Crea Backup</button>
            </form>
            <?php if ($backup_file): ?>
                <p>Backup creato: <?php echo $backup_file; ?></p>
            <?php endif; ?>
        </div>
        
        <div class="sql-editor">
            <h3><i class="fas fa-code"></i> Editor SQL</h3>
            <form method="post" action="">
                <textarea name="sql_query" placeholder="Inserisci la tua query SQL qui..."><?php echo $sql_query; ?></textarea>
                <input type="hidden" name="action" value="execute_query">
                <button type="submit" class="btn"><i class="fas fa-play"></i> Esegui Query</button>
            </form>
        </div>
        
        <?php if ($sql_result): ?>
            <div class="query-result">
                <h3><i class="fas fa-list"></i> Risultato della Query</h3>
                <?php echo $sql_result; ?>
            </div>
        <?php endif; ?>
        
        <div class="database-container">
            <div class="tables-sidebar">
                <h3><i class="fas fa-table"></i> Tabelle</h3>
                <ul class="tables-list">
                    <?php foreach ($tables as $table): ?>
                        <li>
                            <a href="?table=<?php echo $table; ?>" <?php if ($current_table == $table) echo 'class="active"'; ?>>
                                <i class="fas fa-table"></i> <?php echo $table; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <div class="table-content">
                <?php if ($current_table): ?>
                    <h2><i class="fas fa-table"></i> Tabella: <?php echo $current_table; ?></h2>
                    
                    <div class="table-actions">
                        <button class="btn" id="showInsertForm"><i class="fas fa-plus"></i> Inserisci Nuovo Record</button>
                    </div>
                    
                    <div class="edit-form" id="insertForm" style="display: none;">
                        <h3><i class="fas fa-plus"></i> Inserisci Nuovo Record</h3>
                        <form method="post" action="">
                            <?php foreach ($columns as $column): ?>
                                <div class="form-row">
                                    <label for="new_<?php echo $column['Field']; ?>"><?php echo $column['Field']; ?></label>
                                    <input type="text" id="new_<?php echo $column['Field']; ?>" name="new_<?php echo $column['Field']; ?>" 
                                           <?php if ($column['Extra'] == 'auto_increment') echo 'placeholder="AUTO INCREMENT"'; ?>>
                                </div>
                            <?php endforeach; ?>
                            
                            <input type="hidden" name="action" value="insert_record">
                            <input type="hidden" name="table" value="<?php echo $current_table; ?>">
                            
                            <div class="form-group">
                                <button type="submit" class="btn"><i class="fas fa-save"></i> Inserisci Record</button>
                                <button type="button" class="btn btn-danger" id="cancelInsert"><i class="fas fa-times"></i> Annulla</button>
                            </div>
                        </form>
                    </div>
                    
                    <?php if (count($columns) > 0): ?>
                        <div class="table-structure">
                            <h3><i class="fas fa-sitemap"></i> Struttura della Tabella</h3>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Campo</th>
                                        <th>Tipo</th>
                                        <th>Null</th>
                                        <th>Chiave</th>
                                        <th>Default</th>
                                        <th>Extra</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($columns as $column): ?>
                                        <tr>
                                            <td><?php echo $column['Field']; ?></td>
                                            <td><?php echo $column['Type']; ?></td>
                                            <td><?php echo $column['Null']; ?></td>
                                            <td><?php echo $column['Key']; ?></td>
                                            <td><?php echo $column['Default']; ?></td>
                                            <td><?php echo $column['Extra']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (count($table_data) > 0): ?>
                        <div class="table-data">
                            <h3><i class="fas fa-list"></i> Dati della Tabella</h3>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <?php foreach ($columns as $column): ?>
                                            <th><?php echo $column['Field']; ?></th>
                                        <?php endforeach; ?>
                                        <th>Azioni</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($table_data as $row): ?>
                                        <tr>
                                            <?php 
                                            $id_column = '';
                                            $id_value = '';
                                            
                                            // Trova la chiave primaria
                                            foreach ($columns as $column) {
                                                if ($column['Key'] == 'PRI') {
                                                    $id_column = $column['Field'];
                                                    $id_value = $row[$id_column];
                                                    break;
                                                }
                                            }
                                            
                                            // Se non c'è una chiave primaria, usa la prima colonna
                                            if (empty($id_column)) {
                                                $id_column = $columns[0]['Field'];
                                                $id_value = $row[$id_column];
                                            }
                                            
                                            foreach ($columns as $column): 
                                                $field = $column['Field'];
                                            ?>
                                                <td><?php echo htmlspecialchars($row[$field]); ?></td>
                                            <?php endforeach; ?>
                                            <td class="record-actions">
                                                <button class="edit" onclick="showEditForm('<?php echo $id_column; ?>', '<?php echo $id_value; ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <form method="post" action="" onsubmit="return confirm('Sei sicuro di voler eliminare questo record?');">
                                                    <input type="hidden" name="action" value="delete_record">
                                                    <input type="hidden" name="table" value="<?php echo $current_table; ?>">
                                                    <input type="hidden" name="id_column" value="<?php echo $id_column; ?>">
                                                    <input type="hidden" name="id_value" value="<?php echo $id_value; ?>">
                                                    <button type="submit" class="delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Form di modifica (nascosto per default) -->
                        <div class="edit-form" id="editForm" style="display: none;">
                            <h3><i class="fas fa-edit"></i> Modifica Record</h3>
                            <form method="post" action="" id="recordEditForm">
                                <?php foreach ($columns as $column): ?>
                                    <div class="form-row">
                                        <label for="field_<?php echo $column['Field']; ?>"><?php echo $column['Field']; ?></label>
                                        <input type="text" id="field_<?php echo $column['Field']; ?>" name="field_<?php echo $column['Field']; ?>">
                                    </div>
                                <?php endforeach; ?>
                                
                                <input type="hidden" name="action" value="edit_record">
                                <input type="hidden" name="table" value="<?php echo $current_table; ?>">
                                <input type="hidden" name="id_column" id="id_column" value="">
                                <input type="hidden" name="id_value" id="id_value" value="">
                                
                                <div class="form-group">
                                    <button type="submit" class="btn"><i class="fas fa-save"></i> Salva Modifiche</button>
                                    <button type="button" class="btn btn-danger" id="cancelEdit"><i class="fas fa-times"></i> Annulla</button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-info-circle"></i>
                            <p>Nessun dato presente nella tabella.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-database"></i>
                        <p>Seleziona una tabella dal menu a sinistra per visualizzarne i dati.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Funzione per mostrare il form di modifica
        function showEditForm(idColumn, idValue) {
            document.getElementById('id_column').value = idColumn;
            document.getElementById('id_value').value = idValue;
            
            // Trova i dati del record
            const rows = document.querySelectorAll('.data-table tbody tr');
            let recordData = null;
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                const columns = <?php echo json_encode(array_column($columns, 'Field')); ?>;
                
                // Verifica se questa è la riga corretta
                for (let i = 0; i < columns.length; i++) {
                    if (columns[i] === idColumn && cells[i].textContent === idValue) {
                        recordData = {};
                        
                        // Raccoglie i dati dalla riga
                        for (let j = 0; j < columns.length; j++) {
                            recordData[columns[j]] = cells[j].textContent;
                        }
                        
                        break;
                    }
                }
            });
            
            // Popola il form con i dati del record
            if (recordData) {
                for (const field in recordData) {
                    const input = document.getElementById('field_' + field);
                    if (input) {
                        input.value = recordData[field];
                    }
                }
            }
            
            // Mostra il form
            document.getElementById('editForm').style.display = 'block';
            
            // Scorri fino al form
            document.getElementById('editForm').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Gestione del form di inserimento
        document.getElementById('showInsertForm').addEventListener('click', function() {
            document.getElementById('insertForm').style.display = 'block';
            document.getElementById('insertForm').scrollIntoView({ behavior: 'smooth' });
        });
        
        document.getElementById('cancelInsert').addEventListener('click', function() {
            document.getElementById('insertForm').style.display = 'none';
        });
        
        // Gestione del form di modifica
        document.getElementById('cancelEdit').addEventListener('click', function() {
            document.getElementById('editForm').style.display = 'none';
        });
    </script>
</body>
</html>