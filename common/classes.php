<?php if (count(get_included_files()) == 1) die('This file is not meant to be accessed directly.');


class mySQlite3 extends SQLite3
{
    public function __construct(string $filename)
    {
        parent::__construct($filename);

        // try to load ICU extension, if not available, use (slower) PHP functions
        // for compatibility with mysql, call collation utf8_lithuanian_ci
        // @ suppresses error (method returns false)
        if (!empty(SQLITE_ICU_EXT) && @$this->loadExtension(SQLITE_ICU_EXT)) {
            $this->exec("SELECT icu_load_collation('lt', 'utf8_lithuanian_ci')");
        } else {
            $this->createCollation('utf8_lithuanian_ci', function ($a, $b) {
                return strcoll($a, $b);
            });

            // normalization function, replacing default lower
            $this->createFunction('LOWER', function ($str) {
                return mb_strtolower($str);
            });

            // my own like function
            $this->createFunction('REGEXP', function ($pattern, $str) {
                // Remove trailing dots from the pattern and escape special characters
                $pattern = rtrim($pattern, '.,;');
                $pattern = preg_quote(
                    $pattern,
                    '/'
                );

                // Match whole words only
                return preg_match("/\b$pattern\b/u", $str);
            }, 2);
        }

        // set timeout for database operations
        $this->exec('PRAGMA busy_timeout = 2000;');
    }
    /**
     * database helper functions
     */

    public function getSetting(string $param_name, $full = false): mixed
    {
        // Prepare the SELECT statement with placeholder
        $sql = "SELECT value, type, date FROM settings WHERE name = :name";

        $stmt = $this->prepare($sql);

        // Bind the parameter name to the placeholder
        $stmt->bindParam(":name", $param_name, SQLITE3_TEXT);

        // Execute the prepared statement
        $result = $stmt->execute();

        if (!$result) {
            echo "Error retrieving setting '{$param_name}': " . $this->lastErrorMsg();  // Consider logging instead of echoing
            return null;
        }

        // Fetch the retrieved row
        $row = $result->fetchArray(SQLITE3_ASSOC);

        if (!$row) {
            return null; // Setting not found
        }


        // Convert value based on type
        if ($row['type'] === 'int')
            $row['value'] = (int) $row['value'];
        elseif ($row['type'] === 'bool')
            $row['value'] =  $row['value'] === "true" ? true : false;

        if (!$full) {
            return $row["value"];
        } else {
            return $row;
        }
    }

    public function tableExists(string $table_name): bool
    {
        // Define the SQL statement to check the table existence
        $sql = "SELECT name FROM sqlite_master 
           WHERE type='table' AND name = :table_name";

        $stmt = $this->prepare($sql);

        // Bind the table name to the placeholder
        $stmt->bindParam(":table_name", $table_name, SQLITE3_TEXT);

        // Execute the prepared statement
        $result = $stmt->execute();

        if (!$result) {
            echo "Error checking table existence: " . $this->lastErrorMsg(); // Consider logging instead of echoing
            return false;
        }

        // Check if a row is returned (meaning the table exists)
        return $result->fetchArray(SQLITE3_ASSOC) !== false;
    }

    public function createSetting(string $name, mixed $value, string $type): ?bool
    {
        $sql = "INSERT INTO settings (name, value, type, date) VALUES (:name, :value, :type, :date)";

        $stmt = $this->prepare($sql);

        if ($type === 'bool') {
            $value = $value ? 'true' : 'false';
        }

        $stmt->bindParam(":name", $name, SQLITE3_TEXT);
        $stmt->bindParam(":value", $value, SQLITE3_TEXT);
        $stmt->bindParam(":type", $type, SQLITE3_TEXT);
        $date = TIMESTAMP;
        $stmt->bindParam(":date", $date, SQLITE3_TEXT);

        // Execute the prepared statement
        $result = $stmt->execute();

        if (!$result) {
            echo "Error creating setting '{$name}': " . $this->lastErrorMsg();  // Consider logging instead of echoing
            return null;
        }

        // Return true on successful insertion (number of rows affected should be 1)
        return $this->changes() === 1;
    }

    public function updateSetting(string $name, mixed $value, ?string $type = null): ?bool
    {
        // Prepare the UPDATE statement with placeholders
        if (!$type) {
            $sql = "UPDATE settings SET value = :value, date = :date WHERE name = :name";
        } else {

            $sql = "UPDATE settings SET value = :value, type = :type, date = :date WHERE name = :name";
        }

        $stmt = $this->prepare($sql);

        if ($type === 'bool') {
            $value = $value ? 'true' : 'false';
        }

        // Bind values to the placeholders
        if ($type) {
            $stmt->bindParam(":type", $type, SQLITE3_TEXT);
        }
        $stmt->bindParam(":value", $value, SQLITE3_TEXT); // Use a helper function for type binding
        $stmt->bindParam(":name", $name, SQLITE3_TEXT);
        $date = TIMESTAMP;
        $stmt->bindParam(":date", $date, SQLITE3_TEXT);
        // Execute the prepared statement
        $result = $stmt->execute();

        if (!$result) {
            echo "Error updating setting '{$name}': " . $this->lastErrorMsg();  // Consider logging instead of echoing
            return null;
        }

        // Return true on successful update (number of rows affected should be 1)
        return $this->changes() === 1;
    }

    public function deleteSetting(string $name): ?bool
    {
        $sql = "DELETE FROM settings WHERE name = :name";

        $stmt = $this->prepare($sql);

        $stmt->bindParam(":name", $name, SQLITE3_TEXT);

        $result = $stmt->execute();

        if (!$result) {
            echo "Error deleting setting '{$name}': " . $this->lastErrorMsg();  // Consider logging instead of echoing
            return null;
        }

        // Return true on successful deletion (number of rows affected should be 1)
        return $this->changes() === 1;
    }


    public function touchSetting(string $name)
    {
        $sql = "UPDATE settings SET date = :date WHERE name = :name";

        $stmt = $this->prepare($sql);

        $date = TIMESTAMP;
        $stmt->bindParam(":date", $date, SQLITE3_TEXT);
        $stmt->bindParam(":name", $name, SQLITE3_TEXT);
        $result = $stmt->execute();

        if (!$result) {
            echo "Error touching setting '{$name}': " . $this->lastErrorMsg();  // Consider logging instead of echoing
            return null;
        }

        // Return true on successful update (number of rows affected should be 1)
        return $this->changes() === 1;
    }

    public function saveProgress($percent, $next, $action)
    {
        // for testing
        //echo PHP_EOL . PHP_EOL . $percent . '|' . $action . PHP_EOL . date("H:i:s") . PHP_EOL . PHP_EOL;

        $this->updateSetting('import_progress', $percent . '|' . $next . '|' . $action);
    }

    public function proxiesStoppedOn()
    {
        $sql = "SELECT date FROM settings WHERE name LIKE 'stop_proxy_%' LIMIT 1";

        $stmt = $this->prepare($sql);

        $result = $stmt->execute();

        if (!$result) {
            echo "Error retrieving proxy, " . $this->lastErrorMsg();  // Consider logging instead of echoing
            return null;
        }

        // Fetch the retrieved row
        $row = $result->fetchArray(SQLITE3_ASSOC);

        if (!$row) {
            return null; // Setting not found
        }

        return $row['date'];
    }

    public function getRandomProxy(array $list = []): ?array
    {
        if (empty($list))
            return [];

        $sql = "SELECT name, value, date FROM settings " .
            "WHERE name IN ('" . implode("','", $list) . "') " .
            " ORDER BY date DESC";

        $result = $this->query($sql);

        if (!$result) {
            echo "Error retrieving random proxy: " . $this->lastErrorMsg();  // Consider logging instead of echoing
            return null;
        }
        // Fetch all rows (excluding newest date) into an array
        $rows = [];
        $first = true;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            //skip first - most recently used
            if ($first && count($list) > 1) {
                $first = false;
                continue;
            }
            $rows[] = $row;
        }

        // Shuffle the array for randomness
        shuffle($rows);

        // Return the first element (random proxy value)
        return $rows[0];
    }

    public function getUnstoppedProxies(): array
    {
        // Prepare the SQL statement with placeholder
        $sql = "SELECT SUBSTR(name, 6) AS proxy_name 
          FROM settings 
          WHERE name LIKE ? " .
            " AND value = ?";

        $stmt = $this->prepare($sql);

        $condition = 'stop_proxy_%';
        $value = 'false';

        // Bind values to the placeholders
        $stmt->bindParam(1, $condition, SQLITE3_TEXT);  // Use GLOB for pattern matching
        $stmt->bindParam(2, $value, SQLITE3_TEXT);

        // Execute the prepared statement
        $result = $stmt->execute();

        if (!$result) {
            echo "Error retrieving unstopped proxies: " . $this->lastErrorMsg();  // Consider logging instead of echoing
            return [];
        }

        // Fetch all rows as an array of associative arrays
        $unstoppedProxies = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $unstoppedProxies[] = $row["proxy_name"];
        }

        return $unstoppedProxies;
    }

    public function updateStopProxySettings($value = false): bool
    {
        $sql = "UPDATE settings SET value = :value, date = :date WHERE name LIKE :name";

        $stmt = $this->prepare($sql);
        $condition = "stop_proxy%";
        $value = $value ? 'true' : 'false';
        $date = TIMESTAMP;

        // Bind values to the placeholders
        $stmt->bindParam(":value", $value, SQLITE3_TEXT);
        $stmt->bindParam(":date", $date, SQLITE3_TEXT);
        $stmt->bindParam(":name", $condition, SQLITE3_TEXT);
        // Execute the prepared statement
        $result = $stmt->execute();

        if (!$result) {
            echo "Error updating stop_proxy settings: " . $this->lastErrorMsg();  // Consider logging instead of echoing
            return false;
        }

        // Return true on successful update
        return true;
    }

    public function getOldestIndividualEntry()
    {
        // where lists the codes of legal forms to search
        $sql = "SELECT persons.ja_kodas, individual.tikr_data FROM persons
        LEFT JOIN individual ON persons.ja_kodas = individual.ja_kodas
        WHERE (persons.form_kodas = 810 OR persons.form_kodas = 811 OR persons.form_kodas = 812 OR persons.form_kodas = 220) AND persons.stat_kodas != 10
        ORDER BY tikr_data ASC 
        LIMIT 1";
        $stmt = $this->prepare($sql);
        $result = $stmt->execute();

        if (!$result) {
            echo "Error retrieving ja_kodas: " . $this->lastErrorMsg();  // Consider logging instead of echoing
            return null; // Or handle the error differently
        }

        // Fetch the first (and only) row
        $row = $result->fetchArray(SQLITE3_ASSOC);

        // Return the ja_kodas value (or null if no row found)
        return $row ?? null;
    }

    public function updatePersonsFromIndividual($total = false): int
    {
        // Get yesterday's date in YYYY-MM-DD format
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        // Prepare and execute query to fetch relevant entries from individual table
        if ($total) {
            $stmt = $this->prepare('SELECT ja_kodas, ja_pavadinimas FROM individual WHERE tikr_statusas = "Success"');
        } else {
            $stmt = $this->prepare('SELECT ja_kodas, ja_pavadinimas FROM individual WHERE tikr_data = :yesterday AND tikr_statusas = "Success"');
            $stmt->bindValue(':yesterday', $yesterday, SQLITE3_TEXT);
        }
        $result = $stmt->execute();

        // check if result is empty
        if ($result->numColumns() === 0) {
            return 0;
        }

        // Prepare update statement for persons table
        $updateStmt = $this->prepare('UPDATE persons SET ja_pavadinimas = :ja_pavadinimas WHERE ja_kodas = :ja_kodas');

        // Iterate through results and update persons table
        $noUpdated = 0;
        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $ja_kodas = $row['ja_kodas'];
            $ja_pavadinimas = $row['ja_pavadinimas'];

            // Bind parameters and execute update statement
            $updateStmt->bindValue(':ja_pavadinimas', $ja_pavadinimas, SQLITE3_TEXT);
            $updateStmt->bindValue(':ja_kodas', $ja_kodas, SQLITE3_INTEGER);
            $updateStmt->execute();
            $noUpdated++;
        }

        // Close statements
        $stmt->close();
        $updateStmt->close();

        return $noUpdated;
    }

    public function insertUpdateIndividualEntry($data, $insert = true)
    {
        try {
            if ($insert) {
                $insert_query = "INSERT INTO individual (ja_pavadinimas, tikr_statusas, tikr_data,ja_kodas) VALUES (?, ?, ?, ?)";
                $stmt = $this->prepare($insert_query);
            } else {
                $update_query = "UPDATE individual SET ja_pavadinimas = ?, tikr_statusas = ?, tikr_data = ? WHERE ja_kodas = ?";
                $stmt = $this->prepare($update_query);
            }
            $stmt->bindValue(1, $data['ja_pavadinimas'], SQLITE3_TEXT);
            $stmt->bindValue(2, $data['tikr_statusas'], SQLITE3_TEXT);
            $today = date('Y-m-d');
            $stmt->bindValue(3, $today, SQLITE3_TEXT);
            $stmt->bindValue(4, $data['ja_kodas'], SQLITE3_INTEGER);
            $stmt->execute();
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
        }
    }

    public function getIndividualRecordCounts()
    {
        // Get the total number of records in the table
        $result = $this->query('SELECT COUNT(*) as count FROM Individual');
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $totalRecords = $row['count'];

        // Get the current date in Y-m-d format
        $todayDate = date('Y-m-d');

        // Get the number of records created today
        $stmt = $this->prepare('SELECT COUNT(*) as count FROM Individual WHERE tikr_data = :todayDate');
        $stmt->bindValue(':todayDate', $todayDate, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $recordsToday = $row['count'];

        // Get the number of records still undone
        $stmt2 = $this->prepare('
        SELECT COUNT(*) as count 
        FROM persons 
        LEFT JOIN individual ON individual.ja_kodas = persons.ja_kodas
        WHERE (form_kodas = :code1 OR form_kodas = :code11 OR form_kodas = :code12 OR form_kodas = :code2) AND stat_kodas != :code3 AND individual.tikr_data IS NULL
        ');
        $ind_kodas = 810;
        $ind_kodas2 = 811;
        $ind_kodas3 = 812;
        $kom_kodas = 220;
        $stmt2->bindValue(':code1', $ind_kodas, SQLITE3_INTEGER);
        $stmt2->bindValue(':code11', $ind_kodas2, SQLITE3_INTEGER);
        $stmt2->bindValue(':code12', $ind_kodas3, SQLITE3_INTEGER);
        $stmt2->bindValue(':code2', $kom_kodas, SQLITE3_INTEGER);
        $stat_kodas = 10;
        $stmt2->bindValue(':code3', $stat_kodas, SQLITE3_INTEGER);
        $result2 = $stmt2->execute();
        $row = $result2->fetchArray(SQLITE3_ASSOC);
        $targetRecords = $row['count'];

        return [
            'targetRecords' => $targetRecords,
            'totalRecords' => $totalRecords,
            'recordsToday' => $recordsToday
        ];
    }

    /**
     * Export invididual enterprises data to CSV file
     * 
     * @param $csvFile - local path to the CSV file
     */
    public function exportIndividualToCsv(string $csvFile = 'writable/individual.csv')
    {

        $csvFile = BASE_DIR . $csvFile;

        if (file_exists($csvFile)) {
            unlink($csvFile);
        }

        // Open the CSV file for writing
        if (!$fileHandle = fopen($csvFile, 'w')) {
            die("Could not open the file for writing.");
        }

        // Define the CSV delimiter
        $delimiter = '|';

        // Write the column headers to the CSV file
        $column_headers = ['ja_kodas', 'ja_pavadinimas', 'tikr_statusas', 'tikr_data'];
        fputcsv($fileHandle, $column_headers, $delimiter);

        // Query to select all data from the individual table
        $sql = "SELECT ja_kodas, ja_pavadinimas, tikr_statusas, tikr_data FROM individual ORDER BY tikr_data DESC";

        try {
            // Execute the query
            $result = $this->query($sql);

            // Fetch and write each row to the CSV file
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                fputcsv($fileHandle, $row, $delimiter);
            }

            // Close the file handle
            fclose($fileHandle);

            echo "Data has been successfully exported to $csvFile" . PHP_EOL . PHP_EOL;
        } catch (Exception $e) {
            die("Failed to execute query: " . $e->getMessage());
        }
    }
}

class Benchmark
{
    private $startTime;
    private $endTime;
    private $startMemory;
    private $endMemory;
    private $peakMemory;

    // Start the benchmark
    public function start()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
        $this->peakMemory = memory_get_peak_usage();
    }

    // Stop the benchmark
    public function stop()
    {
        $this->endTime = microtime(true);
        $this->endMemory = memory_get_usage();
        // Peak memory usage is updated automatically
        $this->peakMemory = memory_get_peak_usage();
    }

    // Show the elapsed time and memory usage in Bootstrap 5 alert format
    public function showHtml()
    {
        $elapsedTime = $this->endTime - $this->startTime;
        $startMemoryKB = $this->startMemory / 1024;
        $endMemoryKB = $this->endMemory / 1024;
        $peakMemoryKB = $this->peakMemory / 1024;
        $memoryUsageKB = $peakMemoryKB - $startMemoryKB;

        return "
            <div class='alert alert-info' role='alert'>
                <h4 class='alert-heading'>Benchmark Results</h4>
                <p>Elapsed time: " . number_format($elapsedTime, 5) . " seconds</p>
                <p>Memory usage at start: " . number_format($startMemoryKB, 2) . " KB</p>
                <p>Memory usage at end: " . number_format($endMemoryKB, 2) . " KB</p>
                <p>Peak memory usage: " . number_format($peakMemoryKB, 2) . " KB</p>
                <p>Script memory usage: " . number_format($memoryUsageKB, 2) . " KB</p>
            </div>
        ";
    }

    public function showTxt()
    {
        $elapsedTime = $this->endTime - $this->startTime;
        $startMemoryKB = $this->startMemory / 1024;
        $endMemoryKB = $this->endMemory / 1024;
        $peakMemoryKB = $this->peakMemory / 1024;
        $memoryUsageKB = $peakMemoryKB - $startMemoryKB;

        return "Benchmark Results\n" .
            "Elapsed time: " . number_format($elapsedTime, 5) . " seconds\n" .
            "Memory usage at start: " . number_format($startMemoryKB, 2) . " KB\n" .
            "Memory usage at end: " . number_format($endMemoryKB, 2) . " KB\n" .
            "Peak memory usage: " . number_format($peakMemoryKB, 2) . " KB\n" .
            "Script memory usage: " . number_format($memoryUsageKB, 2) . " KB\n";
    }
}

class import
{

    private mySQlite3 $db;
    private string $tableName;
    private bool $unregCase = false;
    private string $unregSuffix = '';
    private string $filePath = BASE_DIR . 'writable/';
    private string $fileName;
    private bool $debug = false;

    private $expectedHeaders = [
        "ja_kodas|ja_pavadinimas|adresas|ja_reg_data|form_kodas|form_pavadinimas|stat_kodas|stat_pavadinimas|stat_data_nuo|formavimo_data\n",
        "ja_kodas|ja_pavadinimas|adresas|ja_reg_data|form_kodas|form_pavadinimas|isreg_data|formavimo_data\n",
        "stat_kodas|stat_pavadinimas|stat_name|formavimo_data\n",
        "form_kodas|form_pavadinimas|form_pav_ilgas|form_name|tipas|type|formavimo_data\n",
        "ja_kodas|ja_pavadinimas|tikr_statusas|tikr_data\n"
    ];

    private $createSqls = [
        "forms" => [
            "CREATE TABLE IF NOT EXISTS forms (
                form_kodas INTEGER PRIMARY KEY,
                form_pavadinimas TEXT COLLATE utf8_lithuanian_ci,
                form_pav_ilgas TEXT COLLATE utf8_lithuanian_ci,
                tipas TEXT
            )",
            "CREATE INDEX idx_form_kodas ON forms(form_kodas)",
            "CREATE INDEX idx_form_pavadinimas ON forms(form_pavadinimas COLLATE utf8_lithuanian_ci)",
            "CREATE INDEX idx_form_pav_ilgas ON forms(form_pav_ilgas COLLATE utf8_lithuanian_ci)",
            "CREATE INDEX idx_form_tipas ON forms(tipas)",
        ],
        "statuses" => [
            "CREATE TABLE IF NOT EXISTS statuses (
                stat_kodas INTEGER PRIMARY KEY,
                stat_pavadinimas TEXT COLLATE utf8_lithuanian_ci
            )",
            "CREATE INDEX idx_stat_kodas ON statuses(stat_kodas)",
            "CREATE INDEX idx_stat_pavadinimas ON statuses(stat_pavadinimas COLLATE utf8_lithuanian_ci)",
        ],
        "persons" => [
            "CREATE TABLE IF NOT EXISTS persons (
                ja_kodas INTEGER PRIMARY KEY,
                ja_pavadinimas TEXT COLLATE utf8_lithuanian_ci,
                adresas TEXT COLLATE utf8_lithuanian_ci,
                ja_reg_data TEXT,
                form_kodas INTEGER,
                stat_kodas INTEGER,
                stat_data_nuo TEXT,
                isreg_data TEXT,
                formavimo_data TEXT
            )",
            "CREATE INDEX idx_ja_kodas ON persons(ja_kodas)",
            "CREATE INDEX idx_ja_pavadinimas ON persons(ja_pavadinimas COLLATE utf8_lithuanian_ci)",
            "CREATE INDEX idx_adresas ON persons(adresas COLLATE utf8_lithuanian_ci)",
        ],
        "individual" => [
            "CREATE TABLE IF NOT EXISTS individual (
                ja_kodas INTEGER PRIMARY KEY,
                ja_pavadinimas TEXT COLLATE utf8_lithuanian_ci,
                tikr_statusas TEXT,
                tikr_data TEXT
            )",
        ],
    ];

    public function __construct(mySQlite3 $db, string $tableName, bool $unreg = false)
    {
        $this->db = $db;
        $this->tableName = $tableName;
        if ($unreg && $tableName === 'persons') {
            $this->unregCase = true;
            $this->unregSuffix = 'Unreg';
        }
        $this->fileName = $this->tableName . $this->unregSuffix . '.csv';
    }

    public function enableDebug()
    {
        $this->debug = true;
    }

    public function checkFileAndGetDate(): ?string
    {
        if (($handle = fopen($this->filePath . $this->fileName, 'r')) !== FALSE) {
            // Read the first line
            $header = fgets($handle);

            // Check if the header matches the expected header
            if (in_array($header, $this->expectedHeaders)) {
                $secondLine = fgets($handle);

                // Split the second line into fields
                $fields = str_getcsv($secondLine, '|');

                // Get the last element (formavimo_data)
                return $fields[count($fields) - 1];
            }
            fclose($handle);
        }
        // if file not found, or if the header does not match the expected
        return null;
    }

    public function downloadFile($delete = true): bool
    {
        $file = $this->filePath . $this->fileName;
        if (isset($this->debug)) {
            echo 'checking for file ' . $this->fileName . PHP_EOL;
        }
        if (file_exists($file) && $delete) {
            unlink($file);
        }
        if (!file_exists($file)) {
            if (isset($this->debug)) {
                echo 'downloading file as it does not exist: ' . $this->fileName . PHP_EOL;
            }
            // need to rebuild constant from supplied data:
            $unregPart = $this->unregCase ? '_' . $this->unregSuffix : '';
            $typePath = strtoupper('OPEN_DATA_' . $this->tableName . $unregPart . '_URL');
            if ($this->tableName === 'individual') {
                //typepath includes full url in this case:
                file_put_contents($file, fopen(constant($typePath), 'r'));
            } else {
                file_put_contents($file, fopen(RC_WEB . constant($typePath), 'r'));
            }
        }
        return file_exists($file);
    }

    private function dropTable(): bool
    {
        if (isset($this->debug)) {
            echo 'dropping table ' . $this->tableName . PHP_EOL;
        }
        $result = $this->db->exec("DROP TABLE IF EXISTS " . $this->tableName);
        return $result !== false;
    }

    private function createTable(): bool
    {
        if (isset($this->debug)) {
            echo 'creating table ' . $this->tableName . PHP_EOL;
        }
        $success = true;
        foreach ($this->createSqls[$this->tableName] as $sql) {
            $result = $this->db->exec($sql);
            if ($result === false) {
                $success = false;
                break; // Early exit if any SQL statement fails
            }
        }
        return $success;
    }

    /** 
     * convenience method to refresh drop/create table
     */
    public function refreshTable(): bool
    {
        if (isset($this->debug)) {
            echo 'running refresh table ' . $this->tableName . PHP_EOL;
        }
        // skip if the dataset is not that of unregistered legal persons
        if (!$this->unregCase) {
            return $this->dropTable() && $this->createTable();
        }
        return true;
    }

    /**
     * Fills the table with data from the CSV file.
     *
     * Returns an associative array with details:
     *  - success (bool): True on success, false on failure.
     *  - message (string): Optional error message if unsuccessful.
     *  - rows_inserted (int): Optional number of rows inserted (if successful).
     */
    public function fillTable()
    {
        $batchSize = 1000;
        $rows = [];
        $insertedRowCount = 0;

        $writeDataByTable = 'writeData' . ucfirst($this->tableName) . $this->unregSuffix;

        // Ignore the first line of the CSV file (column names)
        $handle = fopen($this->filePath . $this->fileName, "r");
        fgets($handle); // Read and ignore the first line

        if ($handle !== FALSE) {
            while (($data = fgetcsv($handle, 1000, "|")) !== FALSE) {
                $rows[] = $data;
                if (count($rows) >= $batchSize) {
                    $insertResult = $this->$writeDataByTable($rows);
                    if (!$insertResult) {
                        fclose($handle);
                        return [
                            'success' => false,
                            'message' => 'Error inserting data into table',
                        ];
                    }
                    $insertedRowCount += count($rows);
                    $rows = [];
                }
            }

            if (count($rows) > 0) {
                $insertResult = $this->$writeDataByTable($rows);
                if (!$insertResult) {
                    fclose($handle);
                    return [
                        'success' => false,
                        'message' => 'Error inserting data into table',
                    ];
                }
                $insertedRowCount += count($rows);
            }
            fclose($handle);
        } else {
            return [
                'success' => false,
                'message' => 'Error opening CSV file',
            ];
        }

        return [
            'success' => true,
            'message' => 'Table filled successfully',
            'rows_inserted' => $insertedRowCount,
        ];
    }

    /**
     * TABLE-SPECIFIC FUNCTIONS, TOO COMPLICATED TO WRITE ONE UNIVERSAL...
     */

    private function writeDataPersons($rows)
    {
        // ĮREGISTRUOTI:
        // ja_kodas | ja_pavadinimas | adresas | ja_reg_data | form_kodas | form_pavadinimas | stat_kodas | stat_pavadinimas | stat_data_nuo | formavimo_data
        $this->db->exec("BEGIN TRANSACTION");
        $stmt = $this->db->prepare("INSERT INTO persons 
        (ja_kodas, ja_pavadinimas, adresas, ja_reg_data, form_kodas, stat_kodas, stat_data_nuo, formavimo_data)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($rows as $data) {
            $stmt->bindValue(1, $data[0], SQLITE3_INTEGER); // ja_kodas            
            $stmt->bindValue(2, $data[1], SQLITE3_TEXT);    // ja_pvadinimas
            $stmt->bindValue(3, $data[2], SQLITE3_TEXT);    // adresas
            $stmt->bindValue(4, $data[3], SQLITE3_TEXT);    // ja_reg_data 
            $stmt->bindValue(5, $data[4], SQLITE3_INTEGER); // form_kodas
            //$stmt->bindValue(6, $data[5], SQLITE3_TEXT);    // form_pavadinimas
            $stmt->bindValue(6, $data[6], SQLITE3_INTEGER); // stat_kodas        isreg_data/text
            //$stmt->bindValue(8, $data[7], SQLITE3_TEXT);    // stat_pavadinimas  formavimo_data/text | pabaiga
            $stmt->bindValue(7, $data[8], SQLITE3_TEXT);    // stat_data_nuo
            $stmt->bindValue(8, $data[9], SQLITE3_TEXT);   // formavimo_data
            $stmt->execute();
        }

        // Check if any statement within the transaction failed
        $errorCode = $this->db->lastErrorCode();
        if ($errorCode !== 0) {
            // Transaction failed, rollback
            $this->db->exec("ROLLBACK TRANSACTION");
            return false;
        }

        $this->db->exec("END TRANSACTION");
        return true; // Transaction successful
    }

    private function writeDataPersonsUnreg($rows)
    {
        // IŠREGISTRUOTI:
        // ja_kodas | ja_pavadinimas | adresas | ja_reg_data | form_kodas | form_pavadinimas | isreg_data | formavimo_data

        $this->db->exec("BEGIN TRANSACTION");
        $stmt = $this->db->prepare("INSERT INTO persons (ja_kodas, ja_pavadinimas, adresas, ja_reg_data, form_kodas, isreg_data, formavimo_data, stat_kodas, stat_data_nuo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($rows as $data) {
            $stmt->bindValue(1, $data[0], SQLITE3_INTEGER); // ja_kodas            
            $stmt->bindValue(2, $data[1], SQLITE3_TEXT);    // ja_pvadinimas
            $stmt->bindValue(3, $data[2], SQLITE3_TEXT);    // adresas
            $stmt->bindValue(4, $data[3], SQLITE3_TEXT);    // ja_reg_data 
            $stmt->bindValue(5, $data[4], SQLITE3_INTEGER); // form_kodas
            //$stmt->bindValue(6, $data[5], SQLITE3_TEXT);    // form_pavadinimas
            $stmt->bindValue(6, $data[6], SQLITE3_TEXT); // isreg_data/text
            $stmt->bindValue(7, $data[7], SQLITE3_TEXT);    // formavimo_data/text | pabaiga
            $stmt->bindValue(8, 10, SQLITE3_INTEGER);    // stat_kodas - iš statusų lent
            //$stmt->bindValue(10, 'Išregistruotas', SQLITE3_TEXT);    // stat_pavadinimas
            $stmt->bindValue(9, $data[6], SQLITE3_TEXT);   // stat_data_nuo = išreg data
            $stmt->execute();
        }


        // Check if any statement within the transaction failed
        $errorCode = $this->db->lastErrorCode();
        if ($errorCode !== 0) {
            // Transaction failed, rollback
            $this->db->exec("ROLLBACK TRANSACTION");
            return false;
        }

        $this->db->exec("END TRANSACTION");
        return true; // Transaction successful
    }

    private function writeDataForms($rows)
    {
        // FORMOS
        // form_kodas|form_pavadinimas|form_pav_ilgas|form_name|tipas|type|formavimo_data
        $this->db->exec("BEGIN TRANSACTION");
        $stmt = $this->db->prepare("INSERT INTO forms (form_kodas, form_pavadinimas, form_pav_ilgas, tipas) VALUES (?, ?, ?, ?)");
        foreach ($rows as $data) {
            $stmt->bindValue(1, $data[0], SQLITE3_INTEGER);
            $stmt->bindValue(2, $data[1], SQLITE3_TEXT);
            $stmt->bindValue(3, $data[2], SQLITE3_TEXT);
            $stmt->bindValue(4, $data[4], SQLITE3_TEXT);
            $stmt->execute();
        }

        // Check if any statement within the transaction failed
        $errorCode = $this->db->lastErrorCode();
        if ($errorCode !== 0) {
            // Transaction failed, rollback
            $this->db->exec("ROLLBACK TRANSACTION");
            return false;
        }

        $this->db->exec("END TRANSACTION");
        return true; // Transaction successful
    }

    private function writeDataStatuses($rows)
    {
        // STATUSES
        // stat_kodas|stat_pavadinimas|stat_name|formavimo_data
        $this->db->exec("BEGIN TRANSACTION");
        $stmt = $this->db->prepare("INSERT INTO statuses (stat_kodas, stat_pavadinimas) VALUES (?, ?)");
        foreach ($rows as $data) {
            $stmt->bindValue(1, $data[0], SQLITE3_INTEGER);
            $stmt->bindValue(2, $data[1], SQLITE3_TEXT);
            $stmt->execute();
        }

        // Check if any statement within the transaction failed
        $errorCode = $this->db->lastErrorCode();
        if ($errorCode !== 0) {
            // Transaction failed, rollback
            $this->db->exec("ROLLBACK TRANSACTION");
            return false;
        }

        $this->db->exec("END TRANSACTION");
        return true; // Transaction successful
    }

    private function writeDataIndividual($rows)
    {
        // STATUSES
        // stat_kodas|stat_pavadinimas|stat_name|formavimo_data
        $this->db->exec("BEGIN TRANSACTION");
        $stmt = $this->db->prepare("INSERT INTO individual (ja_kodas, ja_pavadinimas, tikr_statusas, tikr_data) VALUES (?, ?, ?, ?)");
        foreach ($rows as $data) {
            $stmt->bindValue(1, $data[0], SQLITE3_INTEGER);
            $stmt->bindValue(2, $data[1], SQLITE3_TEXT);
            $stmt->bindValue(3, $data[2], SQLITE3_TEXT);
            $stmt->bindValue(4, $data[3], SQLITE3_TEXT);
            $stmt->execute();
        }

        // Check if any statement within the transaction failed
        $errorCode = $this->db->lastErrorCode();
        if ($errorCode !== 0) {
            // Transaction failed, rollback
            $this->db->exec("ROLLBACK TRANSACTION");
            return false;
        }

        $this->db->exec("END TRANSACTION");
        return true; // Transaction successful
    }
}
