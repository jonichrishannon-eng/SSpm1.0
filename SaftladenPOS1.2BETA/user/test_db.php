<?php
try {
    $dsn = "odbc:DSN=JonaTLan;TrustServerCertificate=yes;";
    $pdo = new PDO($dsn, "sa", "sa04jT14");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "DB OK\n";
    
    $stmt = $pdo->query("SELECT TOP 5 a.cBarcode, b.cName, a.kArtikel FROM dbo.tArtikel a LEFT JOIN dbo.tArtikelBeschreibung b ON a.kArtikel = b.kArtikel WHERE b.cName LIKE '%Spezi%'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
