<?php

if (!isset($argv[1]) || (isset($argv[1]) && $argv[1] !== 'initial')) {
    return 0;
}
require_once(__DIR__ . '/../config.php');
require_once(BASE_DIR . 'common/classes.php');

echo '<pre>';
if (!isset($db)) {
    $db = new mySQLite3(BASE_DIR . DBFILE);
}

// if settings are ok, do not execute further statements
if ($db->tableExists('settings') && !is_null($db->getSetting('today_proxy_first'))) {
    return;
}

// $sql = "DROP TABLE IF EXISTS settings";

// $result = $db->query($sql);

// Define the SQL statement to create the table (if it doesn't exist)
$sql = "CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    value TEXT,
    type TEXT,
    date TEXT
)";

$result = $db->query($sql);

$settings_data = array(
    array(
        "name" => "data_formed_persons",
        "value" => "2024-06",
        "type" => "string",
        "date" => TIMESTAMP,
    ),
    array(
        "name" => "data_formed_persons_unreg",
        "value" => "2024-06",
        "type" => "string",
        "date" => TIMESTAMP,
    ),
    array(
        "name" => "data_formed_statuses",
        "value" => "2024-06",
        "type" => "string",
        "date" => TIMESTAMP,
    ),
    array(
        "name" => "data_formed_forms",
        "value" => "2024-06",
        "type" => "string",
        "date" => TIMESTAMP,
    ),
    array(
        "name" => "individual_last_update",
        "value" => "2024-06",
        "type" => "string",
        "date" => TIMESTAMP,
    ),
    array(
        "name" => "today_proxy_first",
        "value" => "true",
        "type" => "string",
        "date" => TIMESTAMP,
    ),
    array(
        "name" => "import_progress",
        "value" => "0|3|Pradedamas duomenų importavimas",
        "type" => "string",
        "date" => TIMESTAMP,
    ),

);

foreach (SCRAP_PROXIES as $key => $value) {
    $settings_data[] = array(
        "name" => $key,
        "value" => $value,
        "type" => "string",
        "date" => TIMESTAMP,
    );
    $settings_data[] = array(
        "name" => "stop_" . $key,
        "value" => "false",
        "type" => "string",
        "date" => date("Y-m-d", strtotime(TIMESTAMP . "-1 day")),
    );
}

// Loop through the settings data array
foreach ($settings_data as $setting) {
    // Prepare the INSERT statement with placeholders
    $sql = "INSERT INTO settings (name, value, type, date)
        VALUES (:name, :value, :type, :date)";

    $stmt = $db->prepare($sql);

    // Bind values to the prepared statement
    $stmt->bindParam(":name", $setting["name"], SQLITE3_TEXT);
    $stmt->bindParam(":value", $setting["value"], SQLITE3_TEXT);
    $stmt->bindParam(":type", $setting["type"], SQLITE3_TEXT);
    $stmt->bindParam(":date", $setting["date"], SQLITE3_TEXT);

    // Execute the prepared statement
    $result = $stmt->execute();

    if (!$result) {
        echo "Error inserting setting '{$setting['name']}': " . $db->lastErrorMsg() . PHP_EOL;
    } else {
        echo "Setting '{$setting['name']}' inserted successfully." . PHP_EOL;
    }
}

// it does not REALLY happen at this point, but lets fool the user :)
usleep(500000);
$db->saveProgress('3', '6', 'Kuriama duomenų bazė ...');
usleep(500000);
$db->saveProgress('6', '8', 'Kuriama lentelė „settings“, ji užpildoma duomenimis...');

echo 'check other tables and import data' . PHP_EOL;

// cycle through the other tables
// '8|48|persons' - means: processing table persons starts at 8% and ends at 48% of progress
$tables = ['8|52|persons', '52|89|persons unreg', '89|91|statuses', '91|93|forms', '93|96|individual'];
foreach ($tables as $value) {
    list($progress, $next, $tableStr) = explode('|', $value);
    $tbl = explode(' ', $tableStr);
    $table = $tbl[0];
    $isUnregTable = (isset($tbl[1])) ? true : false;
    usleep(50000);
    $db->saveProgress($progress, $next, 'Atsisiunčiamas duomenų failas ir jo duomenimis užpildoma lentelė „' . $table . '“ ' . ($isUnregTable ? '(išregistruotų JA duomenys)' : ''));
    usleep(50000);
    // apply on non-existent tables, except for persons (also run on it if it is unreg data)
    if (!$db->tableExists($table) || $isUnregTable) {
        //echo 'executing shell_exec';
        $importFile = BASE_DIR . 'data/importnew.php debug ' . $tableStr;
        //echo $importFile;
        $output = shell_exec('php ' . $importFile . ' testing 2>&1');

        echo $output . PHP_EOL;
    }
    
}

usleep(50000);
$db->saveProgress('96', '100', 'Atnaujinami individualių įmonių ir komanditinių ūkinių bendrovių pavadinimai');
$output = shell_exec('php ' . BASE_DIR . 'data/scrapit.php update forcetotal 2>&1');
echo $output . PHP_EOL;
usleep(500000);
$db->saveProgress('100', '100', 'Duomenų importavimas baigtas');
echo 'DATA IMPORT DONE, NOW RELOAD THE PAGE';

echo '</pre>';

exit;
