<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>SaftladenSuite POS</title>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;700&family=Lato&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/common.css">
    <style>
        .pos-display {
            background: rgba(0, 0, 0, 0.7);
            border: 2px solid #00ffcc;
            border-radius: 20px;
            padding: 40px;
            margin: 20px auto;
            max-width: 600px;
            box-shadow: 0 0 30px rgba(0, 255, 204, 0.2);
            text-align: center;
        }

        .product-name {
            font-family: 'Oswald', sans-serif;
            font-size: 2.5rem;
            color: #fff;
            margin-bottom: 15px;
            line-height: 1.2;
        }

        .price-tag {
            font-family: 'Oswald', sans-serif;
            font-size: 6rem;
            color: #00ffcc;
            font-weight: bold;
            margin: 10px 0;
            text-shadow: 0 0 15px rgba(0, 255, 204, 0.4);
        }

        .match-info {
            font-size: 0.9rem;
            color: #555;
            margin-top: 20px;
            border-top: 1px solid #333;
            padding-top: 10px;
        }

        .status-monitor {
            background: #111;
            border: 1px solid #444;
            color: #0f0;
            padding: 15px;
            font-family: monospace;
            font-size: 0.85rem;
            text-align: left;
            margin: 30px auto;
            max-width: 600px;
            border-radius: 5px;
        }
    </style>
</head>

<body>
    <a href="Home.html" class="logo">
        <img src="https://iili.io/f8QkeA7.png" alt="Logo">
        <span class="logo-text">SaftladenSuite</span>
    </a>

    <div class="content">
        <h1 class="title aurora-text">SaftladenSuite <sup>pro max</sup></h1>

        <div class="barcode-container">
            <form action="#" method="post" class="barcode-form">
                <label for="search">Barcode scannen</label>
                <div class="barcode-input-wrapper">
                    <input type="text" id="search" name="search" placeholder="Hier scannen..." autofocus
                        autocomplete="off">
                    <div class="input-glow"></div>
                </div>
                <button type="submit" class="barcode-btn">Suchen</button>
            </form>
        </div>

        <div class="results">
            <?php
            function cleanString($str)
            {
                if (empty($str))
                    return "";
                return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $str));
            }

            if (!empty(trim($_POST['search'] ?? ''))) {
                $search = trim($_POST['search']);
                $debugLog = ["Scan: " . htmlspecialchars($search)];

                try {
                    $dsn = "odbc:DSN=JonaTLan;TrustServerCertificate=yes;";
                    $pdo = new PDO($dsn, "sa", "sa04jT14");
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $debugLog[] = "DB: OK";

                    // 1. API Abfrage (cURL)
                    $offName = "";
                    $api_url = "https://world.openfoodfacts.org/api/v0/product/" . urlencode($search) . ".json";

                    if (function_exists('curl_version')) {
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $api_url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_USERAGENT, 'SaftladenPOS/1.0');
                        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fix for XAMPP SSL issues
                        $res = curl_exec($ch);
                        curl_close($ch);
                    } else {
                        $res = @file_get_contents($api_url, false, stream_context_create(["http" => ["timeout" => 3]]));
                    }

                    if ($res) {
                        $json = json_decode($res, true);
                        if (isset($json['status']) && $json['status'] == 1) {
                            $brands = $json['product']['brands'] ?? "";
                            $name = $json['product']['product_name_de'] ?? $json['product']['product_name'] ?? "";

                            if (!empty($brands) && stripos($name, $brands) === false) {
                                if (strpos($brands, ',') !== false) {
                                    $brands = explode(',', $brands)[0];
                                }
                                $offName = trim($brands . " " . $name);
                            } else {
                                $offName = trim($name);
                            }
                        }
                    }
                    $debugLog[] = "API: " . ($offName ?: "Kein Name im Web");

                    $finalMatch = null;
                    $score = 0;
                    $safeSearch = str_replace("'", "''", $search);

                    // 2. Direktsuche
                    $sqlDirect = "SELECT TOP 1 a.fVKNetto, b.cName FROM dbo.tArtikel a 
                                  LEFT JOIN dbo.tArtikelBeschreibung b ON a.kArtikel = b.kArtikel 
                                  WHERE a.cArtNr = '$safeSearch' OR a.cBarcode = '$safeSearch'";

                    // WICHTIG: prepare statt query nutzen für Stabilität
                    $stmtDirect = $pdo->prepare($sqlDirect);
                    $stmtDirect->execute();
                    $direct = $stmtDirect->fetch(PDO::FETCH_ASSOC);

                    // DER LEBENSRETTENDE BEFEHL: Hörer auflegen!
                    $stmtDirect->closeCursor();

                    if ($direct) {
                        $finalMatch = ['name' => $direct['cName'], 'preis' => $direct['fVKNetto']];
                        $score = 100;
                        $debugLog[] = "Direkttreffer: JA";
                    } else {
                        $debugLog[] = "Direkttreffer: NEIN";

                        // 3. Fuzzy Match
                        if (!empty($offName)) {
                            $debugLog[] = "Fuzzy gestartet...";
                            try {
                                $cleanOFF = cleanString($offName);
                                $sqlFuzzy = "SELECT b.cName, a.fVKNetto FROM dbo.tArtikel a JOIN dbo.tArtikelBeschreibung b ON a.kArtikel = b.kArtikel WHERE b.cName IS NOT NULL";

                                $stmtFuzzy = $pdo->prepare($sqlFuzzy);
                                $stmtFuzzy->execute();

                                $count = 0;
                                $bestName = "";

                                while ($row = $stmtFuzzy->fetch(PDO::FETCH_ASSOC)) {
                                    $count++;
                                    $cleanJTL = cleanString($row['cName']);
                                    if (empty($cleanJTL))
                                        continue;

                                    $wordsOFF = preg_split('/[^a-zA-Z0-9]+/', strtolower($offName), -1, PREG_SPLIT_NO_EMPTY);
                                    $matchedWords = 0;
                                    $targetLower = strtolower($row['cName']);
                                    foreach ($wordsOFF as $w) {
                                        // Match whole words or substrings for robustness
                                        if (strpos($targetLower, $w) !== false) {
                                            $matchedWords++;
                                        }
                                    }
                                    $wordScore = (count($wordsOFF) > 0) ? ($matchedWords / count($wordsOFF)) * 100 : 0;

                                    $lev = levenshtein($cleanOFF, $cleanJTL);
                                    $maxLen = max(strlen($cleanOFF), strlen($cleanJTL));
                                    $levScore = ($maxLen > 0) ? (1 - $lev / $maxLen) * 100 : 0;

                                    $currentScore = ($wordScore * 0.7) + ($levScore * 0.3);

                                    if ($currentScore > $score) {
                                        $score = $currentScore;
                                        $finalMatch = ['name' => $row['cName'], 'preis' => $row['fVKNetto']];
                                        $bestName = $row['cName'];
                                    }
                                }

                                // Auch hier sauber schließen
                                $stmtFuzzy->closeCursor();

                                $debugLog[] = "Artikel geprüft: $count";
                                $debugLog[] = "Bester Score: " . round($score, 1) . "% ($bestName)";
                            } catch (Exception $eFuzzy) {
                                $debugLog[] = "FUZZY FEHLER: " . $eFuzzy->getMessage();
                            }
                        }
                    }

                    // 4. Anzeige
                    if ($finalMatch && $score >= 1) {
                        $brutto = number_format($finalMatch['preis'] * 1.19, 2, ',', '.');
                        echo "<div class='pos-display fade-in'>";
                        echo "<div class='product-name'>" . htmlspecialchars($finalMatch['name'] ?? 'Unbekannt') . "</div>";
                        echo "<div class='price-tag'>$brutto €</div>";
                        echo "<div class='match-info'>Scan: $search | Match: " . round($score, 1) . "%</div>";
                        echo "</div>";
                    } else {
                        echo "<div class='info-box'>Produkt nicht gefunden.</div>";
                    }

                    // STATUS MONITOR
                    echo "<div class='status-monitor'>";
                    echo "<strong>MONITOR:</strong><br>";
                    foreach ($debugLog as $log) {
                        echo htmlspecialchars($log) . "<br>";
                    }
                    echo "</div>";

                } catch (Exception $e) {
                    echo "<div class='error-box'>Datenbank-Fehler.</div>";
                    echo "<div class='status-monitor'>" . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }
            ?>
        </div>
    </div>
</body>

</html>