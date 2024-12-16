<?php

/**
 * this file is usually run by cron, but could be run with command line arguments too
 * 
 * when run without parameters in command line it gets the latest journal of RC, extracts its info and updates the database.
 * 
 * possible parameters when running from command line:
 * `update` - perform the daily update of the main database by the scrapped names of individual enterprises
 * 
 * possible GET parameters: 
 * key=adasdšasdš - key for access via get method
 * sendemail= - send email with results
 * debug= - echo data to the browser
 */

ini_set('max_execution_time', '100');
require_once(__DIR__ . '/../config.php');
require_once(BASE_DIR . 'data/data-functions.php');
require_once(BASE_DIR . 'common/classes.php');
require_once(BASE_DIR . 'common/functions.php');


if (isset($_GET['key']) && $_GET['key'] === PROXY_API_KEY) {
    // do nothing
} elseif (!isset($_SERVER['REQUEST_METHOD'])) {
    // do nothing
} else {
    echo 'Something fishy!';
    exit;
}

if (!file_exists(DBFILE)) {
    require_once(BASE_DIR . 'data/initialize-db.php');
}



$message = '';
$subject = 'scrapjournal.php išvestis';

if (!isset($db)) {
    $db = new mySQLite3(BASE_DIR . DBFILE);
}

$databaseCheckResult = $db->checkAndReindex();

// echo 'Database opened' . PHP_EOL;exit;

// // test

// $entity = [
//     "ja_pavadinimas" => "VšĮ SAVĘS PAŽINIMO IR SAVIRAIŠKOS STUDIJA",
//     "ja_kodas" => 303011485,
//     "form_kodas" => 570,
//     "stat_kodas" => 0,
//     "stat_data_nuo" => "2024-09-15",
//     "formavimo_data" => "2024-09-15"
// ];

// // Prepare the update query
// $query_str = 'UPDATE persons SET 
//     ja_pavadinimas = :ja_pavadinimas, 
//     form_kodas = :form_kodas, 
//     stat_kodas = :stat_kodas, 
//     stat_data_nuo = :stat_data_nuo, 
//     formavimo_data = :formavimo_data 
//     WHERE ja_kodas = :ja_kodas';

// // Prepare the statement
// $query = $db->prepare($query_str);

// // Bind values
// $query->bindValue(':ja_pavadinimas', $entity['ja_pavadinimas'], SQLITE3_TEXT);
// $query->bindValue(':form_kodas', $entity['form_kodas'], SQLITE3_INTEGER);
// $query->bindValue(':stat_kodas', $entity['stat_kodas'], SQLITE3_INTEGER);
// $query->bindValue(':stat_data_nuo', $entity['stat_data_nuo'], SQLITE3_TEXT);
// $query->bindValue(':formavimo_data', $entity['formavimo_data'], SQLITE3_TEXT);
// $query->bindValue(':ja_kodas', $entity['ja_kodas'], SQLITE3_INTEGER);

// // Execute the query
// $result = $query->execute();

// // Check if the update was successful
// if ($result) {
//     echo "Update successful.";
// } else {
//     echo "Update failed.";
// }

// exit;

// // test end

/**
 * statistics gathering and update of persons table block
 */

$sendEmail = ((isset($argv[1]) && in_array('sendemail', $argv)) || isset($_GET['sendemail']));

// first argument should be journal, second - the number of journal
if (isset($argv[2]) && in_array('journal', $argv) || isset($_GET['journal'])) {
    $pdf_url = "https://www.registrucentras.lt/jar/infleid/download.do?oid=" . ($argv[2] ?? $_GET['journal']);
} else {

    $url = 'https://www.registrucentras.lt/jar/infleid/publications.do';
    $webpage = file_get_contents($url);

    if ($webpage === false) {
        die("Failed to fetch the page.");
    }

    // Find the first occurrence of the download link using regex
    if (preg_match('/<a href="download\.do\?oid=(\d+)" target="_blank">/', $webpage, $matches)) {
        $oid = $matches[1];
        $pdf_url = "https://www.registrucentras.lt/jar/infleid/download.do?oid=$oid";
    } else {
        die("Failed to find the download link.");
    }
}

// Download the PDF
$pdf_content = file_get_contents($pdf_url);
if ($pdf_content === false) {
    die("Failed to download the PDF.");
}

// Save the PDF as content.pdf
$file_path = BASE_DIR .  'writable/content.pdf';
file_put_contents($file_path, $pdf_content);

// Convert PDF to HTML using pdftohtml (command-line tool)
$output = [];
$directory = BASE_DIR . 'writable/';
$command = 'pdftohtml -nodrm ' . escapeshellarg($file_path);
$full_command = 'cd ' . escapeshellarg($directory) . ' && ' . $command;
exec($full_command, $output, $return_var);

// input html
$html = file_get_contents(BASE_DIR . 'writable/contents.html');

// $pattern = 'writable\/content*';

// // Use glob to find files matching the pattern
// $files = glob($pattern);

// // Loop through the files and delete each one
// foreach ($files as $file) {
//     if (is_file($file)) {
//         @unlink($file);
//     }
// }

// Parse the HTML and get the entities
$entities = parseCRJournal($html);

// get data for updating the data retrieved
$query = $db->query("SELECT * FROM statuses");
$revStatuses = [];
while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
    $revStatuses[$row['stat_pavadinimas']] = $row['stat_kodas'];
}
$query = $db->query("SELECT * FROM forms");
$revForms = [];
while ($row = $query->fetchArray(SQLITE3_ASSOC)) {
    $revForms[$row['form_pav_ilgas']] = $row['form_kodas'];
}

$defective = [];
$registered = [];
$unregistered = [];
$updated = [];
$statuses_list = [];
$individual = 0;
$individual_fail = 0;

foreach ($entities as $entity) {

    if (!isset($revForms[$entity['forma']]) || !isset($revStatuses[$entity['statusas']])) {
        $entity['problem'] = 'missing form or status';
        $defective[] = $entity;
        continue;
    }

    $query = $db->prepare('SELECT * FROM persons WHERE ja_kodas = :ja_kodas LIMIT 1');
    $query->bindValue(':ja_kodas', $entity['ja_kodas'], SQLITE3_INTEGER);

    // Execute and fetch the result
    $result = $query->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);

    if (isset($entity['pakeit_data'])) {
        $case = 'update';

        $entity['form_kodas'] = $revForms[$entity['forma']];
        $entity['stat_kodas'] = $revStatuses[$entity['statusas']];

        if ($revStatuses[$entity['statusas']] !== $row['stat_kodas']) {
            $entity['stat_data_nuo'] = $entity['pakeit_data'];
        }
        $entity['formavimo_data'] = $entity['pakeit_data'];

        // unset($entity['statusas'], $entity['pakeit_data'], $entity['forma']);
    } elseif (isset($entity['isreg_data'])) {
        $case = 'unregister';
        $entity['stat_data_nuo'] = $entity['isreg_data'];
        $entity['stat_kodas'] = $revStatuses[$entity['statusas']];
        $entity['formavimo_data'] = $entity['isreg_data'];
        $entity['stat_data_nuo'] = $entity['isreg_data'];
    } elseif (isset($row['ja_kodas'])) {
        $case = 'exists'; // while it should not
    } else {
        $case = 'register';
    }

    if ($case === 'register') {

        // Prepare the insert query
        $query = $db->prepare('
            INSERT INTO persons (ja_kodas, ja_pavadinimas, adresas, form_kodas, stat_kodas, stat_data_nuo, ja_reg_data, formavimo_data) 
            VALUES (:ja_kodas, :ja_pavadinimas, :adresas, :form_kodas, :stat_kodas, :stat_data_nuo, :ja_reg_data, :formavimo_data)
        ');


        // Bind values to the placeholders
        $query->bindValue(':ja_kodas', $entity['ja_kodas'], SQLITE3_INTEGER);
        $query->bindValue(':ja_pavadinimas', $entity['ja_pavadinimas'], SQLITE3_TEXT);
        $query->bindValue(':adresas', $entity['adresas'], SQLITE3_TEXT);
        $query->bindValue(':form_kodas', $revForms[$entity['forma']], SQLITE3_INTEGER);
        $query->bindValue(':stat_kodas', $revStatuses[$entity['statusas']], SQLITE3_INTEGER);
        $query->bindValue(':stat_data_nuo', $entity['ja_reg_data'], SQLITE3_TEXT);
        $query->bindValue(':ja_reg_data', $entity['ja_reg_data'], SQLITE3_TEXT);
        $query->bindValue(':formavimo_data', $entity['ja_reg_data'], SQLITE3_TEXT);

        // Execute the insert query
        $result = $query->execute();

        if ($result) {
            $registered[] = $entity;
        } else {
            $entity['problem'] = 'unsuccessful update';
            $defective[] = $entity;
        }
    } elseif($case === 'exists') {

        $entity['problem'] = 'entry exists but was marked for registration';
        $defective[] = $entity;
    } else {

        // Start building the query dynamically
        $set_clauses = [];
        $bind_values = [];

        // Add fields to query only if they have a value
        if (!empty($entity['ja_pavadinimas'])) {
            $set_clauses[] = 'ja_pavadinimas = :ja_pavadinimas';
            $bind_values[':ja_pavadinimas'] = $entity['ja_pavadinimas'];
        }
        if (!empty($entity['adresas'])) {
            $set_clauses[] = 'adresas = :adresas';
            $bind_values[':adresas'] = $entity['adresas'];
        }
        if (!empty($entity['form_kodas'])) {
            $set_clauses[] = 'form_kodas = :form_kodas';
            $bind_values[':form_kodas'] = $entity['form_kodas'];
        }
        if (isset($entity['stat_kodas'])) {
            $set_clauses[] = 'stat_kodas = :stat_kodas';
            $bind_values[':stat_kodas'] = $entity['stat_kodas'];
        }
        if (!empty($entity['stat_data_nuo'])) {
            $set_clauses[] = 'stat_data_nuo = :stat_data_nuo';
            $bind_values[':stat_data_nuo'] = $entity['stat_data_nuo'];
        }
        if (!empty($entity['formavimo_data'])) {
            $set_clauses[] = 'formavimo_data = :formavimo_data';
            $bind_values[':formavimo_data'] = $entity['formavimo_data'];
        }
        if (!empty($entity['isreg_data'])) {
            $set_clauses[] = 'isreg_data = :isreg_data';
            $bind_values[':isreg_data'] = $entity['isreg_data'];
        }
        if (!empty($entity['ja_kodas'])) {
            $set_clauses[] = 'ja_kodas = :ja_kodas';
            $bind_values[':ja_kodas'] = $entity['ja_kodas'];
        }

        // Build the final query string
        $query_str = 'UPDATE persons SET ' . implode(', ', $set_clauses) . ' WHERE ja_kodas = :ja_kodas';

        // Prepare the query
        $query = $db->prepare($query_str);

        // Bind the ja_kodas (it's mandatory)
        $query->bindValue(':ja_kodas', $entity['ja_kodas'], SQLITE3_INTEGER);

        foreach ($bind_values as $placeholder => $value) {
            if (is_int($value)) {
                $query->bindValue($placeholder, $value, SQLITE3_INTEGER);
            } else {
                $query->bindValue($placeholder, $value, SQLITE3_TEXT);
            }
        }

        // Execute the query
        $result = $query->execute();

        if ($result && $case === 'update') {
            $updated[] = $entity;
        } elseif ($result && $case === 'unregister') {
            $unregistered[] = $entity;
        } else {
            $entity['problem'] = 'Database inserting problem';
            $defective[] = $entity;
        }


        // now enter into individual table
        if (in_array((int) $revForms[$entity['forma']], [CODES_WITH_HIDDEN_NAMES])) {

            // Prepare the insert query
            if ($case === 'register') {

                $query = $db->prepare('
                INSERT INTO individual (ja_kodas, ja_pavadinimas, tikr_statusas, tikr_data) 
                VALUES (:ja_kodas, :ja_pavadinimas, :tikr_statusas, :tikr_data)
                ');
            } else {
                $query = $db->prepare('UPDATE individual SET ja_pavadinimas = :ja_pavadinimas, tikr_statusas = :tikr_statusas, tikr_data = :tikr_data WHERE ja_kodas = :ja_kodas');
            }
            $query->bindValue(':ja_kodas',
                $entity['ja_kodas'],
                SQLITE3_INTEGER
            );
            $query->bindValue(
                ':ja_pavadinimas',
                $entity['ja_pavadinimas'],
                SQLITE3_TEXT
            );
            $query->bindValue(
                ':tikr_statusas',
                'Success',
                SQLITE3_TEXT
            );
            //var_dump($entity);
            $query->bindValue(':tikr_data',
                ($entity['ja_reg_data'] ?? ($entity['pakeit_data'] ?? $entity['isreg_data'])),
                SQLITE3_TEXT
            );
            // Execute the insert query
            $result = $query->execute();

            if ($result) {
                $individual++;
            } else {
                $individual_fail++;
            }
        }

    }
}
//echo 'statuses_list: ' . PHP_EOL;
//var_dump($statuses_list);

$databaseCheckResult2 = $db->checkAndReindex();

if ((isset($argv[1]) && in_array('report', $argv)) || isset($_GET['report'])) {

    if (isset($_GET['report'])) echo "<pre>";

    $message .= "Viso įrašų žurnale: " . count($entities) . "\r\n";
    $message .= "Su klaidom, praleista:" . count($defective) . "\r\n";
    $message .= "Įregistruota:" . count($registered) . "\r\n";
    $message .= "Išregistruota:" . count($unregistered) . "\r\n";
    $message .= "Atnaujinta:" . count($updated) . "\r\n";
    $message .= "Individualių įmonių/komanditinių/tikrųjų ūkinių bendrovių: " . $individual . ' (nepavyko: ' . $individual_fail . ")\r\n";
    $message .= "DB būklė; prieš atnaujinimą: " . ($databaseCheckResult ? "ok" : "ne ok") . "; po atnaujinimo: "  
        . ($databaseCheckResult2 ? "ok" : "ne ok") . ".\r\n";
    $message .= "\n=======================\n\n";

    //var_dump($defective);

    echo $subject . PHP_EOL;
    echo $message;
    if ($sendEmail)
        emailAdmin($subject, $message);
    exit;
}

$db->close();
