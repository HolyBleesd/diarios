<?php
require_once 'config.php';

// Verifica se l'utente è loggato
if (!isLoggedIn()) {
    redirect('index.php');
}

// Ottieni le informazioni dell'utente
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = $user_id";
$result = $conn->query($sql);
$user = $result->fetch_assoc();

// Ottieni le ultime voci del diario (per tutti gli utenti)
$sql = "SELECT d.*, u.username FROM diary_entries d
        JOIN users u ON d.user_id = u.id
        ORDER BY d.timestamp DESC LIMIT 20";

$diary_result = $conn->query($sql);

// Ottieni statistiche per la dashboard
$stats = array();

// Numero totale di voci del diario
$stats_sql = "SELECT COUNT(*) as total FROM diary_entries";
$stats_result = $conn->query($stats_sql);
$stats['total_entries'] = $stats_result->fetch_assoc()['total'];

// Numero di utenti
$stats_sql = "SELECT COUNT(*) as total FROM users";
$stats_result = $conn->query($stats_sql);
$stats['total_users'] = $stats_result->fetch_assoc()['total'];

// Numero di voci oggi
$today = date('Y-m-d');
$stats_sql = "SELECT COUNT(*) as total FROM diary_entries WHERE DATE(timestamp) = '$today'";
$stats_result = $conn->query($stats_sql);
$stats['today_entries'] = $stats_result->fetch_assoc()['total'];

// Utente più attivo
$stats_sql = "SELECT u.username, COUNT(*) as entry_count
              FROM diary_entries d
              JOIN users u ON d.user_id = u.id
              GROUP BY d.user_id
              ORDER BY entry_count DESC
              LIMIT 1";
$stats_result = $conn->query($stats_sql);
if ($stats_result->num_rows > 0) {
    $most_active = $stats_result->fetch_assoc();
    $stats['most_active_user'] = $most_active['username'];
    $stats['most_active_count'] = $most_active['entry_count'];
} else {
    $stats['most_active_user'] = 'Nessuno';
    $stats['most_active_count'] = 0;
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIARIO - Dashboard Operativa</title>
    <link rel="stylesheet" href="fbi-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Fira+Code:wght@400;500;600&display=swap">
    <?php require_once 'theme_loader.php'; ?>
    <style>
        .dashboard-summary {
            background-color: var(--bg-darker);
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
            position: relative;
        }
        
        .dashboard-summary::before {
            content: "BRIEFING";
            position: absolute;
            top: -10px;
            left: 10px;
            background-color: var(--bg-dark);
            padding: 0 10px;
            font-size: 0.7rem;
            color: var(--accent-color);
            letter-spacing: 1px;
            border: 1px solid var(--border-color);
        }
        
        .dashboard-summary h2 {
            color: var(--text-primary);
            margin-bottom: 10px;
            font-size: 1.4rem;
            letter-spacing: 0.5px;
        }
        
        .dashboard-summary p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            font-family: 'Roboto Mono', monospace;
        }
        
        .stat-card {
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(to right, var(--accent-color), transparent);
        }
        
        .diary-entry {
            position: relative;
            overflow: hidden;
        }
        
        .diary-entry::after {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 30px;
            height: 30px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" fill="none" stroke="%23003366" stroke-width="5"/></svg>');
            background-size: contain;
            opacity: 0.1;
        }
        
        .entry-date {
            font-family: 'Roboto Mono', monospace;
            font-size: 0.8rem;
            color: var(--accent-color);
            margin-bottom: 10px;
            display: inline-block;
            padding: 2px 8px;
            background-color: rgba(0, 51, 102, 0.1);
            border-radius: 3px;
        }
        
        .entry-content {
            font-family: 'Roboto Mono', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
            color: var(--text-primary);
            padding: 10px;
            background-color: rgba(0, 0, 0, 0.2);
            border-radius: 3px;
            border-left: 3px solid var(--accent-color);
        }
        
        .admin-tools {
            background-color: var(--bg-darker);
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
            position: relative;
        }
        
        .admin-tools::before {
            content: "ADMIN CONSOLE";
            position: absolute;
            top: -10px;
            left: 10px;
            background-color: var(--bg-dark);
            padding: 0 10px;
            font-size: 0.7rem;
            color: var(--secondary-color);
            letter-spacing: 1px;
            border: 1px solid var(--secondary-color);
        }
        
        .admin-tools h2 {
            color: var(--text-primary);
            margin-bottom: 15px;
            font-size: 1.2rem;
            letter-spacing: 0.5px;
        }
        
        .system-status {
            position: fixed;
            bottom: 10px;
            right: 10px;
            font-size: 0.7rem;
            color: var(--text-secondary);
            font-family: 'Roboto Mono', monospace;
            opacity: 0.5;
            text-align: right;
        }
        
        .system-status span {
            display: block;
            margin-bottom: 2px;
        }
        
        .blinking {
            animation: blink 1s infinite;
        }
        
        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="user-info">
            <h2><?php echo $_SESSION['username']; ?></h2>
            <p><?php echo isAdmin() ? 'ADMIN LEVEL' : 'OPERATOR LEVEL'; ?></p>
        </div>
        <ul>
            <li><a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="diary.php"><i class="fas fa-book"></i> Diario Operativo</a></li>
            <?php if (isAdmin()): ?>
                <li><a href="users.php"><i class="fas fa-users"></i> Gestione Operatori</a></li>
                <li><a href="ragazzi.php"><i class="fas fa-child"></i> Soggetti</a></li>
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
        <h1 class="page-title"><i class="fas fa-tachometer-alt"></i> Dashboard Operativa</h1>

        <div class="dashboard-summary">
            <h2><i class="fas fa-user-circle"></i> Benvenuto, Operatore <?php echo $_SESSION['username']; ?></h2>
            <p>Questa è la dashboard centrale del sistema. Qui puoi monitorare tutte le attività recenti e le statistiche operative.</p>
        </div>

        <!-- Statistiche della dashboard -->
        <div class="dashboard-stats">
            <div class="stat-card">
                <i class="fas fa-book"></i>
                <div class="stat-value"><?php echo $stats['total_entries']; ?></div>
                <div class="stat-label">Rapporti Totali</div>
            </div>

            <div class="stat-card">
                <i class="fas fa-users"></i>
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Operatori Attivi</div>
            </div>

            <div class="stat-card">
                <i class="fas fa-calendar-day"></i>
                <div class="stat-value"><?php echo $stats['today_entries']; ?></div>
                <div class="stat-label">Rapporti Odierni</div>
            </div>

            <div class="stat-card">
                <i class="fas fa-trophy"></i>
                <div class="stat-value"><?php echo $stats['most_active_user']; ?></div>
                <div class="stat-label">Operatore più Attivo (<?php echo $stats['most_active_count']; ?> rapporti)</div>
            </div>
        </div>
        
        <?php if (isAdmin()): ?>
        <div class="admin-tools">
            <h2><i class="fas fa-tools"></i> Strumenti di Amministrazione</h2>
            <div class="action-buttons">
                <a href="fix_punishments_table.php" class="btn"><i class="fas fa-wrench"></i> Fix Tabella Sanzioni</a>
                <a href="fix_permissions_table.php" class="btn"><i class="fas fa-wrench"></i> Fix Tabella Autorizzazioni</a>
            </div>
        </div>
        <?php endif; ?>

        <h2><i class="fas fa-clock"></i> Ultime Attività Operative</h2>

        <?php if ($diary_result->num_rows > 0): ?>
            <?php
            $current_date = '';
            $prev_user = '';
            while($entry = $diary_result->fetch_assoc()):
                $entry_date = date('Y-m-d', strtotime($entry['timestamp']));
                if ($entry_date != $current_date) {
                    $current_date = $entry_date;
                    echo '<h3 class="date-header">' . date('d/m/Y', strtotime($entry['timestamp'])) . '</h3>';
                }
            ?>
                <div class="diary-entry">
                    <div class="entry-header">
                        <span class="entry-date"><?php echo date('H:i:s', strtotime($entry['timestamp'])); ?> - Operatore: <?php echo $entry['username']; ?></span>
                    </div>
                    <div class="entry-content">
                        <?php
                            $lines = explode("\n", htmlspecialchars($entry['content']));
                            foreach ($lines as $line_num => $line) {
                                echo '<div class="line-numbers"><span class="user-prefix">' . $entry['username'] . ':</span> ' . nl2br($line) . '</div>';
                            }
                        ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-book-open"></i>
                <p>Nessun rapporto operativo trovato.</p>
                <a href="diary.php" class="btn btn-outline"><i class="fas fa-plus"></i> Crea il primo rapporto</a>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="system-status">
        <span>SISTEMA: <span class="blinking">●</span> OPERATIVO</span>
        <span>SESSIONE: <?php echo session_id(); ?></span>
        <span>ULTIMO ACCESSO: <?php echo date('d/m/Y H:i:s'); ?></span>
    </div>

    <script>
        // Animazione avanzata per gli elementi della dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Animazione per le statistiche
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';

                setTimeout(function() {
                    card.style.transition = 'all 0.6s cubic-bezier(0.22, 1, 0.36, 1)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 300 + (index * 100));
            });
            
            // Animazione per le voci del diario
            const diaryEntries = document.querySelectorAll('.diary-entry');
            diaryEntries.forEach((entry, index) => {
                entry.style.opacity = '0';
                entry.style.transform = 'translateX(-20px)';

                setTimeout(function() {
                    entry.style.transition = 'all 0.5s ease';
                    entry.style.opacity = '1';
                    entry.style.transform = 'translateX(0)';
                }, 800 + (index * 100));
            });
        });
    </script>
</body>
</html>