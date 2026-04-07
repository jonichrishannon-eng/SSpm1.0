🥤 SaftladenSuite Pro Max
=========================

> **Intelligent middleware for automating kiosk sales with JTL-Wawi integration**

📋 Overview
-----------

In retail environments like the ICP kiosk, manual barcode maintenance is often a bottleneck. **SaftladenSuite Pro Max** solves this problem through intelligent linking of local inventory management and global product databases.

The system scans barcodes, determines the product name via API, and matches it with the JTL database using **fuzzy matching**.

### 🚨 PREREQUISITES 🚨

To implement this system, the following components are strictly required:

*   **Microsoft ODBC Driver**: This is essential for establishing a stable connection between the middleware and the MSSQL JTL-Wawi database.
    
*   **PHP cURL Extension**: Used to perform robust API requests to OpenFoodFacts with defined timeouts, replacing the less reliable file\_get\_contents.
    
*   **PDO (PHP Data Objects)**: The standard interface used to communicate with the database.
    
*   **OpenFoodFacts API Access**: A stable internet connection is required to query the global product database for items not yet mapped in the local system.
    
*   **HTTPS Environment**: If you plan to use the **Barcode Detection API**, a secure context (HTTPS) is mandatory for the browser to allow camera access.
    

### 🛠 System Configuration Requirements

*   **TrustServerCertificate**: The connection string must include TrustServerCertificate=yes to handle SSL handshake requirements during the ODBC connection.
    
*   **Schema Access**: The database user must have permissions to access the dbo schema, specifically the tArtikel and tArtikelBeschreibung tables.
    
*   **UTF-8 Encoding**: The environment must support UTF-8 to correctly display special characters and German umlauts in product names.



⚙️ System Architecture & Workflow
---------------------------------

The system follows a modular logic that can be implemented on any PC with database access, regardless of the programming language:

1.  **Input:** Capture the EAN code (integer) via a scanner.
    
2.  **Web Enrichment:** Query the [OpenFoodFacts API](https://world.openfoodfacts.org/data) to determine the plain product name.
    
3.  **Data Matching:** Compare the API name with dbo.tArtikelBeschreibung via Levenshtein algorithm.
    
4.  **Logic Gate:**
    
    *   **Match > 90%:** Immediate display of the item and gross price.
        
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
    

⚠️ Known Hurdles & Solutions
----------------------------

**ProblemSolutionODBC Timeout**Use prepare() & execute() instead of direct queries.**Memory Limit**Row-by-row fetching (while loop) instead of fetchAll().**API Latency**Switch from file\_get\_contents to **cURL** with a 3s timeout.**SSL Error**Set TrustServerCertificate=yes in the connection string.

🚀 Future Outlook
-----------------
    
*   \[ \] **Live Statistics:** Visualization of daily sales figures directly in the dashboard.

*   \[ \] **Tablet Delivery System (TDS) :** Tablet Based Delivery System to Optimise Productivity.

*   \[ \] **Browser Delivery System (BDS) :** Browser Based Order Delivery System to Optimise Productivity.
  
*   \[ \] **Mobile Support:** Optimization for tablet-based POS solutions.
    

👨‍💻 Developed for
-------------------

**ICP Kiosk / IT Department** _Documentation for replicating the system on other workstations._
