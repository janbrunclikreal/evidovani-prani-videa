<?php

function export_data() {
    // 1. Nastavení připojení k databázi
    $servername = "localhost";
    $username = "root";
    $password = "Spr.8601222179.,";
    $dbname = "janbrunclik";
    
    // Vytvoření připojení
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Kontrola připojení
    if ($conn->connect_error) {
        die("Chyba při připojení k databázi: " . $conn->connect_error);
    }

    // 2. SQL dotaz pro výběr dat z 'prani_video'
    $sql = "SELECT `datum`, `jmeno`, `ucet`, `castka`, `stav`, `prani`, `nick`, `link`, `faktura`, '1' AS 'created_by', `znacka` AS 'created_at', `znacka` AS 'updated_at' FROM `prani_video` LIMIT 1000";
    
    // Provedení dotazu
    $result = $conn->query($sql);
    
    // 3. Vytvoření výstupního souboru s aktuálním datem a časem
    $datetime = date("Y-m-d_H-i-s");
    $outputFile = "export_records_" . $datetime . ".sql";
    $file = fopen($outputFile, "w") or die("Nelze otevřít soubor!");

    // 4. Přidání SQL příkazu pro vyprázdnění tabulky do souboru
    fwrite($file, "TRUNCATE TABLE `records`;\n");

    if ($result->num_rows > 0) {
        // Zpracování každého řádku a vytvoření INSERT příkazů
        while($row = $result->fetch_assoc()) {
            // Sestavení SQL dotazu pro vložení do tabulky 'records'
            $insertSql = "INSERT INTO `records` (`datum`, `jmeno`, `ucet`, `castka`, `stav`, `prani`, `nick`, `link`, `faktura`, `created_by`, `created_at`, `updated_at`) VALUES (";
            $insertSql .= "'" . $conn->real_escape_string($row['datum'] ?? '') . "', ";
            $insertSql .= "'" . $conn->real_escape_string($row['jmeno'] ?? '') . "', ";
            $insertSql .= "'" . $conn->real_escape_string($row['ucet'] ?? '') . "', ";
            $insertSql .= "'" . $conn->real_escape_string($row['castka'] ?? '') . "', ";
            $insertSql .= "'" . $conn->real_escape_string($row['stav'] ?? '') . "', ";
            $insertSql .= "'" . $conn->real_escape_string($row['prani'] ?? '') . "', ";
            $insertSql .= "'" . $conn->real_escape_string($row['nick'] ?? '') . "', ";
            $insertSql .= "'" . $conn->real_escape_string($row['link'] ?? '') . "', ";
            $insertSql .= "'" . $conn->real_escape_string($row['faktura'] ?? '') . "', ";
            $insertSql .= "'" . $conn->real_escape_string($row['created_by'] ?? '') . "', ";
            $insertSql .= "'" . $conn->real_escape_string($row['created_at'] ?? '') . "', ";
            $insertSql .= "'" . $conn->real_escape_string($row['updated_at'] ?? '') . "');\n";
            
            // Zápis do souboru
            fwrite($file, $insertSql);
        }
    } else {
        echo "0 výsledků";
    }

    // Uzavření souboru a připojení k databázi
    fclose($file);
    $conn->close();

    echo "Export dat do souboru '$outputFile' byl úspěšně dokončen.\n";
}

// Spuštění funkce
export_data();

?>