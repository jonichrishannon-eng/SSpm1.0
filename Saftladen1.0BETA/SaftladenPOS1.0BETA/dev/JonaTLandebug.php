<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <title>SaftladenSuite POS v1.1</title>
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;700&family=Lato&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/cyber-terminal.css">
</head>

<body>
    <div class="background-effects">
        <div class="grid-overlay"></div>
        <div class="scan-line"></div>
        <div class="floating-code"></div>
    </div>

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

            function formatValue($val) {
                if (empty($val)) {
                    return '<span class="glitch">NO_DATA</span>';
                }
                return htmlspecialchars($val);
            }

            if (!empty(trim($_POST['search'] ?? ''))) {
                $search = trim($_POST['search']);
                $debugLog = ["Scan: " . htmlspecialchars($search)];
                $offData = null;
                $offName = "";
                $finalMatch = null;
                $score = 0;

                // 1. API Abfrage (OpenFoodFacts)
                $api_url = "https://world.openfoodfacts.org/api/v0/product/" . urlencode($search) . ".json";
                $res = null;
                if (function_exists('curl_version')) {
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $api_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_USERAGENT, 'SaftladenPOS/1.0');
                    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    $res = curl_exec($ch);
                    curl_close($ch);
                } else {
                    $res = @file_get_contents($api_url, false, stream_context_create(["http" => ["timeout" => 3]]));
                }

                if ($res) {
                    $json = json_decode($res, true);
                    if (isset($json['status']) && $json['status'] == 1) {
                        $offData = $json['product'];
                        $brands = $offData['brands'] ?? "";
                        $name = $offData['product_name_de'] ?? $offData['product_name'] ?? "";

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

                // 2. Datenbank-Abfrage
                try {
                    $dsn = "odbc:DSN=JonaTLan;TrustServerCertificate=yes;";
                    $pdo = new PDO($dsn, "sa", "sa04jT14");
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $debugLog[] = "DB: OK";

                    $safeSearch = str_replace("'", "''", $search);

                    // Direktsuche
                    $sqlDirect = "SELECT TOP 1 a.fVKNetto, b.cName FROM dbo.tArtikel a
                                  LEFT JOIN dbo.tArtikelBeschreibung b ON a.kArtikel = b.kArtikel
                                  WHERE a.cArtNr = '$safeSearch' OR a.cBarcode = '$safeSearch'";

                    $stmtDirect = $pdo->prepare($sqlDirect);
                    $stmtDirect->execute();
                    $direct = $stmtDirect->fetch(PDO::FETCH_ASSOC);
                    $stmtDirect->closeCursor();

                    if ($direct) {
                        $finalMatch = ['name' => $direct['cName'], 'preis' => $direct['fVKNetto']];
                        $score = 100;
                        $debugLog[] = "Direkttreffer: JA";
                    } else {
                        $debugLog[] = "Direkttreffer: NEIN";

                        // Fuzzy Match
                        if (!empty($offName)) {
                            $debugLog[] = "Fuzzy gestartet...";
                            $cleanOFF = cleanString($offName);
                            $sqlFuzzy = "SELECT b.cName, a.fVKNetto FROM dbo.tArtikel a JOIN dbo.tArtikelBeschreibung b ON a.kArtikel = b.kArtikel WHERE b.cName IS NOT NULL";

                            $stmtFuzzy = $pdo->prepare($sqlFuzzy);
                            $stmtFuzzy->execute();

                            while ($row = $stmtFuzzy->fetch(PDO::FETCH_ASSOC)) {
                                $cleanJTL = cleanString($row['cName']);
                                if (empty($cleanJTL)) continue;

                                $wordsOFF = preg_split('/[^a-zA-Z0-9]+/', strtolower($offName), -1, PREG_SPLIT_NO_EMPTY);
                                $matchedWords = 0;
                                $targetLower = strtolower($row['cName']);
                                foreach ($wordsOFF as $w) {
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
                                }
                            }
                            $stmtFuzzy->closeCursor();
                            $debugLog[] = "Bester Score: " . round($score, 1) . "%";
                        }
                    }
                } catch (Exception $e) {
                    $debugLog[] = "DB FEHLER: " . $e->getMessage();
                }

                // 3. Anzeige im Cyber Terminal
                ?>
                <div class="terminal-container">
                        <div class="terminal-header">
                            <div class="terminal-controls">
                                <span class="control close"></span>
                                <span class="control minimize"></span>
                                <span class="control maximize"></span>
                            </div>
                            <div class="terminal-title">SCAN_RESULT_V1.1</div>
                            <div class="terminal-status">
                                <span class="status-indicator"></span>
                                <span>ONLINE</span>
                            </div>
                        </div>

                        <div class="terminal-body">
                            <div class="terminal-content" id="terminal-content">
                                <?php if (($finalMatch && $score >= 1) || $offData): ?>
                                    <div class="terminal-line">
                                        <span class="prompt">root@cyber:~$</span>
                                        <span class="command">display_product --id <?php echo htmlspecialchars($search); ?></span>
                                    </div>

                                    <div class="output">
                                        <?php if (!empty($offData['image_url'])): ?>
                                            <div class="product-image-container">
                                                <img src="<?php echo htmlspecialchars($offData['image_url']); ?>" alt="Product">
                                            </div>
                                        <?php endif; ?>

                                        <div class="cyber-product-name"><?php echo htmlspecialchars($finalMatch['name'] ?? $offName ?? 'UNBEKANNT'); ?></div>
                                        <?php if(isset($finalMatch['preis'])): ?>
                                            <div class="cyber-price-tag"><?php echo number_format($finalMatch['preis'] * 1.19, 2, ',', '.'); ?> €</div>
                                        <?php else: ?>
                                            <div class="cyber-price-tag glitch">NO_PRICE</div>
                                        <?php endif; ?>

                                        <?php if($score > 0): ?>
                                        <div class="info-row">
                                            <span class="info">► Match Score: <?php echo round($score, 1); ?>%</span>
                                        </div>
                                        <?php endif; ?>

                                        <div class="off-data-grid">
                                            <div class="off-item"><span class="off-label">Menge:</span><span class="off-value"><?php echo formatValue($offData['quantity'] ?? null); ?></span></div>
                                            <div class="off-item"><span class="off-label">Marken:</span><span class="off-value"><?php echo formatValue($offData['brands'] ?? null); ?></span></div>
                                            <div class="off-item"><span class="off-label">Verpackung:</span><span class="off-value"><?php echo formatValue($offData['packaging'] ?? null); ?></span></div>
                                            <div class="off-item"><span class="off-label">Kategorien:</span><span class="off-value"><?php echo formatValue($offData['categories'] ?? null); ?></span></div>
                                            <div class="off-item"><span class="off-label">Labels:</span><span class="off-value"><?php echo formatValue($offData['labels'] ?? null); ?></span></div>
                                            <div class="off-item"><span class="off-label">Herstellung:</span><span class="off-value"><?php echo formatValue($offData['manufacturing_places'] ?? null); ?></span></div>
                                            <div class="off-item"><span class="off-label">Läden:</span><span class="off-value"><?php echo formatValue($offData['stores'] ?? null); ?></span></div>
                                            <div class="off-item"><span class="off-label">Länder:</span><span class="off-value"><?php echo formatValue($offData['countries'] ?? null); ?></span></div>
                                        </div>

                                        <div class="terminal-line">
                                            <span class="prompt">root@cyber:~$</span>
                                            <span class="command">matrix_view</span>
                                        </div>
                                        <div class="output">
                                            <div class="matrix-display" id="matrix-display"></div>
                                        </div>
                                    </div>

                                <?php else: ?>
                                    <div class="output">
                                        <span class="error">✗ PRODUCT_NOT_FOUND</span>
                                    </div>
                                <?php endif; ?>

                                <div class="terminal-line">
                                    <span class="prompt">root@cyber:~$</span>
                                    <span class="command">show_logs --monitor</span>
                                </div>
                                <div class="output">
                                    <?php foreach ($debugLog as $log): ?>
                                        <div class="success">✓ <?php echo htmlspecialchars($log); ?></div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="terminal-line">
                                    <span class="prompt">root@cyber:~$</span>
                                    <span class="command" id="typing-command"></span>
                                    <span class="cursor">█</span>
                                </div>
                            </div>
                        </div>

                        <div class="terminal-footer">
                            <div class="footer-info">
                                <span>CONNECTION: SECURE</span>
                                <span>UPTIME: <?php echo date('H:i:s'); ?></span>
                                <span>v1.1</span>
                            </div>
                        </div>
                    </div>
                    <?php
            }
            ?>
        </div>
    </div>
    <script src="../js/cyber-terminal.js"></script>
</body>

</html>