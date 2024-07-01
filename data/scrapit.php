<?php

/**
 * this file is usually run by cron, but could be run with command line arguments too
 * 
 * when run without parameters in command line it scraps one title (to get the same behaviour on a
 * get request you have to supply key=proxyKeyFromConfigFile get parameter)
 * 
 * possible parameters when running from command line:
 * `update` - perform the daily update of the main database by the scrapped names of individual enterprises
 * `ifnewmonth` (used together with `update`) - check if the monthly update of individual enterprises data should be performed and do it
 * `report` - show the statistics of scrapping 
 * `export_individual` - export scrapped data of individual enterprises to a csv file
 * 
 * possible GET parameters: 
 * download=individual - force download of the csv file of individual enterprises
 * debug= - echo data to the browser
 */

require_once('../config.php');
require_once(BASE_DIR . 'data/data-functions.php');
require_once(BASE_DIR . 'common/classes.php');
require_once(BASE_DIR . 'common/functions.php');

if (!file_exists(DBFILE)) {
    require_once(BASE_DIR . 'data/initialize-db.php');
}

$message = '';
$subject = 'scrapit.php išvestis';

if (!isset($db)) {
    $db = new mySQLite3(DBFILE);
}

/**
 * statistics gathering and update of persons table block
 */

if (isset($argv[1]) && in_array('update', $argv)) {

    $total = false;
    if (in_array('ifnewmonth', $argv)) {
        $message .= "Tikrinama, ar reikia daryti kasmėnesinį individualių įmonių ir komanditinių ūkinių bendrijų pavadinimų atnaujinimą..." . "\n";
        $lastUpdate = $db->getSetting('individual_last_update');
        $lastImport = $db->getSetting('data_formed_persons');
        if ($lastUpdate < $lastImport) {
            $message .= 'Nereikia pilno atnaujinimo. Vykdymas nutraukiamas' . "\n";
            emailAdmin($subject, $message);
            exit;
        }
        $total = true;
    } else {
        $message .= "Bus vykdomas kasdieninis ind. įmonių ir kom. ūk. bendrijų pavadinimų atnaujinimas vakar surinktais duomenimis..." . "\n";
    }

    $stats = $db->getIndividualRecordCounts();


    if (in_array('forcetotal', $argv)) {
        $message .= 'Vykdomas veiksmas – pilnas individualių įmonių ir komanditinių ūkinių bendrijų pavadinimų atnaujinimas neatsižvelgiant į status quo...' . "\n";
        $total = true;
    }

    $noUpdated = $db->updatePersonsFromIndividual($total);
    $db->updateSetting('individual_last_update', date('Y-m-d'), 'string');

    $message .= "Viso veikiančių individualių įmonių / komanditinių ūkinių bendrijų nesutvarkytais pavadinimais: " . $stats['targetRecords'] . "\n";
    $message .= "Gauta pavadinimų iš viso: " . $stats['totalRecords'] . "\n";
    $message .= "Šiandien atnaujinta pavadinimų: " . $noUpdated . "\n";

    emailAdmin($subject, $message);
    exit;
}

if (isset($argv[1]) && in_array('report', $argv)) {

    $stats = $db->getIndividualRecordCounts();

    $message .= "Viso veikiančių individualių įmonių ir komanditinių ūkinių bendrijų nesutvarkytais pavadinimais: " . $stats['targetRecords'] . "\r\n";
    $message .= "Gautų (sutvarkytų) pavadinimų iš viso: " . $stats['totalRecords'] . "\r\n";
    $message .= "Gautų (sutvarkytų) pavadinimų šiandien: " . $stats['recordsToday'] . "\r\n";

    echo $subject . PHP_EOL;
    echo $message;

    emailAdmin($subject, $message);
    exit;
}

/**
 * export individual table content to csv for easy download by third parties
 */
if (isset($argv[1]) && in_array('export_individual', $argv)) {
    $benchmark = new Benchmark();
    $benchmark->start();

    $db->exportIndividualToCsv();

    $benchmark->stop();
    echo $benchmark->showTxt();
}

/**
 * download file
 */
if (isset($_GET['download']) && $_GET['download'] === 'individual') {
    downloadCsv(BASE_DIR . 'writable/individual.csv', 'individual');
}


/**
 * scrapping execution block, - run if there is no stats gathering specified
 */

$proxy_key = PROXY_API_KEY;

if (isset($_GET['key']) && $_GET['key'] === $proxy_key) {
    // do nothing
} elseif (!isset($_SERVER['REQUEST_METHOD'])) {
    // do nothing
} else {
    echo 'Something fishy!';
    exit;
}


// reset to unstopped if they were stopped not today
$date_proxy_stopped = substr($db->proxiesStoppedOn(), 0, 10);
if ($date_proxy_stopped < date('Y-m-d')) {
    $db->updateStopProxySettings(false);
}

$list = $db->getUnstoppedProxies();

if (isset($_GET['debug'])) {
    echo '<pre>';
    var_dump($list);
}

$proxy = $db->getRandomProxy($list);

if (empty($proxy)) {
    //no proxies could be found, perhaps the limits were exceeded
    return;
}

$newEntry = $db->getOldestIndividualEntry();

$url = $proxy['value'] . RC_WEB . SCRAP_URL . $newEntry['ja_kodas'];

if (isset($_GET['debug'])) {

    var_dump('url: ' . $url);
}

$content = getPageContent($url);

$result = retrieveInfo($content, $newEntry['ja_kodas']);

if (isset($_GET['debug'])) {
    var_dump(['result: ', $result]);
}

if ($result['status'] === 'Success') {
    $db->insertUpdateIndividualEntry($result['data'], is_null($newEntry['tikr_data']));
    $db->touchSetting($proxy['name']);
} elseif ($result['status'] === 'Limit exceeded' || $result['status'] === 'Repeat') {
    // disable that proxy
    $db->updateSetting('stop_' . $proxy['name'], true, 'bool');
} elseif ($result['status'] === 'Not found') {
    $db->insertUpdateIndividualEntry($result['data'], is_null($newEntry['tikr_data']));
    $db->touchSetting($proxy['name']);
} // else: do nothing: cases of ""

if (isset($_GET['debug'])) {
    var_dump($db->getIndividualRecordCounts());
}

$db->close();
