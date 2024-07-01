<?php
// at least 50 seconds needed, give more
ini_set('max_execution_time', '100');
require_once(__DIR__ . '/../config.php');
require_once(BASE_DIR . 'data/data-functions.php');
require_once(BASE_DIR . 'common/functions.php');
require_once(BASE_DIR . 'common/classes.php');

echo '<pre>';

$checkIfNew = false;
$table = null;
$unreg = false;
$postfix = '';

$message = '';
$subject = 'importnew.php išvestis';

// gather variables from php command line
if (isset($argv[1])) {
    $table = in_array('persons', $argv) ? 'persons' : (in_array('forms', $argv) ? 'forms' : (in_array('statuses', $argv) ? 'statuses' : (in_array('individual', $argv) ? 'individual' : '')));
    $unreg = in_array('unreg', $argv) && in_array('persons', $argv); //only applies on persons tbl
    $checkIfNew = in_array('checkifnew', $argv);
    $debug = in_array('debug', $argv);
    $sendEmail = in_array('sendemail', $argv);
    $testing = in_array('testing', $argv);

    // gather vars from url
} elseif (isset($_GET['table'])) {
    if (!isset($_GET['key']) || (isset($_GET['key']) && $_GET['key'] !== 'youBetterBeVeryCareful')) {
        echo 'You do not have the right to access this page';
        exit;
    }
    if (in_array($_GET['table'], ['statuses', 'forms', 'persons', 'individual'])) {
        $table = $_GET['table'];
        $unreg = isset($_GET['unreg']) && $_GET['table'] === 'persons';
        $checkIfNew = isset($_GET['checkifnew']);
        $debug = isset($_GET['debug']);
        $sendEmail = isset($_GET['sendemail']);
        $testing = isset($_GET['testing']);
    } else {
        echo 'Wrong table (persons|forms|statuses|individual)';
        exit;
    }
} else {
    echo 'please specify a table to import (persons|forms|statuses|individual)';
    exit;
}

if ($unreg) {
    $postfix = '_unreg';
}

$benchmark = new Benchmark();
$benchmark->start();

$db = new mySQLite3(DBFILE);
//enable concurrent reads while write is in progress
$db->exec('PRAGMA journal_mode = WAL;');

$import = new Import($db, $table, $unreg);
if ($debug) {
    $import->enableDebug();
}

// if not testing mode, downloaded file will be deleted in advance
$downloadedFile = $import->downloadFile(!$testing);
$dateInFile = $import->checkFileAndGetDate();
$dateInDb = $db->getSetting('data_formed_' . $table . $postfix);
if ($checkIfNew) {
    if (is_null($dateInFile) || $dateInFile == $dateInDb) {
        echo "data in db is of the same date as in file, exiting" . PHP_EOL;
        exit;
    }
}

$tableRefreshed = $import->refreshTable();
$fillResult = [];
if ($downloadedFile && $tableRefreshed) {

    echo "Filling table {$table} with data" . PHP_EOL;
    $fillResult = $import->fillTable();
}

echo 'Updating database settings entry for the table...' . PHP_EOL;

$db->updateSetting('data_formed_' . $table . $postfix, $dateInFile, 'string');


$message = '';
foreach ($fillResult as $key => $value) {
    $message .= $key . ' ' . $value . PHP_EOL;
}
echo $message;

if ($sendEmail) {
    emailAdmin('Lentelės "' . $table . '" atnaujinimo duomentys', $message);
}


$benchmark->stop();
echo $benchmark->showTxt();
