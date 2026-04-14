<!DOCTYPE html>
<html>
<title>SaftladenSuite</title>

<head>
  <link href="https://fonts.googleapis.com/css2?family=Oswald&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Lato&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/common.css">
</head>

<body>
  <a href="Home.html" class="logo"><img src="https://iili.io/f8QkeA7.png" alt="ICPLogo"><span class="logo-text">SaftladenSuite</span></a>
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
      // --- CONNECTION VIA YOUR 'JonaTLan' DSN ---
      try {
          $dsn = "odbc:JonaTLan"; 
          $db_user = "sa";
          $db_pass = "sa04jT14";
          $pdo = new PDO($dsn, $db_user, $db_pass);
          $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      } catch (PDOException $e) {
          echo "<div style='background:red; color:white; padding:10px;'>Verbindungsfehler: " . htmlspecialchars($e->getMessage()) . "</div>";
          $pdo = null;
      }

      $search = trim($_POST['search'] ?? '');

      if ($search && $pdo) {
          // 1. OPENFOODFACTS LOOKUP
          $api_url = "https://world.openfoodfacts.org/api/v0/product/" . urlencode($search) . ".json";
          $offName = "";
          $response = @file_get_contents($api_url);
          
          if ($response) {
              $json = json_decode($response, true);
              if ($json['status'] == 1) {
                  $offName = $json['product']['product_name'] ?? '';
              }
          }

          if ($offName) {
              // 2. FETCH ALL PRODUCTS FROM WAWI FOR FUZZY MATCHING
              $stmt = $pdo->query("SELECT a.cName, a.fVKNetto, a.cBarcode, k.cName AS CategoryName 
                                   FROM tArtikel a
                                   LEFT JOIN tKategorieArtikel ka ON a.kArtikel = ka.kArtikel
                                   LEFT JOIN tKategorie k ON ka.kKategorie = k.kKategorie");
              $allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

              $bestMatch = null;
              $highestScore = 0;

              // 3. FUZZY MATCHING (Threshold 55%)
              foreach ($allProducts as $row) {
                  $lev = levenshtein(strtolower($offName), strtolower($row['cName']));
                  $maxLen = max(strlen($offName), strlen($row['cName']));
                  $score = ($maxLen > 0) ? (1 - $lev / $maxLen) * 100 : 0;

                  if ($score > $highestScore) {
                      $highestScore = $score;
                      $bestMatch = $row;
                  }
              }

              if ($bestMatch && $highestScore >= 55) {
                  $bruttoPrice = $bestMatch['fVKNetto'] * 1.19; // Calculate 119% MwSt
                  
                  echo "<div class='success-box fade-in'>";
                  echo "<h2>Match Score: " . round($highestScore, 1) . "%</h2>";
                  echo "<div class='table-container'><table>";
                  echo "<thead><tr><th>Barcode Input</th><th>Artikelname (JTL)</th><th>Preis (119%)</th><th>Kategorie</th></tr></thead>";
                  echo "<tbody><tr>";
                  echo "<td>" . htmlspecialchars($search) . "</td>";
                  echo "<td>" . htmlspecialchars($bestMatch['cName']) . "</td>";
                  echo "<td>" . number_format($bruttoPrice, 2) . " €</td>";
                  echo "<td>" . htmlspecialchars($bestMatch['CategoryName'] ?? 'N/A') . "</td>";
                  echo "</tr></tbody></table></div>";
                  
                  // Separated Output as requested: Price / Category
                  echo "<p style='text-align:center; font-size:1.5rem; margin-top:10px;'>";
                  echo number_format($bruttoPrice, 2) . " € / " . htmlspecialchars($bestMatch['CategoryName'] ?? 'N/A');
                  echo "</p></div>";
              } else {
                  echo "<div class='info-box'>Kein passendes JTL-Produkt für '" . htmlspecialchars($offName) . "' (Score: " . round($highestScore, 1) . "%).</div>";
              }
          } else {
              echo "<div class='info-box'>Barcode nicht bei OpenFoodFacts gefunden.</div>";
          }
      }
      ?>
    </div>
  </div>
</body>
</html>