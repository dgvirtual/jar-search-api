<?php
if (count(get_included_files()) == 1) die('This file is not meant to be accessed directly.');

require_once('common/functions.php');

/**
 * Main settings file
 * This file should be renamed to config.php
 */

/**
 * Change to the subdir(s) of your project, leave empty if the app is at the website root.
 * So, empty if your index file is https://example.com/index.php, and "jar" if it is 
 * at https://example.com/jar/index.php
 */
define("SUBDIR", "jar");

/**
 * Full app url (with trailing slash, without file name at tne end)
 */ 
define("BASE_URL", "https://example.com/jar/");

/**
 * Admin email, to send messages to.
 */
define('ADMIN_EMAIL', 'you@example.com');

/**
 * The email the site should use in the from field.
 */
define('FROM_EMAIL', 'Juridinių asmenų paieška <jar@example.com>');

/**
 * If you use your own proxies to scrape data, place the key you use here.
 */
define('PROXY_API_KEY', 'SomeVerySecretKEy');

/**
 * Define here all proxies that you will use to scrape data before you
 * run the app for the first time; the entries will be imported to the db.
 * If you add proxies later, add them directly to the db.
 * File data/getfile.php can be used as a proxy on other hosts 
 * (PROXY_API_KEY has to be written in it manually).
 */
define('SCRAP_PROXIES', array(
    'proxy_here' => BASE_URL . '/data/getfile.php?apikey=' . PROXY_API_KEY . '&url=',
    //'proxy_other' => 'https://example.com/getfile.php?apikey=' . PROXY_API_KEY . '&url='
));

/***********************************************************************
 *** Beyond this point you will probably not have to change anything ***
 ***********************************************************************/

/**
 * Base directory same as the directory of this script.
 */
define("BASE_DIR", __DIR__ . '/');

define('RC_WEB', 'https://www.registrucentras.lt/');
define('SCRAP_URL', 'jar/p/dok.php?kod=');
define('OPEN_DATA_PERSONS_URL', 'aduomenys/?byla=JAR_IREGISTRUOTI.csv');
define('OPEN_DATA_PERSONS_UNREG_URL', 'aduomenys/?byla=JAR_ISREGISTRUOTI.csv');
define('OPEN_DATA_FORMS_URL', 'aduomenys/?byla=JAR_TEI_FORM_KLASIFIKATORIUS.csv');
define('OPEN_DATA_STATUSES_URL', 'aduomenys/?byla=JAR_TEI_STATUSU_KLASIFIKATORIUS.csv');
define('OPEN_DATA_INDIVIDUAL_URL', 'https://pr.lapas.info/jar/data/scrapit.php?download=individual');

/**
 * Name and place of the database file.
 */
define('DBFILE', BASE_DIR . 'writable/jar.db');

/**
 * Default timestamp to use in the db.
 */
define('TIMESTAMP', date("Y-m-d H:i:s"));

/**
 * Name of the API file.
 */
define("API_FILE", "api.php");

/**
 * Default time zone and locale (locale is important for data sorting functions of the database).
 */
date_default_timezone_set('Europe/Vilnius');
setlocale(LC_ALL, 'lt_LT.UTF-8');
