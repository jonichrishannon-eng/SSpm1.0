🥤 SaftladenSuite <sup>pro max</sup>
=========================

> **Intelligent middleware for automating kiosk sales with JTL-Wawi integration**

📋 Overview
-----------

In retail environments like the ICP kiosk, manual barcode maintenance is often a bottleneck. **SaftladenSuite Pro Max** solves this problem through intelligent linking of local inventory management and global product databases.

The system scans barcodes, determines the product name via API, and matches it with the JTL database using **fuzzy matching**.

### 🚨 PREREQUISITES 🚨
-----------
To implement this system, the following components are strictly required:
    
*   **PHP cURL Extension**: Used to perform robust API requests to OpenFoodFacts with defined timeouts, replacing the less reliable file\_get\_contents.
    
*   **PDO (PHP Data Objects)**: The standard interface used to communicate with the database.
    
*   **OpenFoodFacts API Access**: A stable internet connection is required to query the global product database for items not yet mapped in the local system.
    
*   **HTTPS Environment**: If you plan to use the **Barcode Detection API**, a secure context (HTTPS) is mandatory for the browser to allow camera access.

*   **JTL-Wawi:** or another Database Manager.
    

### 🛠 System Configuration Requirements
    
*   **Schema Access**: The database user must have permissions to access the dbo schema, specifically the tArtikel and tArtikelBeschreibung tables.

*   **Microsoft ODBC Driver for SQL Server**: This is essential for establishing a stable connection between the middleware and the MSSQL JTL-Wawi database. In addition to the PHP extensions, the physical driver must be installed on the Windows machine hosting XAMPP to allow the pdo\_odbc extension to function. See "ODBC Connection Setup (MSSQL to PHP)" for more instructions.
    
*   **TrustServerCertificate**: Your connection string in JonaTLan.php must include TrustServerCertificate=yes to handle SSL handshake requirements during the ODBC connection, and to bypass SSL handshake issues common in local development environments.

  
⚙️ System Architecture & Workflow
---------------------------------

The system follows a modular logic that can be implemented on any PC with database access, regardless of the programming language:

1.  **Input:** Capture the EAN code (integer) via a scanner.
    
2.  **Web Enrichment:** Query the [OpenFoodFacts API](https://world.openfoodfacts.org/data) to determine the plain product name.
    
3.  **Data Matching:** Compare the API name with dbo.tArtikelBeschreibung via Levenshtein algorithm.
    
4.  **Logic Gate:**
    
    *   **Highest % Match:** Immediate display of the item and gross price.
        
5.  **Persistence:** Write the barcode back to dbo.tArtikel via SQL update.
    

🛠 Implementation Guide (Technical Manual)
------------------------------------------

### 1\. Database Connectivity

Access is handled via the **Microsoft ODBC driver**. For stability, it is crucial to manage the connection in a resource-efficient manner.

**Best Practices:**

*   Use schema prefixes: dbo.tArtikel.
    
*   Cursor Management: Call closeCursor() after each query to avoid blockages in the SQL server.
    

### 2\. Fuzzy Matching Logic

Since names in JTL and on the web are rarely identical, normalization is performed:

*   Removal of special characters and spaces.
    
*   Case-insensitivity (lowercase).
    
*   Similarity calculation (e.g., similar\_text or Levenshtein).
    

⚠️ Known Hurdles, Limitations & Solutions
----------------------------
Nothing here! (yet.)

🚀 Future Outlook
-----------------
    
*   \[ \] **Live Statistics:** Visualization of daily sales figures directly in the dashboard.

*   \[ \] **Tablet Delivery System (TDS) :** Tablet Based Delivery System to Optimise Productivity.

*   \[ \] **Browser Delivery System (BDS) :** Browser Based Order Delivery System to Optimise Productivity.
  
*   \[ \] **Mobile Support:** Optimization for tablet-based POS solutions.
    
To ensure the **SaftladenSuite Pro Max** operates correctly within a XAMPP environment, specific PHP extensions must be enabled in your php.ini configuration.

### 🚨 PHP EXTENSIONS FOR XAMPP 🚨
----------------------------------
The following extensions are mandatory for the system's database connectivity and API communication:

*   **php\_pdo\_odbc**: This is the primary extension required for the middleware to communicate with the MSSQL JTL-Wawi database using the Microsoft ODBC Driver.
    
*   **php\_curl**: This extension is required to perform robust, high-performance API requests to OpenFoodFacts. It replaces the default file\_get\_contents to allow for better timeout handling and error management.
    
*   **php\_mbstring**: Necessary for handling multi-byte strings, ensuring that product names with special characters or German umlauts are processed correctly.
    
*   **php\_openssl**: Required to establish secure connections (HTTPS) when querying the external OpenFoodFacts API.
    

### 🛠 How to Enable Extensions in XAMPP

1.  Open the **XAMPP Control Panel**.
    
2.  Click the **Config** button next to the Apache module and select **PHP (php.ini)**.
    
3.  Search for the following lines (use Ctrl + F):
    
    *   ;extension=pdo\_odbc
        
    *   ;extension=curl
        
    *   ;extension=mbstring
        
    *   ;extension=openssl
        
4.  Remove the semicolon (;) from the beginning of each line to uncomment and enable them.
    
5.  **Save** the file and **Restart** the Apache module in the XAMPP Control Panel for the changes to take effect.


🖥️ ODBC Connection Setup (MSSQL to PHP)
----------------------------------------

To allow the **SaftladenSuite Pro Max** to communicate with your JTL-Wawi database, you must configure a Data Source Name (DSN) on the host machine.

### 1\. Install the Driver

Before configuring the connection, ensure the official driver is installed:

*   **Download:** [Microsoft ODBC Driver for SQL Server](https://learn.microsoft.com/en-us/sql/connect/odbc/download-odbc-driver-for-sql-server)

*   Version 13 was tested and works; but newer versions should work aswell
    
*   **Recommended Version:** ODBC Driver 17 or 18.
    

### 2\. Manual Configuration (Windows ODBC Manager)

Follow these steps to create the JonaTLan DSN:

1.  Press Win + R, type **odbcad32.exe**, and press Enter.
    
2.  Select the **System DSN** tab (to make it available for all users/services).
    
3.  Click **Add** and select **ODBC Driver 17 for SQL Server**.
    
4.  **Name:** Enter JonaTLan (this must match the name used in your PHP scripts).
    
5.  **Server:** Enter your SQL Server instance name (e.g., localhost\\JTLWAWI).
    
6.  **Authentication:** Select "With SQL Server authentication" and enter your credentials (e.g., User: sa).
    
7.  **Database:** Change the default database to your JTL database (e.g., eazybusiness).
    
8.  **Encryption:** - Check **Trust Server Certificate**.
    
    *   (If using Driver 18) Set "Encrypted" to **Optional**.
        
9.  Click **Test Data Source** to verify the connection.
    

### 3\. Backup/Export the Connection

Since ODBC connections are stored in the Windows Registry, you can export your setup for deployment on other machines:

*   **Registry Path:** HKEY\_LOCAL\_MACHINE\\SOFTWARE\\ODBC\\ODBC.INI\\YOUR_DATABASE
    
*   **Command:** Right-click the folder in regedit and select **Export** to create a .reg file.
    

💻 Technical Connection String
------------------------------

Your PHP code uses the following DSN string to initialize the connection. Note the inclusion of TrustServerCertificate to bypass local SSL handshake issues.
```
// Example DSN Configuration in JonaTLan.php; Edit as needed:
$dsn = "odbc:DSN=JonaTLan;TrustServerCertificate=yes;";
$user = "sa";
$password = "sa04jT14";

try {
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
```

### 🚨 Critical Performance Fixes

To prevent the "Driver does not support that attribute" error or SQL hangs during fuzzy searches, the system implements:

*   **Cursor Management:** Use $stmt->closeCursor() after every query to free the connection for the next request.
    
*   **Row-by-Row Fetching:** Avoid fetchAll() on large product tables to prevent memory exhaustion in the ODBC driver.



👨‍💻 Developed for
-------------------

**ICP Kiosk / IT Department** _Documentation for replicating the system on other workstations._
