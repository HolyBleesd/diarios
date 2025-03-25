<?php
require_once 'config.php';

// Reindirizza se l'utente è già loggato
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';

// Gestione del login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = clean_input($_POST['username']);
    $password = $_POST['password'];
    
    // Verifica delle credenziali
    $sql = "SELECT id, username, password, is_admin FROM users WHERE username = '$username'";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Login riuscito
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = $user['is_admin'];
            
            redirect('dashboard.php');
        } else {
            $error = "Password non valida";
        }
    } else {
        $error = "Utente non trovato";
    }
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIARIO - Sistema di Accesso Sicuro</title>
    <link rel="stylesheet" href="fbi-style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Fira+Code:wght@400;500;600&display=swap">
    <?php
    // Includi il caricatore di temi solo se l'utente è loggato
    if (isset($_SESSION['user_id'])) {
        require_once 'theme_loader.php';
    }
    ?>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            position: relative;
            overflow: hidden;
            background-color: #000;
        }

        /* Background data animation */
        .data-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            opacity: 0.2;
            overflow: hidden;
            perspective: 1000px;
        }

        .data-stream {
            position: absolute;
            color: #00ff00;
            font-family: var(--mono-font);
            font-size: 18px;
            white-space: nowrap;
            text-shadow: 0 0 5px rgba(0, 255, 0, 0.7);
            animation-name: datafall;
            animation-timing-function: linear;
            animation-iteration-count: infinite;
        }

        .data-stream.blue {
            color: #0088ff;
            text-shadow: 0 0 5px rgba(0, 136, 255, 0.7);
        }

        .data-stream.red {
            color: #ff3333;
            text-shadow: 0 0 5px rgba(255, 51, 51, 0.7);
        }

        .data-stream.yellow {
            color: #ffcc00;
            text-shadow: 0 0 5px rgba(255, 204, 0, 0.7);
        }

        .data-stream.purple {
            color: #cc33ff;
            text-shadow: 0 0 5px rgba(204, 51, 255, 0.7);
        }

        @keyframes datafall {
            from { transform: translateY(-100%) translateX(0) rotateY(0deg); }
            to { transform: translateY(100vh) translateX(var(--x-shift)) rotateY(var(--rotate-y)); }
        }

        /* Floating data elements */
        .floating-data {
            position: absolute;
            font-family: var(--mono-font);
            color: var(--accent-light);
            opacity: 0.15;
            text-shadow: 0 0 5px var(--accent-color);
            animation: float-data 15s linear infinite;
            z-index: 0;
        }

        @keyframes float-data {
            0% { transform: translate(0, 0) rotate(0deg); opacity: 0; }
            10% { opacity: 0.2; }
            90% { opacity: 0.2; }
            100% { transform: translate(var(--x-end), var(--y-end)) rotate(var(--rotate-end)); opacity: 0; }
        }

        /* FBI Logo */
        .fbi-logo {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2;
            text-align: center;
        }

        .fbi-logo h1 {
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: 10px;
            color: var(--accent-color);
            margin: 0;
            text-shadow: 0 0 10px rgba(0, 51, 102, 0.5);
        }

        .fbi-logo p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            letter-spacing: 3px;
            margin-top: 5px;
            font-family: var(--mono-font);
        }

        /* Login form */
        .login-form {
            position: relative;
            z-index: 1;
            background-color: rgba(10, 22, 34, 0.95);
            padding: 40px;
            border-radius: 8px;
            width: 50%;
            max-width: 800px;
            min-width: 600px;
            box-shadow: 0 0 50px rgba(0, 51, 102, 0.5);
            border: 2px solid var(--accent-color);
            margin-top: 50px;
        }

        .login-form::before {
            content: 'RESTRICTED ACCESS';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            padding: 8px;
            background: var(--secondary-color);
            color: var(--text-primary);
            text-align: center;
            font-size: 0.8rem;
            letter-spacing: 3px;
            font-weight: bold;
            border-radius: 4px 4px 0 0;
        }

        .login-form h2 {
            text-align: center;
            margin-top: 40px;
            margin-bottom: 40px;
            color: var(--text-primary);
            font-size: 2.2rem;
            letter-spacing: 2px;
            text-shadow: 0 0 10px rgba(0, 153, 255, 0.3);
        }

        .login-form .form-group {
            margin-bottom: 35px;
        }

        .login-form .form-group label {
            display: block;
            margin-bottom: 15px;
            color: var(--accent-light);
            font-size: 1.2rem;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-weight: 500;
        }

        .login-form .form-group input {
            width: 100%;
            padding: 18px 20px;
            background-color: rgba(0, 0, 0, 0.3);
            color: var(--terminal-green);
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 1.3rem;
            font-family: var(--mono-font);
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.5);
        }

        .login-form .form-group button {
            width: 100%;
            padding: 20px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.3rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            letter-spacing: 2px;
            text-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .login-form .form-group button:hover {
            background-color: var(--accent-dark);
        }

        /* Classified stamp */
        .classified-stamp {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 5rem;
            color: rgba(153, 0, 0, 0.15);
            font-weight: bold;
            pointer-events: none;
            border: 10px solid rgba(153, 0, 0, 0.15);
            padding: 10px 20px;
            text-transform: uppercase;
            letter-spacing: 5px;
            z-index: 0;
        }

        /* System info */
        .system-info {
            position: fixed;
            bottom: 15px;
            left: 15px;
            font-size: 0.75rem;
            color: var(--text-secondary);
            font-family: var(--mono-font);
            opacity: 0.7;
            background-color: rgba(0, 0, 0, 0.5);
            padding: 10px;
            border-radius: 3px;
            border-left: 3px solid var(--accent-color);
        }

        .system-info span {
            display: block;
            margin-bottom: 3px;
        }

        .blinking {
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }

        /* Security camera effect */
        .camera-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(
                0deg,
                rgba(0, 0, 0, 0.1),
                rgba(0, 0, 0, 0.1) 1px,
                transparent 1px,
                transparent 2px
            );
            pointer-events: none;
            z-index: 3;
            opacity: 0.3;
        }

        .camera-corner {
            position: fixed;
            width: 50px;
            height: 50px;
            border: 3px solid rgba(0, 51, 102, 0.5);
            z-index: 3;
            pointer-events: none;
        }

        .camera-corner.top-left {
            top: 10px;
            left: 10px;
            border-right: none;
            border-bottom: none;
        }

        .camera-corner.top-right {
            top: 10px;
            right: 10px;
            border-left: none;
            border-bottom: none;
        }

        .camera-corner.bottom-left {
            bottom: 10px;
            left: 10px;
            border-right: none;
            border-top: none;
        }

        .camera-corner.bottom-right {
            bottom: 10px;
            right: 10px;
            border-left: none;
            border-top: none;
        }

        .init-link {
            text-align: center;
            margin-top: 20px;
        }

        .init-link a {
            color: var(--text-secondary);
            font-size: 0.8rem;
            text-decoration: none;
            transition: color 0.3s;
        }

        .init-link a:hover {
            color: var(--accent-light);
        }
    </style>
</head>
<body>
    <!-- Effetto camera di sorveglianza -->
    <div class="camera-overlay"></div>
    <div class="camera-corner top-left"></div>
    <div class="camera-corner top-right"></div>
    <div class="camera-corner bottom-left"></div>
    <div class="camera-corner bottom-right"></div>

    <!-- Animazione dati in background -->
    <div class="data-background" id="dataBackground"></div>

    <!-- Logo FBI -->
    <div class="fbi-logo">
        <h1>DIARIO</h1>
        <p>SISTEMA OPERATIVO SICURO</p>
    </div>

    <div class="classified-stamp">Classified</div>

    <div class="login-form">
        <h2>AUTENTICAZIONE OPERATORE</h2>

        <?php if ($error): ?>
            <div class="error-message"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <div class="form-group">
                <label for="username"><i class="fas fa-user-shield"></i> CODICE OPERATORE</label>
                <input type="text" id="username" name="username" placeholder="Inserisci il tuo codice operatore" required autofocus>
            </div>

            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> PASSWORD DI ACCESSO</label>
                <input type="password" id="password" name="password" placeholder="Inserisci la tua password" required>
            </div>

            <div class="form-group">
                <button type="submit"><i class="fas fa-sign-in-alt"></i> ACCEDI AL SISTEMA</button>
            </div>
        </form>

        <div class="init-link">
            <a href="init.php"><i class="fas fa-database"></i> Inizializza Database</a>
        </div>
    </div>

    <div class="system-info">
        <span>SISTEMA: <span class="blinking">●</span> OPERATIVO</span>
        <span>VERSIONE: 3.1.5</span>
        <span>ACCESSO: LIMITATO</span>
        <span>STATO: SICURO</span>
        <span>IP: <?php echo $_SERVER['REMOTE_ADDR']; ?></span>
        <span>DATA: <?php echo date('d/m/Y H:i:s'); ?></span>
    </div>

    <script>
        // Generazione dei flussi di dati in background
        document.addEventListener('DOMContentLoaded', function() {
            const dataBackground = document.getElementById('dataBackground');
            const characters = '01ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz!@#$%^&*()_+-=[]{}|;:,./<>?';
            const codeSnippets = [
                'ACCESS GRANTED', 'SECURITY PROTOCOL', 'ENCRYPTION KEY', 'CLASSIFIED DATA',
                'AUTHORIZATION REQUIRED', 'SYSTEM BREACH', 'FIREWALL ACTIVE', 'SCANNING DATABASE',
                'IDENTITY VERIFIED', 'TRACKING ENABLED', 'SATELLITE UPLINK', 'DECRYPTION SEQUENCE',
                'BIOMETRIC SCAN', 'NEURAL NETWORK', 'QUANTUM ENCRYPTION', 'FACIAL RECOGNITION',
                'VOICE PATTERN MATCH', 'GPS COORDINATES', 'SURVEILLANCE ACTIVE', 'THREAT DETECTED'
            ];

            // Crea 80 stream di dati
            for (let i = 0; i < 80; i++) {
                createDataStream(dataBackground, characters);
            }

            // Crea elementi di dati fluttuanti
            for (let i = 0; i < 20; i++) {
                createFloatingData(dataBackground, codeSnippets);
            }

            // Effetto di digitazione per il titolo
            const title = document.querySelector('.fbi-logo h1');
            const originalText = title.textContent;
            title.textContent = '';

            let i = 0;
            const typeWriter = () => {
                if (i < originalText.length) {
                    title.textContent += originalText.charAt(i);
                    i++;
                    setTimeout(typeWriter, 150);
                }
            };

            setTimeout(typeWriter, 500);
        });

        // Funzione per creare un singolo stream di dati
        function createDataStream(container, chars) {
            const stream = document.createElement('div');

            // Colori casuali per gli stream
            const colorClasses = ['', 'blue', 'red', 'yellow', 'purple'];
            const colorClass = colorClasses[Math.floor(Math.random() * colorClasses.length)];
            stream.className = 'data-stream ' + colorClass;

            // Posizione casuale orizzontale
            const left = Math.random() * 100;
            stream.style.left = `${left}%`;

            // Velocità casuale
            const duration = 5 + Math.random() * 20;
            stream.style.animationDuration = `${duration}s`;

            // Ritardo casuale
            const delay = Math.random() * 8;
            stream.style.animationDelay = `${delay}s`;

            // Movimento orizzontale casuale
            const xShift = -100 + Math.random() * 200;
            stream.style.setProperty('--x-shift', `${xShift}px`);

            // Rotazione 3D casuale
            const rotateY = -30 + Math.random() * 60;
            stream.style.setProperty('--rotate-y', `${rotateY}deg`);

            // Genera il contenuto del flusso di dati
            let content = '';
            const length = 50 + Math.floor(Math.random() * 150);

            for (let i = 0; i < length; i++) {
                content += chars.charAt(Math.floor(Math.random() * chars.length));
            }

            stream.textContent = content;
            container.appendChild(stream);

            // Rigenera lo stream quando l'animazione finisce
            stream.addEventListener('animationiteration', function() {
                let newContent = '';
                for (let i = 0; i < length; i++) {
                    newContent += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                stream.textContent = newContent;

                // Cambia anche la traiettoria
                const newXShift = -100 + Math.random() * 200;
                stream.style.setProperty('--x-shift', `${newXShift}px`);

                const newRotateY = -30 + Math.random() * 60;
                stream.style.setProperty('--rotate-y', `${newRotateY}deg`);
            });
        }

        // Funzione per creare elementi di dati fluttuanti
        function createFloatingData(container, snippets) {
            const floatingEl = document.createElement('div');
            floatingEl.className = 'floating-data';

            // Posizione iniziale casuale
            const startX = Math.random() * 100;
            const startY = Math.random() * 100;
            floatingEl.style.left = `${startX}vw`;
            floatingEl.style.top = `${startY}vh`;

            // Dimensione casuale
            const size = 1 + Math.random() * 2;
            floatingEl.style.fontSize = `${size}rem`;

            // Movimento finale casuale
            const xEnd = -300 + Math.random() * 600;
            const yEnd = -300 + Math.random() * 600;
            const rotateEnd = -360 + Math.random() * 720;

            floatingEl.style.setProperty('--x-end', `${xEnd}px`);
            floatingEl.style.setProperty('--y-end', `${yEnd}px`);
            floatingEl.style.setProperty('--rotate-end', `${rotateEnd}deg`);

            // Durata e ritardo casuali
            const duration = 10 + Math.random() * 20;
            const delay = Math.random() * 10;

            floatingEl.style.animationDuration = `${duration}s`;
            floatingEl.style.animationDelay = `${delay}s`;

            // Contenuto casuale
            const snippet = snippets[Math.floor(Math.random() * snippets.length)];
            floatingEl.textContent = snippet;

            container.appendChild(floatingEl);

            // Rigenera con nuovo contenuto e posizione quando l'animazione finisce
            floatingEl.addEventListener('animationiteration', function() {
                const newStartX = Math.random() * 100;
                const newStartY = Math.random() * 100;
                floatingEl.style.left = `${newStartX}vw`;
                floatingEl.style.top = `${newStartY}vh`;

                const newXEnd = -300 + Math.random() * 600;
                const newYEnd = -300 + Math.random() * 600;
                const newRotateEnd = -360 + Math.random() * 720;

                floatingEl.style.setProperty('--x-end', `${newXEnd}px`);
                floatingEl.style.setProperty('--y-end', `${newYEnd}px`);
                floatingEl.style.setProperty('--rotate-end', `${newRotateEnd}deg`);

                const newSnippet = snippets[Math.floor(Math.random() * snippets.length)];
                floatingEl.textContent = newSnippet;
            });
        }
    </script>
</body>
</html>