<?php
if (count(get_included_files()) == 1) die('This file is not meant to be accessed directly.');

require_once('common/functions.php');

/**
 * Main settings file
 * This file should be renamed to config.php
 */

/**
 * Get variables from .env if the file exists
 */
if (file_exists(__DIR__ . '/.env'))
    loadEnv(__DIR__ . '/.env');

/**
 * Change to the subdir(s) of your project, leave empty if the app is at the website root.
 * So, empty if your index file is https://example.com/index.php, and "jar" if it is 
 * at https://example.com/jar/index.php
 */
if (!defined("SUBDIR"))
    define("SUBDIR", "jar");

/**
 * Full app url (with trailing slash, without file name at tne end)
 */
if (!defined("BASE_URL"))
    define("BASE_URL", "https://example.com/jar/");

/**
 * Admin email, to send messages to.
 */
if (!defined("ADMIN_EMAIL"))
    define('ADMIN_EMAIL', 'you@example.com');

/**
 * The email the site should use in the from field.
 */
if (!defined("FROM_EMAIL"))
    define('FROM_EMAIL', 'Juridinių asmenų paieška <jar@example.com>');

/**
 * If you use your own proxies to scrape data, place the key you use here.
 */
if (!defined("PROXY_API_KEY"))
    define('PROXY_API_KEY', 'SomeVerySecretKEy');

/**
 * SQLite3 ICU extension binary path, if it is available
 */
if (!defined("SQLITE_ICU_EXT"))
    define('SQLITE_ICU_EXT', '');

/**
 * SQLite3 PCRE extension binary path, if it is available
 */
if (!defined("SQLITE_PCRE_EXT"))
    define('SQLITE_PCRE_EXT', '');

/**
 * Define here all proxies that you will use to scrape data from the website of the center of registers.
 * You have one on this project itself by default
 */

// Check if the SCRAP_PROXIES array is defined in the global scope
if (isset($GLOBALS['SCRAP_PROXIES']) && is_array($GLOBALS['SCRAP_PROXIES'])) {
    // Replace placeholders with actual values
    array_walk_recursive($GLOBALS['SCRAP_PROXIES'], function (&$item) {
        $item = str_replace('BASE_URL', getenv('BASE_URL'), $item);
        $item = str_replace('PROXY_API_KEY', getenv('PROXY_API_KEY'), $item);
    });

    // Define SCRAP_PROXIES constant with the constructed array
    define('SCRAP_PROXIES', $GLOBALS['SCRAP_PROXIES']);
} else {
    define('SCRAP_PROXIES', array(
        'proxy_here' => getenv('BASE_URL') . 'data/getfile.php?apikey=' . getenv('PROXY_API_KEY') . '&url='
    ));
}

/**
 * Emails exempt from subscriber limitations, comma separated list (spaces will be trimmed)
 */
if (!defined("SUBSCRIPTIONS_UNLIMITED"))
    define("SUBSCRIPTIONS_UNLIMITED", "");

if (!defined("SUBSCRIPTION_LIMIT"))
    define("SUBSCRIPTION_LIMIT", "10");

if (!defined("SALT"))
    define("SALT", "SDTDRTGDaetr");

/***********************************************************************
 *** Beyond this point you will probably not have to change anything ***
 ***********************************************************************/

/**
 * Base directory same as the directory of this script.
 */
if (!defined("BASE_DIR"))
    define("BASE_DIR", __DIR__ . '/');
if (!defined("RC_WEB"))
    define('RC_WEB', 'https://www.registrucentras.lt/');
if (!defined("SCRAP_URL"))
    define('SCRAP_URL', 'jar/p/dok.php?kod=');
if (!defined("JOURNAL_LIST_URL"))
    define('JOURNAL_LIST_URL', 'jar/infleid/publications.do');
if (!defined("JOURNAL_DOWNLOAD_URL"))
    define('JOURNAL_DOWNLOAD_URL', 'jar/infleid/download.do?oid=');
if (!defined("OPEN_DATA_PERSONS_URL"))
    define('OPEN_DATA_PERSONS_URL', 'aduomenys/?byla=JAR_IREGISTRUOTI.csv');
if (!defined("OPEN_DATA_PERSONS_UNREG_URL"))
    define('OPEN_DATA_PERSONS_UNREG_URL', 'aduomenys/?byla=JAR_ISREGISTRUOTI.csv');
if (!defined("OPEN_DATA_FORMS_URL"))
    define('OPEN_DATA_FORMS_URL', 'aduomenys/?byla=JAR_TEI_FORM_KLASIFIKATORIUS.csv');
if (!defined("OPEN_DATA_STATUSES_URL"))
    define('OPEN_DATA_STATUSES_URL', 'aduomenys/?byla=JAR_TEI_STATUSU_KLASIFIKATORIUS.csv');
if (!defined("OPEN_DATA_INDIVIDUAL_URL"))
    define('OPEN_DATA_INDIVIDUAL_URL', 'https://pr.lapas.info/jar/data/scrapit.php?download=individual');

/**
 * Name and place of the database file.
 */
if (!defined("DBFILE"))
    define('DBFILE', 'writable/jar.db');

/**
 * List of often used codes
 */
// damn English... individualios įmonės, komanditinės ūkinės bendrijos ir tikrosios ūkinės bendrijos, jų filialai
if (!defined("CODES_WITH_HIDDEN_NAMES"))
    define('CODES_WITH_HIDDEN_NAMES', '210, 211, 212, 220, 221, 222, 810, 811, 812');
// išregistruotų statuso numeris - nebenaudojamas nuo 2025-04-01
if (!defined("DISREG_STATUS_CODE"))
    define('DISREG_STATUS_CODE', '10');

/**
 * Default timestamp to use in the db.
 */
// TODO: rewrite in code to use TIMEFORMAT instead of TIMESTAMP
if (defined("TIMEFORMAT"))
    define('TIMESTAMP', date(TIMEFORMAT));
else
    define('TIMESTAMP', date("Y-m-d H:i:s"));

/**
 * Name of the API file.
 */
if (!defined("API_FILE"))
    define("API_FILE", "api.php");

/**
 * Default time zone and locale (locale is important for data sorting functions of the database).
 */
date_default_timezone_set('Europe/Vilnius');
setlocale(LC_ALL, 'lt_LT.UTF-8');

// echo '<pre>';
// var_dump(SCRAP_PROXIES);exit;