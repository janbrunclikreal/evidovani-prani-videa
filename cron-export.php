<?php

// Nastavení
$servername = "localhost";
$username = "root";
$password = "Spr.8601222179.,";
$dbname = "janbrunclik";
$targetDbname = "janbrunclik_evidence";
$sourceTable = "prani_video";
$targetTable = "records";

// Funkce pro logování do souboru s dynamickým názvem
function logMessage($message) {
    $logDir = 'logs/' . date('Y-m-d');
    
    // Vytvoření složky, pokud neexistuje
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Dynamický název souboru s datem a časem
    $logFile = $logDir . '/export_' . date('Y-m-d_H-i-s') . '.log';
    
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Zaznamenání začátku migrace
logMessage("START: Migrace dat ze `$dbname`.`$sourceTable` do `$targetDbname`.`$targetTable`.");

// Vytvoření připojení k databázi
$conn = new mysqli($servername, $username, $password);

// Kontrola připojení
if ($conn->connect_error) {
    $errorMessage = "Chyba při připojení k databázi: " . $conn->connect_error;
    logMessage("ERROR: $errorMessage");
    echo "ERROR";
    die();
}

// Spuštění transakce
$conn->begin_transaction();

try {
    // 1. Získání počtu řádků
    $countSql = "SELECT COUNT(*) AS total_rows FROM `$dbname`.`$sourceTable`";
    $result = $conn->query($countSql);
    $totalRows = $result->fetch_assoc()['total_rows'];
    logMessage("INFO: Nalezeno celkem $totalRows řádků k přenesení.");

    // 2. Vyčištění cílové tabulky
    $sqlTruncate = "TRUNCATE TABLE `$targetDbname`.`$targetTable`;";
    $conn->query($sqlTruncate);
    logMessage("INFO: Tabulka `$targetTable` byla vyprázdněna.");

    // 3. Vložení dat
    $sqlInsertSelect = "
        INSERT INTO `$targetDbname`.`$targetTable` (
            `datum`, `jmeno`, `ucet`, `castka`, `stav`, `prani`, `nick`, `link`, `faktura`, `created_by`, `created_at`, `updated_at`
        )
        SELECT
            `datum`, `jmeno`, `ucet`, `castka`, `stav`, `prani`, `nick`, `link`, `faktura`, '1' AS `created_by`, `znacka` AS `created_at`, `znacka` AS `updated_at`
        FROM
            `$dbname`.`$sourceTable`;
    ";
    $conn->query($sqlInsertSelect);
    $affectedRows = $conn->affected_rows;
    logMessage("INFO: Úspěšně vloženo $affectedRows řádků.");

    // Potvrzení transakce
    $conn->commit();
    logMessage("SUCCESS: Transakce byla úspěšně dokončena.");
    echo "OK";

} catch (mysqli_sql_exception $e) {
    // V případě chyby vrátit transakci zpět
    $conn->rollback();
    $errorMessage = "Chyba: Transakce byla vrácena zpět. " . $e->getMessage();
    logMessage("ERROR: $errorMessage");
    echo "ERROR";
    
} finally {
    // Uzavření připojení
    $conn->close();
    logMessage("END: Připojení k databázi uzavřeno.");
}

?>