<!DOCTYPE html> <!-- Defines the document type as HTML5 -->
<html lang="de"> <!-- Sets the document language to German -->

<head> <!-- Start of the head section (metadata, title, scripts) -->
    <meta charset="UTF-8"> <!-- Sets the character encoding to UTF-8 for special characters -->
    <title>SaftladenSuite POS v1.1</title> <!-- Title shown in the browser tab -->
    <!-- Including Google Fonts (Oswald and Lato) for the design -->
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;700&family=Lato&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/common.css"> <!-- Including general styles -->
    <link rel="stylesheet" href="../css/cyber-terminal.css"> <!-- Including the special "Cyber Terminal" design -->
</head> <!-- End of the head section -->

<body class="<?php echo ($isRedTheme ?? false) ? 'red-alert-theme' : ''; ?>"> <!-- Start of the visible area -->
    <div class="background-effects"> <!-- Container for visual background effects -->
        <div class="grid-overlay"></div> <!-- Creates a grid overlay (Cyber look) -->
        <div class="scan-line"></div> <!-- Animated scan line that runs across the screen -->
        <div class="floating-code"></div> <!-- Placeholder for floating code elements in the background -->
    </div> <!-- End of background effects -->

    <a href="Home.html" class="logo"> <!-- Link to dashboard/home, formatted as a logo -->
        <img src="https://iili.io/f8QkeA7.png" alt="Logo"> <!-- The logo image itself -->
        <span class="logo-text">SaftladenSuite</span> <!-- Text next to the logo -->
    </a> <!-- End of the logo link -->

    <div class="content"> <!-- Main container for the actual content -->
        <h1 class="title aurora-text">SaftladenSuite <sup>pro max</sup></h1> <!-- Main heading with effect class -->

        <div class="barcode-container"> <!-- Container for the barcode input field -->
            <form action="#" method="post" class="barcode-form"> <!-- Form sends data via POST to itself -->
                <label for="search">Barcode scannen</label> <!-- Label for the input field -->
                <div class="barcode-input-wrapper"> <!-- Design wrapper for the input field -->
                    <input type="text" id="search" name="search" placeholder="Hier scannen..." autofocus
                        autocomplete="off"> <!-- The actual text field, autofocused -->
                    <div class="input-glow"></div> <!-- Visual glow effect for the input field -->
                </div> <!-- End of input wrapper -->
                <button type="submit" class="barcode-btn">Suchen</button> <!-- Button to submit the form -->
            </form> <!-- End of the form -->
        </div> <!-- End of barcode container -->

        <div class="results"> <!-- Area where search results are displayed -->
            <?php // Start of PHP block
            // Function to clean strings (removes special characters, converts to lowercase)
            function cleanString($str)
            {
                if (empty($str)) // If the string is empty...
                    return ""; // ...return an empty string.
                return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $str)); // Remove everything except letters and numbers.
            }

            // Function to format values for display (handles empty data)
            function formatValue($val)
            {
                if (empty($val)) { // If the value is empty...
                    return '<span class="glitch">NO_DATA</span>'; // ...return a formatted error message.
                }
                return htmlspecialchars($val); // Convert HTML special characters (security against XSS).
            }

            // Helper to extract numbers from a string for quantity comparison
            function getNumbers($str)
            {
                if (!$str)
                    return [];
                $str = str_replace(',', '.', $str);
                preg_match_all('/[0-9]+([.][0-9]+)?/', $str, $matches);
                return array_map('floatval', $matches[0]);
            }

            // Check if there is a discrepancy between JTL name and OFF quantity
            function hasQuantityMismatch($jtlName, $offQuantity)
            {
                if (!$offQuantity || !$jtlName)
                    return false;
                $jtlNums = getNumbers($jtlName);
                $offNums = getNumbers($offQuantity);
                if (empty($jtlNums) || empty($offNums))
                    return false;
                foreach ($offNums as $on) {
                    foreach ($jtlNums as $jn) {
                        if (abs($on - $jn) < 0.001)
                            return false;
                        if (abs($on - ($jn * 1000)) < 0.01)
                            return false; // e.g. 0.5l -> 500ml
                        if (abs(($on * 1000) - $jn) < 0.01)
                            return false; // e.g. 500ml -> 0.5l
                    }
                }
                return true;
            }


            // Check if the search field in the POST array is not empty
            if (!empty(trim($_POST['search'] ?? ''))) {
                $search = trim($_POST['search']); // Remove leading/trailing spaces from the search term.
                $debugLog = ["Scan: " . htmlspecialchars($search)]; // Start a log array for debugging.
                $offData = null; // Initialize variable for OpenFoodFacts data.
                $offName = ""; // Initialize variable for the name from the API.
                $finalMatch = null; // Initialize variable for the found product object.
                $score = 0; // Initialize variable for the match score.
            
                // 1. API Query (OpenFoodFacts) - Load product information from the internet
                $api_url = "https://world.openfoodfacts.org/api/v0/product/" . urlencode($search) . ".json"; // Create the API URL.
                $res = null; // Variable for the API response.
                if (function_exists('curl_version')) { // If the cURL extension is available on the server...
                    $ch = curl_init(); // Initialize a cURL session.
                    curl_setopt($ch, CURLOPT_URL, $api_url); // Set the URL.
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // The response should be returned as a string.
                    curl_setopt($ch, CURLOPT_USERAGENT, 'SaftladenPOS/1.0'); // Set a User-Agent header.
                    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Wait at most 3 seconds for a response.
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ignore SSL certificate errors (practical for local development).
                    $res = curl_exec($ch); // Execute the request.
                    curl_close($ch); // Close the cURL session.
                } else { // If cURL is not available, use file_get_contents.
                    $res = @file_get_contents($api_url, false, stream_context_create(["http" => ["timeout" => 3]])); // Load the URL with timeout.
                }

                if ($res) { // If a response was received from the API...
                    $json = json_decode($res, true); // Decode the JSON string into a PHP array.
                    if (isset($json['status']) && $json['status'] == 1) { // Status 1 means: Product was found.
                        $offData = $json['product']; // Save the product data.
                        $brands = $offData['brands'] ?? ""; // Get the brand name, if available.
                        $name = $offData['product_name_de'] ?? $offData['product_name'] ?? ""; // Look for German name, otherwise default name.
            
                        // Logic for joining brand and name
                        if (!empty($brands) && stripos($name, $brands) === false) { // If brand is not already in the name...
                            if (strpos($brands, ',') !== false) { // If multiple brands exist...
                                $brands = explode(',', $brands)[0]; // ...take only the first brand.
                            }
                            $offName = trim($brands . " " . $name); // Combine brand and product name.
                        } else {
                            $offName = trim($name); // Use only the product name.
                        }
                    }
                }
                $debugLog[] = "API: " . ($offName ?: "No name found on web"); // Log the result of the API query.
            
                // 2. Database Query (Local JTL-Wawi Database)
                try {
                    $dsn = "odbc:DSN=JonaTLan;TrustServerCertificate=yes;"; // Define the ODBC connection data.
                    $pdo = new PDO($dsn, "sa", "sa04jT14"); // Create the database connection with user 'sa'.
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Enable error messages for DB problems.
                    $debugLog[] = "DB: OK"; // Log successful connection.
            
                    $safeSearch = str_replace("'", "''", $search); // Clean search term for SQL (protection against SQL injection).
            
                    // Direct Search: Check if barcode or item number match exactly
                    $sqlDirect = "SELECT TOP 1 a.fVKNetto, b.cName FROM dbo.tArtikel a
                                  LEFT JOIN dbo.tArtikelBeschreibung b ON a.kArtikel = b.kArtikel
                                  WHERE a.cArtNr = '$safeSearch' OR a.cBarcode = '$safeSearch'";

                    $stmtDirect = $pdo->prepare($sqlDirect); // Prepare the SQL query.
                    $stmtDirect->execute(); // Execute the query.
                    $direct = $stmtDirect->fetch(PDO::FETCH_ASSOC); // Get the result.
                    $stmtDirect->closeCursor(); // End the query.
            
                    if ($direct) { // If a direct hit was found...
                        $finalMatch = ['name' => $direct['cName'], 'preis' => $direct['fVKNetto']]; // Save name and price.
                        $score = 100; // Set match score to 100%.
                        $debugLog[] = "Direct hit: YES"; // Log direct hit.
                    } else { // If no direct hit was found...
                        $debugLog[] = "Direct hit: NO"; // Log failure of direct search.
            
                        // Fuzzy Match: Search for similar names if the API provided a name
                        if (!empty($offName)) {
                            $debugLog[] = "Fuzzy started..."; // Log start of similarity search.
                            $cleanOFF = cleanString($offName); // Clean the name from the API.
                            // Get all item descriptions from the database
                            $sqlFuzzy = "SELECT b.cName, a.fVKNetto FROM dbo.tArtikel a JOIN dbo.tArtikelBeschreibung b ON a.kArtikel = b.kArtikel WHERE b.cName IS NOT NULL";

                            $stmtFuzzy = $pdo->prepare($sqlFuzzy); // Prepare the fuzzy query.
                            $stmtFuzzy->execute(); // Execute it.
            
                            while ($row = $stmtFuzzy->fetch(PDO::FETCH_ASSOC)) { // Go through all products in the database...
                                $cleanJTL = cleanString($row['cName']); // Clean the DB name for comparison.
                                if (empty($cleanJTL)) // If name is empty, skip.
                                    continue;

                                // Word-based matching
                                $wordsOFF = preg_split('/[^a-zA-Z0-9]+/', strtolower($offName), -1, PREG_SPLIT_NO_EMPTY); // Split API name into words.
                                $matchedWords = 0; // Counter for found words.
                                $targetLower = strtolower($row['cName']); // Convert DB name to lowercase.
                                foreach ($wordsOFF as $w) { // Search each word...
                                    if (strpos($targetLower, $w) !== false) { // If word occurs in DB name...
                                        $matchedWords++; // Increase counter.
                                    }
                                }
                                // Calculate word score in percent
                                $wordScore = (count($wordsOFF) > 0) ? ($matchedWords / count($wordsOFF)) * 100 : 0;

                                // Levenshtein distance (character-based matching)
                                $lev = levenshtein($cleanOFF, $cleanJTL); // Calculate edit distance between both names.
                                $maxLen = max(strlen($cleanOFF), strlen($cleanJTL)); // Determine the length of the longer string.
                                $levScore = ($maxLen > 0) ? (1 - $lev / $maxLen) * 100 : 0; // Calculate Levenshtein score in percent.
            
                                // Weighted total score (78% word score, 22% Levenshtein)
                                $currentScore = ($wordScore * 0.78) + ($levScore * 0.22);

                                if ($currentScore > $score) { // If this article fits better than the previous best...
                                    $score = $currentScore; // Update the best score.
                                    $finalMatch = ['name' => $row['cName'], 'preis' => $row['fVKNetto']]; // Save this article.
                                }
                            }
                            $stmtFuzzy->closeCursor(); // End the fuzzy search.
                            $debugLog[] = "Best Score: " . round($score, 1) . "%"; // Log the best found score.
                        }
                    }
                } catch (Exception $e) { // If an error occurs in the database connection...
                    $debugLog[] = "DB ERROR: " . $e->getMessage(); // Log the error message.
                }

                // 3. Determine Theme (Red Alert if Score < 45% or Quantity mismatch)
                $qMismatch = ($finalMatch && isset($offData['quantity'])) ? hasQuantityMismatch($finalMatch['name'], $offData['quantity']) : false;
                $isRedTheme = ($score < 45 || $qMismatch);
                if ($qMismatch)
                    $debugLog[] = "WARNING: Quantity Mismatch detected!";
                if ($isRedTheme)
                    $debugLog[] = "ALERT: ITEM_NOT_IN_DATABASE mode active.";

                // 4. Display in Cyber Terminal
                ?> <!-- End of PHP logic part -->
                <div class="terminal-container <?php echo $isRedTheme ? 'red-alert' : ''; ?>"> <!-- Outer container -->

                    <div class="terminal-header"> <!-- Top edge of the terminal -->
                        <div class="terminal-controls">
                            <!-- The typical window buttons (close, minimize, maximize) -->
                            <span class="control close"></span> <!-- Red dot -->
                            <span class="control minimize"></span> <!-- Yellow dot -->
                            <span class="control maximize"></span> <!-- Green dot -->
                        </div> <!-- End controls -->
                        <div class="terminal-title">SCAN_RESULT_V1.1</div> <!-- Title of the terminal window -->
                        <div class="terminal-status"> <!-- Status display (online indicator) -->
                            <span class="status-indicator"></span> <!-- Small green dot -->
                            <span>ONLINE</span> <!-- Text "ONLINE" -->
                        </div> <!-- End status -->
                    </div> <!-- End header -->

                    <div class="terminal-body"> <!-- Main content of the terminal -->
                        <div class="terminal-content" id="terminal-content">
                            <!-- Area for textual commands and outputs -->
                            <!-- Condition: Show product if (hit in DB and score >= 35%) OR online data available -->
                            <?php if (($finalMatch && $score >= 35) || $offData): ?>
                                <div class="terminal-line1"> <!-- Simulates a command line -->
                                    <span class="prompt">root@Saftladen:~$</span> <!-- The prompt (user@machine) -->
                                    <span class="command">display_product --id <?php echo htmlspecialchars($search); ?></span>
                                    <!-- The fictitious command -->
                                </div> <!-- End terminal line -->

                                <div class="output"> <!-- Container for the result output -->
                                    <?php if ($isRedTheme): ?>
                                        <div class="item-not-in-db-banner">
                                            ITEM NOT IN JTL DATABASE
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($offData['image_url'])): ?> <!-- If an image was found online... -->
                                        <div class="product-image-container"> <!-- Image layout container -->
                                            <img src="<?php echo htmlspecialchars($offData['image_url']); ?>" alt="Product">
                                            <!-- The product image -->
                                        </div> <!-- End image container -->
                                    <?php endif; ?>

                                    <div class="cyber-product-name">
                                        <?php
                                        // If red theme, force OpenFoodFacts name. Otherwise use the best match.
                                        echo htmlspecialchars($isRedTheme ? ($offName ?: 'UNKNOWN') : ($finalMatch['name'] ?? $offName ?? 'UNKNOWN'));
                                        ?>
                                    </div> <!-- End name -->

                                    <?php if (!$isRedTheme && isset($finalMatch['preis'])): ?>
                                        <!-- If NOT red and price found in JTL -->
                                        <div class="cyber-price-tag">
                                            <?php echo number_format($finalMatch['preis'] * 1.19, 2, ',', '.'); ?> €
                                        </div> <!-- End price -->
                                    <?php else: ?>
                                        <div class="cyber-price-tag glitch">NO_PRICE</div>
                                    <?php endif; ?>

                                    <?php if (!$isRedTheme && $score > 0): ?> <!-- Only show score if in Green mode -->
                                        <div class="info-row"> <!-- Info line -->
                                            <span class="info">► Match Score: <?php echo round($score, 1); ?>%</span>
                                        </div> <!-- End info line -->
                                    <?php endif; ?>


                                    <!-- Grid for additional product data from OpenFoodFacts -->
                                    <div class="off-data-grid">
                                        <!-- Output of various attributes like quantity, brands, packaging etc. -->
                                        <div class="off-item"><span class="off-label">Menge:</span><span
                                                class="off-value"><?php echo formatValue($offData['quantity'] ?? null); ?></span>
                                        </div>
                                        <div class="off-item"><span class="off-label">Marken:</span><span
                                                class="off-value"><?php echo formatValue($offData['brands'] ?? null); ?></span>
                                        </div>
                                        <div class="off-item"><span class="off-label">Verpackung:</span><span
                                                class="off-value"><?php echo formatValue($offData['packaging'] ?? null); ?></span>
                                        </div>
                                        <div class="off-item"><span class="off-label">Kategorien:</span><span
                                                class="off-value"><?php echo formatValue($offData['categories'] ?? null); ?></span>
                                        </div>
                                        <div class="off-item"><span class="off-label">Labels:</span><span
                                                class="off-value"><?php echo formatValue($offData['labels'] ?? null); ?></span>
                                        </div>
                                        <div class="off-item"><span class="off-label">Herstellung:</span><span
                                                class="off-value"><?php echo formatValue($offData['manufacturing_places'] ?? null); ?></span>
                                        </div>
                                        <div class="off-item"><span class="off-label">Läden:</span><span
                                                class="off-value"><?php echo formatValue($offData['stores'] ?? null); ?></span>
                                        </div>
                                        <div class="off-item"><span class="off-label">Länder:</span><span
                                                class="off-value"><?php echo formatValue($offData['countries'] ?? null); ?></span>
                                        </div>
                                    </div> <!-- End data grid -->

                                    <div class="terminal-line"> <!-- Next fictitious command for the matrix effect -->
                                        <span class="prompt">root@Saftladen:~$</span> <!-- Prompt -->
                                        <span class="command">matrix_view</span> <!-- Command "matrix_view" -->
                                    </div> <!-- End line -->
                                    <div class="output"> <!-- Container for the matrix animation -->
                                        <div class="matrix-display" id="matrix-display"></div>
                                        <!-- JavaScript will write code here -->
                                    </div> <!-- End matrix output -->
                                </div> <!-- End total output -->

                            <?php else: ?>
                                <!-- If no product was found in DB (with score >= 25) and not online -->
                                <div class="output"> <!-- Error output -->
                                    <span class="error">✗ PRODUCT_NOT_FOUND</span> <!-- Display "Product not found" -->
                                </div> <!-- End error -->
                            <?php endif; ?>

                            <div class="terminal-line"> <!-- Simulates command to display debug logs -->
                                <span class="prompt">root@Saftladen:~$</span> <!-- Prompt -->
                                <span class="command">show_logs --monitor</span> <!-- Command "show_logs" -->
                            </div> <!-- End line -->
                            <div class="output"> <!-- Area for the log entries -->
                                <?php foreach ($debugLog as $log): ?> <!-- Go through all collected log entries... -->
                                    <div class="success">✓ <?php echo htmlspecialchars($log); ?></div>
                                    <!-- Show log line with a checkmark -->
                                <?php endforeach; ?> <!-- End log loop -->
                            </div> <!-- End log output -->

                            <div class="terminal-line"> <!-- Last (empty) terminal line with blinking cursor -->
                                <span class="prompt">root@Saftladen:~$</span> <!-- Prompt -->
                                <span class="command" id="typing-command"></span>
                                <!-- Area for typing animation (JS) -->
                                <span class="cursor">█</span> <!-- The cursor symbol -->
                            </div> <!-- End line -->
                        </div> <!-- End terminal interior -->
                    </div> <!-- End terminal body -->

                    <div class="terminal-footer"> <!-- Bottom edge of the terminal -->
                        <div class="footer-info"> <!-- Additional info (encryption, time, version) -->
                            <span>CONNECTION: SECURE</span> <!-- Text status -->
                            <span>UPTIME: <?php echo date('H:i:s'); ?></span> <!-- Current time from the server -->
                            <span>v1.1</span> <!-- Version number -->
                        </div> <!-- End footer info -->
                    </div> <!-- End footer -->
                </div> <!-- End terminal container -->
            <?php // Completion of the PHP process
            } // End of the 'if search not empty' condition
            ?>
        </div> <!-- End of results div -->
    </div> <!-- End of content div -->
    <script src="../js/cyber-terminal.js"></script> <!-- Inclusion of JavaScript for interactions -->
</body> <!-- End of the body section -->

</html> <!-- End of the HTML document -->