# 🥤 SaftladenSuite Pro Max
> **Intelligente Middleware zur Automatisierung des Kiosk-Verkaufs mit JTL-WAWI Anbindung**

![Version](https://img.shields.io/badge/Version-1.0.0-blue)
![Build](https://img.shields.io/badge/Status-Funktional-success)
![Database](https://img.shields.io/badge/DB-JTL--WAWI%20(MSSQL)-orange)

## 📋 Übersicht
In Handelsumgebungen wie dem ICP-Kiosk ist die manuelle Pflege von Barcodes oft ein Flaschenhals. Die **SaftladenSuite Pro Max** löst dieses Problem durch eine intelligente Verknüpfung von lokaler Bestandsführung und globalen Produktdatenbanken.

Das System scannt Barcodes, ermittelt via API die Produktbezeichnung und gleicht diese mittels **Fuzzy-Matching** mit der JTL-Datenbank ab. Ein integrierter **Learning-Mode** erlaubt es, neue Barcodes mit nur einem Klick permanent in die Warenwirtschaft zu übernehmen.

---

## ⚙️ Systemarchitektur & Workflow

Das System folgt einer modularen Logik, die unabhängig von der Programmiersprache an jedem PC mit Datenbankzugriff implementiert werden kann:



1. **Input:** Erfassung des EAN-Codes (Ganzzahl) über einen Scanner.
2. **Web-Enrichment:** Abfrage der [OpenFoodFacts API](https://world.openfoodfacts.org/data) zur Ermittlung des Klarnamens.
3. **Data-Matching:** Vergleich des API-Namens mit `dbo.tArtikelBeschreibung` via Levenshtein-Algorithmus.
4. **Logic-Gate:**
   - **Match > 90%:** Sofortige Anzeige von Artikel und Brutto-Preis.
   - **Match < 90%:** Aktivierung des **Learning-Mode** (Vorschlag zur Verknüpfung).
5. **Persistence:** Rückschreiben des Barcodes in `dbo.tArtikel` per SQL-Update.

---

## 🛠 Implementierungs-Guide (Technischer Leitfaden)

### 1. Datenbank-Konnektivität
Der Zugriff erfolgt über den **Microsoft ODBC-Treiber**. Für die Stabilität ist es entscheidend, die Verbindung ressourcenschonend zu verwalten.

**Best Practice:**
- Schema-Präfixe nutzen: `dbo.tArtikel`.
- Cursor-Management: Nach jeder Abfrage `closeCursor()` aufrufen, um Blockaden im SQL-Server zu vermeiden.

### 2. Fuzzy-Matching Logik
Da Namen in JTL und im Web selten identisch sind, wird eine Normalisierung durchgeführt:
- Entfernung von Sonderzeichen & Leerzeichen.
- Case-Insensitivity (Kleinschreibung).
- Berechnung der Ähnlichkeit (z.B. `similar_text` oder Levenshtein).

### 3. Der Learning-Mode (Automatisierung)
Falls ein Barcode unbekannt ist, bietet das UI eine "Lern-Taste" an. Diese führt folgenden Prozess aus:
```sql
-- Identifikation des Artikels und Verknüpfung
UPDATE dbo.tArtikel 
SET cBarcode = 'SCAN_WERT' 
WHERE kArtikel = (SELECT kArtikel FROM dbo.tArtikelBeschreibung WHERE cName = 'VORSCHLAG');
