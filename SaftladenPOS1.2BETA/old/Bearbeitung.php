<!DOCTYPE html>
<html>
<title>SaftladenSuite</title>
<head>
  <link href="https://fonts.googleapis.com/css2?family=Oswald&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Lato&display=swap" rel="stylesheet">
  <script src="https://kit.fontawesome.com/a076d05399.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Exo+2:ital,wght@1,200&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Oswald&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Lato&display=swap" rel="stylesheet">
  <script src="https://kit.fontawesome.com/a076d05399.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Sansation:wght@700&display=swap" rel="stylesheet">
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
    // mysqli_db_proc.php

    // --- Database configuration ---
    $DB_HOST = "localhost";
    $DB_USER = "noa";      // XAMPP default
    $DB_PASS = "Root123";          // XAMPP default (empty)
    $DB_NAME = "suitehub_main";     // <-- change to your database name

    // --- Enable MySQLi error reporting (DEV ONLY) ---
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    // --- Create connection ---
    $conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

    // --- Check connection ---
    if (!$conn) {
        die("Datenbankverbindung fehlgeschlagen: " . mysqli_connect_error());
    }

    // --- Set charset (VERY important for umlauts, € sign, etc.) ---
    mysqli_set_charset($conn, "utf8mb4");

    error_reporting(E_ALL);
    ini_set('display_errors', 1);


    $search = $_POST['search'] ?? $_GET['search'] ?? '';
    $search = trim($search);

    if ($search) {
        echo "<div class='terminal-output'>";
        echo "<span class='cmd'>[SYSTEM]</span> Scanning Barcode: <span class='val'>" . htmlspecialchars($search) . "</span>";

    // Prepare SQL

    //  Universal result handling (NO mysqlnd required)
                // Single unified SQL for products + category
        $sql = "
            SELECT 
                p.ProductName,
                p.Price,
                p.CategoryID,
                c.CategoryName
            FROM product p
            LEFT JOIN category c ON p.CategoryID = c.CategoryID
            WHERE p.BarcodeNumber = ?
        ";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $search);
        mysqli_stmt_execute($stmt);

        // Bind result columns (no mysqlnd required)
        mysqli_stmt_bind_result(
            $stmt,
            $productName,
            $price,
            $categoryID,
            $categoryName
        );

        if (mysqli_stmt_fetch($stmt)) {
            echo "<div class='product-info'>";
            echo "<div class='info-row'><span class='label'>Produkt:</span> <span class='value'>" . htmlspecialchars($productName) . "</span></div>";
            echo "<div class='info-row'><span class='label'>Preis:</span> <span class='value price'>" . number_format($price, 2) . " €</span></div>";
            echo "<div class='info-row'><span class='label'>Kategorie:</span> <span class='value'>" . htmlspecialchars($categoryName) . "</span></div>";
            echo "</div>";
        } else {
            echo "<div class='error'>[ERROR] Kein Produkt gefunden.</div>";
        }
        echo "</div>"; // end terminal-output
    

        mysqli_stmt_close($stmt);
  }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // Throw exceptions on errors
    try {
        // create a string as Simple query (no parameters) statement
        $sql = "SELECT  BarcodeNumber, ProductName, Price, maincategoryID, subcategoryID, MainCategoryName, SubCategoryName FROM product";
        // apply / execute that query to the DB
        $result = $conn->query($sql);

        // ask for results and if available, loop through them and store it
        // now cerate array which will hold the data; this will be used later to render the table - html
        $myProductArray = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Build array (optional, you can render directly from $row)
                $myProductArray[] = [
                    'BarcodeNumber'   => $row['BarcodeNumber'],
                    'ProductName'     => $row['ProductName'],
                    'Price'           => $row['Price'],
                    'maincategoryID'  => $row['maincategoryID'],
                    'subcategoryID'   => $row['subcategoryID'],
                    'MainCategoryName' => $row['MainCategoryName'],
                    'SubCategoryName' => $row['SubCategoryName']
                ];
            }
        } else {
                echo "0 results";
            // No results; keep array empty
            $myProductArray = [];
        }
        if ($result) {
            $result->free();
        }
        
        // --- OPTION B: Prepared statement version (template for when you add WHERE filters) ---
        // $stmt = $conn->prepare("SELECT ID, BarcodeNumber, ProductName, Price FROM product WHERE Price > ?");
        // $minPrice = 0;
        // $stmt->bind_param("d", $minPrice);
        // $stmt->execute();
        // $rs = $stmt->get_result();
        // $myProductArray = $rs->fetch_all(MYSQLI_ASSOC);
        // $stmt->close();

        $conn->close();
    } catch (mysqli_sql_exception $e) {
      // Avoid leaking DB details to users in production
      http_response_code(500);
      // For debugging only (remove in production):
      // error_log("MySQLi error: " . $e->getMessage());
      exit;
  }
  ?>
      </div> <!-- end results -->

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
                    <td><?php echo htmlspecialchars($product['BarcodeNumber']); ?></td>
                    <td><?php echo htmlspecialchars($product['ProductName']); ?></td>
                    <td><?php echo number_format($product['Price'], 2); ?> €</td>
                    <td><?php echo htmlspecialchars($product['MainCategoryName'] . ' - ' . $product['SubCategoryName']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div> <!-- end content -->

    <input class="menu-icon" type="checkbox" id="menu-icon" name="menu-icon"/>
    <label for="menu-icon" class="menu-label"></label>
    <nav class="nav">     
      <ul class="pt-5">
        <li><a href="Home.html">Home</a></li>
        <li><a href="Bearbeitung.php">Bearbeitung</a></li>
        <li><a href="Eingabe.php">Eingabe</a></li>
        <li><a href="Statistik.html">Statistik</a></li>
        <li><a href="https://www.icpmuenchen.de/">
          <img src="https://iili.io/f8QkeA7.png" alt="ICPLogo" style="width:200px;height:200px;"> </a>
        </li>
      </ul>
    </nav>

  <!-- Scripts -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
  <script src="js/particles.min.js"></script>
  <script src="js/background.js"></script>
  <script src="https://cdn.jsdelivr.net/gh/studio-freight/lenis@1.0.29/bundled/lenis.min.js"></script>
  <script src="js/main.js"></script>
</body>
</html>