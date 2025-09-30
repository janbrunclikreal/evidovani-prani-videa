<?php
session_start();

// Kontrola, zda již existuje config.php
$configExists = file_exists('./config.php');
$installCompleted = false;

// Zpracování formuláře
if ($_POST) {
    $errors = [];
    $success = false;
    
    // Validace vstupů
    $dbHost = trim($_POST['db_host'] ?? '');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';
    $dbPort = trim($_POST['db_port'] ?? '3306');
    $appName = trim($_POST['app_name'] ?? 'Evidence přání videií');
    $adminPassword = trim($_POST['admin_password'] ?? 'admin123');
    
    if (empty($dbHost)) $errors[] = "Zadejte databázový server";
    if (empty($dbName)) $errors[] = "Zadejte název databáze";
    if (empty($dbUser)) $errors[] = "Zadejte databázového uživatele";
    if (empty($adminPassword)) $errors[] = "Zadejte heslo pro administrátora";
    
    if (empty($errors)) {
        try {
            // Test připojení k databázi
            $dsn = "mysql:host=$dbHost;port=$dbPort;charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
            
            // Vytvoření databáze pokud neexistuje
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");
            
            // Vytvoření tabulky users
            $createUsersTable = "
                CREATE TABLE IF NOT EXISTS `users` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `username` varchar(50) NOT NULL UNIQUE,
                    `password` varchar(255) NOT NULL,
                    `role` enum('admin','user') NOT NULL DEFAULT 'user',
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_username` (`username`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
            
            $pdo->exec($createUsersTable);

            // Vytvoření tabulky records
            $createRecordsTable = "
                CREATE TABLE IF NOT EXISTS `records` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `datum` date NOT NULL,
                    `jmeno` varchar(100) NOT NULL,
                    `ucet` varchar(50) DEFAULT NULL,
                    `castka` decimal(10,2) DEFAULT NULL,
                    `stav` enum('zaplaceno','zaslano','odmitnuto','rozpracovane') NOT NULL DEFAULT 'rozpracovane',
                    `prani` text,
                    `nick` varchar(50) DEFAULT NULL,
                    `link` varchar(255) DEFAULT NULL,
                    `faktura` varchar(100) DEFAULT NULL,
                    `created_by` int(11) DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_datum` (`datum`),
                    KEY `idx_stav` (`stav`),
                    KEY `idx_nick` (`nick`),
                    KEY `fk_created_by` (`created_by`),
                    CONSTRAINT `fk_records_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
            
            $pdo->exec($createRecordsTable);
            
            // Vytvoření výchozích uživatelů (heslo: password)
            $defaultPassword = password_hash('password', PASSWORD_DEFAULT);
            $adminPasswordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
            
            $createUsers = "
                INSERT IGNORE INTO `users` (`id`, `username`, `password`, `role`) VALUES
                (1, 'admin', '$adminPasswordHash', 'admin'),
                (2, 'user', '$defaultPassword', 'user');
            ";
            
            $pdo->exec($createUsers);
            
            // Vložení ukázkových dat pro records
            $sampleData = "
                INSERT IGNORE INTO `records` (`id`, `datum`, `jmeno`, `ucet`, `castka`, `stav`, `prani`, `nick`, `link`, `faktura`, `created_by`) VALUES
                (1, '2025-09-20', 'Jan Novák', 'CZ1234567890', 500.00, 'zaplaceno', 'Ukázkové přání #1', 'jannovak', 'https://example.com/1', 'FAK001', 1),
                (2, '2025-09-21', 'Marie Svobodová', 'CZ0987654321', 750.50, 'zaslano', 'Ukázkové přání #2', 'marie.s', 'https://example.com/2', 'FAK002', 1),
                (3, '2025-09-22', 'Petr Dvořák', 'CZ1122334455', 300.00, 'rozpracovane', 'Ukázkové přání #3', 'petr_d', 'https://example.com/3', NULL, 2),
                (4, '2025-09-23', 'Anna Nováková', 'CZ5566778899', 1200.00, 'odmitnuto', 'Ukázkové přání #4', 'anna.nova', 'https://example.com/4', NULL, 2);
            ";
            
            $pdo->exec($sampleData);
            
            // Vytvoření config.php
            $configContent = "<?php
// Konfigurace databáze
define('DB_HOST', '$dbHost');
define('DB_NAME', '$dbName');
define('DB_USER', '$dbUser');
define('DB_PASS', '$dbPass');
define('DB_CHARSET', 'utf8mb4');

// Konfigurace aplikace
define('APP_NAME', '$appName');
define('APP_VERSION', '3.1.1');
define('RECORDS_PER_PAGE', 10);

// Bezpečnostní nastavení
define('SESSION_TIMEOUT', 3600); // 1 hodina

// Cesty
define('BASE_URL', 'http://localhost:7001');
define('UPLOAD_DIR', 'uploads/');

// Nastavení časového pásma
date_default_timezone_set('Europe/Prague');

// Error reporting (pro development)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>";
            
            if (file_put_contents('./config.php', $configContent)) {
                $success = true;
                $installCompleted = true;
                $_SESSION['install_success'] = true;
            } else {
                $errors[] = "Nepodařilo se vytvořit konfigurační soubor";
            }
            
        } catch (PDOException $e) {
            $errors[] = "Chyba databáze: " . $e->getMessage();
        } catch (Exception $e) {
            $errors[] = "Obecná chyba: " . $e->getMessage();
        }
    }
}

// Kontrola úspěšné instalace z předchozí session
if (isset($_SESSION['install_success'])) {
    $installCompleted = true;
    unset($_SESSION['install_success']);
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalace Evidence aplikace</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .install-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            max-width: 600px;
            width: 100%;
        }

        .install-header {
            background: linear-gradient(45deg, #2c3e50, #34495e);
            color: white;
            text-align: center;
            padding: 40px 30px;
        }

        .install-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .install-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .install-content {
            padding: 40px 30px;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin: 0 10px;
            position: relative;
        }

        .step.active {
            background: #007bff;
            color: white;
        }

        .step.completed {
            background: #28a745;
            color: white;
        }

        .step::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 100%;
            width: 20px;
            height: 2px;
            background: #e9ecef;
            transform: translateY(-50%);
        }

        .step:last-child::after {
            display: none;
        }

        .step.completed::after {
            background: #28a745;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.25);
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,123,255,0.4);
        }

        .btn-success {
            background: linear-gradient(45deg, #28a745, #218838);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40,167,69,0.4);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left-color: #dc3545;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left-color: #28a745;
        }

        .alert ul {
            margin: 0;
            padding-left: 20px;
        }

        .installation-complete {
            text-align: center;
            padding: 40px 0;
        }

        .installation-complete .icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }

        .installation-complete h2 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 2rem;
        }

        .installation-complete p {
            color: #6c757d;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }

        .installation-complete .links {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .installation-complete .links a {
            display: inline-block;
            padding: 12px 24px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .installation-complete .links a:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        .installation-complete .links a.secondary {
            background: #6c757d;
        }

        .installation-complete .links a.secondary:hover {
            background: #545b62;
        }

        .requirements {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .requirements h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .requirements ul {
            color: #6c757d;
            padding-left: 20px;
        }

        .requirements li {
            margin-bottom: 5px;
        }

        .pre-install {
            text-align: center;
        }

        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }

        .loading.show {
            display: block;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .install-header h1 {
                font-size: 2rem;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .installation-complete .links {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1>🚀 Instalace aplikace</h1>
            <p>Evidence systém - Snadná instalace</p>
        </div>

        <div class="install-content">
            <?php if ($configExists && !$installCompleted): ?>
                <!-- Aplikace již nainstalována -->
                <div class="installation-complete">
                    <div class="icon">⚠️</div>
                    <h2>Aplikace je již nainstalována</h2>
                    <p>Konfigurační soubor již existuje. Pro reinstalaci jej nejdříve smažte.</p>
                    <div class="links">
                        <a href="index.html">Otevřít aplikaci</a>
                        <a href="test_kompletni.html" class="secondary">Testovací rozhraní</a>
                    </div>
                </div>

            <?php elseif ($installCompleted): ?>
                <!-- Instalace dokončena -->
                <div class="step-indicator">
                    <div class="step completed">1</div>
                    <div class="step completed">2</div>
                    <div class="step completed">3</div>
                </div>

                <div class="installation-complete">
                    <div class="icon">✅</div>
                    <h2>Instalace dokončena!</h2>
                    <p>Vaše aplikace byla úspěšně nainstalována a je připravena k použití.</p>
                    <div class="links">
                        <a href="index.html">Spustit aplikaci</a>
                        <a href="test_kompletni.html" class="secondary">Testovací rozhraní</a>
                    </div>
                </div>

            <?php else: ?>
                <!-- Instalační formulář -->
                <div class="step-indicator">
                    <div class="step active">1</div>
                    <div class="step">2</div>
                    <div class="step">3</div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <strong>Chyby při instalaci:</strong>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="requirements">
                    <h3>📋 Systémové požadavky</h3>
                    <ul>
                        <li>PHP 7.4 nebo vyšší</li>
                        <li>MySQL 5.7 nebo MariaDB 10.2+</li>
                        <li>PDO MySQL rozšíření</li>
                        <li>Oprávnění k zápisu do adresáře</li>
                    </ul>
                </div>

                <form method="POST" onsubmit="showLoading()">
                    <h3 style="margin-bottom: 20px; color: #2c3e50;">🔧 Konfigurace databáze</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="db_host">Databázový server *</label>
                            <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="db_port">Port</label>
                            <input type="number" id="db_port" name="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>" min="1" max="65535">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="db_name">Název databáze *</label>
                        <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="db_user">Uživatelské jméno *</label>
                            <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="db_pass">Heslo</label>
                            <input type="password" id="db_pass" name="db_pass" value="<?= htmlspecialchars($_POST['db_pass'] ?? '') ?>">
                        </div>
                    </div>

                    <hr style="margin: 30px 0; border: none; height: 1px; background: #e9ecef;">

                    <h3 style="margin-bottom: 20px; color: #2c3e50;">⚙️ Konfigurace aplikace</h3>

                    <div class="form-group">
                        <label for="app_name">Název aplikace</label>
                        <input type="text" id="app_name" name="app_name" value="<?= htmlspecialchars($_POST['app_name'] ?? 'Evidence přání videií') ?>">
                    </div>

                    <div class="form-group">
                        <label for="admin_password">Heslo pro administrátora *</label>
                        <input type="password" id="admin_password" name="admin_password" value="<?= htmlspecialchars($_POST['admin_password'] ?? '') ?>" required placeholder="Zadejte silné heslo pro admin účet">
                        <small style="color: #6c757d; font-size: 0.8rem; margin-top: 5px; display: block;">
                            Admin uživatel: <strong>admin</strong> | Standardní uživatel: <strong>user</strong> (heslo: password)
                        </small>
                    </div>

                    <div class="loading" id="loading">
                        <div class="spinner"></div>
                        <p>Probíhá instalace, prosím čekejte...</p>
                    </div>

                    <button type="submit" class="btn btn-primary" id="install-btn">
                        🚀 Spustit instalaci
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function showLoading() {
            document.getElementById('loading').classList.add('show');
            document.getElementById('install-btn').style.display = 'none';
        }

        // Automatické vyplnění některých polí
        document.addEventListener('DOMContentLoaded', function() {
            const dbNameField = document.getElementById('db_name');
            const dbUserField = document.getElementById('db_user');
            
            if (dbNameField && !dbNameField.value) {
                dbNameField.value = 'evidence_db';
            }
        });

        // Validace formuláře
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const requiredFields = ['db_host', 'db_name', 'db_user', 'admin_password'];
            let hasErrors = false;

            requiredFields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc3545';
                    hasErrors = true;
                } else {
                    field.style.borderColor = '#e9ecef';
                }
            });

            if (hasErrors) {
                e.preventDefault();
                alert('Prosím vyplňte všechna povinná pole označená *');
                document.getElementById('loading').classList.remove('show');
                document.getElementById('install-btn').style.display = 'block';
            }
        });
    </script>
</body>
</html>