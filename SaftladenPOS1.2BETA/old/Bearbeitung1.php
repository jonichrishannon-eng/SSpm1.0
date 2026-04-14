<!DOCTYPE html>
<html>
<title>SaftladenSuite</title>

<head>
  <link href="https://fonts.googleapis.com/css2?family=Oswald&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Lato&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/common.css">
</head>

<body>
  <a href="Home.html" class="logo"><img src="https://iili.io/f8QkeA7.png" alt="ICPLogo"><span
      class="logo-text">SaftladenSuite</span></a>
  <div class="content">
    <h1 class="title aurora-text">SaftladenSuite <sup>pro max</sup></h1>

    <div class="barcode-container">
      <form action="#" method="post" class="barcode-form">
        <label for="search">Bitte hier Barcode eingeben</label>
        <div class="barcode-input-wrapper">
          <input type="text" id="search" name="search" placeholder="0000000000000" autofocus>
          <div class="input-glow"></div>
        </div>
        <button type="submit" class="barcode-btn">Suchen</button>
      </form>
    </div>

    <div class="results">
      <?php
      // Use the dot (.) to represent the local machine and double backslash for the instance
      $DB_HOST = ".\\JTLWAWI";
      $DB_NAME = "eazybusiness";
      $DB_USER = "sa";
      $DB_PASS = "sa04jT14"; // Try this default, or your custom one
      
      try {
        // We explicitly tell PHP to use ODBC Driver 13
        $dsn = "sqlsrv:Server=$DB_HOST;Database=$DB_NAME;Driver={ODBC Driver 13 for SQL Server}";
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
      } catch (PDOException $e) {
        // This will now show the REAL error message so we can stop guessing
        echo "<div class='error' style='background:red; color:white; padding:10px;'>";
        echo "DEBUG ERROR: " . $e->getMessage();
        echo "</div>";
        $pdo = null;
      }

      $search = $_POST['search'] ?? $_GET['search'] ?? '';
      $search = trim($search);

      if ($search && $pdo) {
        echo "<div class='terminal-output'>";
        echo "<span class='cmd'>[SYSTEM]</span> Analyzing EAN: <span class='val'>" . htmlspecialchars($search) . "</span><br>";

        // 1. OPENFOODFACTS LOOKUP
        $api_url = "https://world.openfoodfacts.org/api/v0/product/" . urlencode($search) . ".json";
        $offName = "";
        $offData = null;

        $response = @file_get_contents($api_url);
        if ($response) {
          $json = json_decode($response, true);
          if ($json['status'] == 1) {
            $offData = $json['product'];
            $offName = $offData['product_name'] ?? '';
            echo "<span class='cmd'>[OFF]</span> Name gefunden: <span class='val'>" . htmlspecialchars($offName) . "</span><br>";
          }
        }

        if ($offName) {
          // 2. TOKENIZATION (Split by spaces, dashes, underscores, tabs)
          $words = preg_split('/[\s\-_,\t]+/', $offName, -1, PREG_SPLIT_NO_EMPTY);
          $words = array_filter($words, function ($w) {
            return strlen($w) > 2;
          }); // Keep words > 2 chars
      
          if (!empty($words)) {
            // 3. SEARCH WAWI FOR MATCHING TOKENS
            $queryParts = [];
            $params = [];
            foreach (array_values($words) as $index => $word) {
              $queryParts[] = "a.cName LIKE :word$index";
              $params["word$index"] = "%$word%";
            }

            $sql = "SELECT a.cName, a.fVKNetto, a.cArtNr, k.cName AS CategoryName 
                        FROM tArtikel a
                        LEFT JOIN tKategorieArtikel ka ON a.kArtikel = ka.kArtikel
                        LEFT JOIN tKategorie k ON ka.kKategorie = k.kKategorie
                        WHERE " . implode(" OR ", $queryParts);

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $potentialMatches = $stmt->fetchAll();

            // 4. FUZZY MATCHING (55% Threshold)
            $bestMatch = null;
            $highestScore = 0;

            foreach ($potentialMatches as $row) {
              $lev = levenshtein(strtolower($offName), strtolower($row['cName']));
              $maxLen = max(strlen($offName), strlen($row['cName']));
              $score = ($maxLen > 0) ? (1 - $lev / $maxLen) * 100 : 0;

              if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatch = $row;
              }
            }

            if ($bestMatch && $highestScore >= 55) {
              echo "<span class='cmd'>[MATCH FOUND!]</span> Confidence: " . round($highestScore, 1) . "%<br>";
              echo "<div class='product-info'>";
              echo "<div class='info-row'><span class='label'>Wawi Name:</span> <span class='value'>" . htmlspecialchars($bestMatch['cName']) . "</span></div>";
              echo "<div class='info-row'><span class='label'>Preis:</span> <span class='value price'>" . number_format($bestMatch['fVKNetto'], 2) . " €</span></div>";
              echo "<div class='info-row'><span class='label'>Marke (OFF):</span> <span class='value'>" . htmlspecialchars($offData['brands'] ?? 'N/A') . "</span></div>";
              if (isset($offData['image_small_url'])) {
                echo "<img src='{$offData['image_small_url']}' style='margin-top:10px; border-radius:8px; border:1px solid #444;'>";
              }
              echo "</div>";
            } else {
              echo "<div class='error'>[FAIL] Kein passendes Produkt in Wawi gefunden (Beste Übereinstimmung: " . round($highestScore, 1) . "%).</div>";
            }
          }
        } else {
          echo "<div class='error'>[ERROR] Barcode nicht bei OpenFoodFacts gelistet.</div>";
        }
        echo "</div>";
      }

      // --- FETCH PRODUCT LIST (For the table below) ---
      $myProductArray = [];
      if ($pdo) {
        try {
          $sqlList = "SELECT TOP 50 a.cBarcode, a.cName, a.fVKNetto, k.cName as CategoryName 
                        FROM tArtikel a
                        LEFT JOIN tKategorieArtikel ka ON a.kArtikel = ka.kArtikel
                        LEFT JOIN tKategorie k ON ka.kKategorie = k.kKategorie";
          $myProductArray = $pdo->query($sqlList)->fetchAll();
        } catch (Exception $e) {
          $myProductArray = [];
        }
      }
      ?>
    </div>
    <div class="product-list fade-in-up">
      <?php if (!empty($myProductArray)): ?>
        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>Barcode</th>
                <th>Produkt</th>
                <th>Preis</th>
                <th>Kategorie</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($myProductArray as $product): ?>
                <tr>
                  <td><?php echo htmlspecialchars($product['cBarcode'] ?? 'N/A'); ?></td>
                  <td><?php echo htmlspecialchars($product['cName']); ?></td>
                  <td><?php echo number_format($product['fVKNetto'], 2); ?> €</td>
                  <td><?php echo htmlspecialchars($product['CategoryName'] ?? 'Unkategorisiert'); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>

</html>