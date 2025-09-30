<?php

function exportDataToCsv() {
    // 1. Nastavení připojení k databázi
    $host = 'localhost';
    $dbname = 'janbrunclik';
    $user = 'root';
    $pass = 'Spr.8601222179.,';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        die("Chyba při připojení k databázi: " . $e->getMessage());
    }

    // 2. SQL dotaz
    $sql = "SELECT `datum`, `jmeno`, `ucet`, `castka`, `stav`, `prani`, `nick`, `link`, `faktura`, '1' AS 'created_by', `znacka` AS 'created_at', `znacka` AS 'updated_at' FROM `prani_video` LIMIT 1000";

    // 3. Spuštění dotazu a příprava výstupu
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($results)) {
        return "Žádná data k exportu.";
    }

    // 4. Vytvoření CSV souboru
    $filename = 'export_' . date('Y-m-d_H-i-s') . '.csv';
    $file = fopen($filename, 'w');

    // Hlavičky
    //$headers = array_keys($results[0]);
   // fputcsv($file, $headers);

    // Data
    foreach ($results as $row) {
        fputcsv($file, $row);
    }

    fclose($file);

    return "Data byla úspěšně exportována do souboru: " . $filename;
}

// Spuštění funkce
echo exportDataToCsv();

?>