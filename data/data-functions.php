<?php

if (count(get_included_files()) == 1) {
    die('This file is not meant to be accessed directly.');
}

function retrieveInfo($content, int $ja_kodas)
{
    // Search for specific strings
    $juridinisAsmuoFound = strpos($content, "Juridinis asmuo:");
    $rezultatuNerastaFound = strpos($content, "Rezultatų nerasta");
    $limitoVirsytasFound = strpos($content, "Jūs viršijote leistiną dienos limitą");

    $person['ja_kodas'] = $ja_kodas;
    // Return the appropriate result
    if ($juridinisAsmuoFound !== false) {

        // Extract text between <b> tags after "Juridinis asmuo:"
        $startIndex = strpos($content, "<b>", $juridinisAsmuoFound);
        $endIndex = strpos($content, "</b>", $startIndex);
        if ($startIndex !== false && $endIndex !== false) {

            $person['ja_pavadinimas'] = substr($content, $startIndex + 3, $endIndex - $startIndex - 3);
            $person['tikr_statusas'] = 'Success';
        } else {
            $person['tikr_statusas'] = 'Extraction error';
        }
    } elseif ($rezultatuNerastaFound !== false) {
        $person['tikr_statusas'] = 'Not found';
    } elseif ($limitoVirsytasFound !== false) {
        $person['tikr_statusas'] = 'Limit exceeded';
    } else {
        // perhaps it was the
        $person['tikr_statusas'] = 'Repeat';
    }

    return [
        'status' => $person['tikr_statusas'],
        'data' => $person
    ];
}

function getPageContent($url)
{

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt(
            $ch,
            CURLOPT_RETURNTRANSFER,
            true
        );
        curl_setopt(
            $ch,
            CURLOPT_HEADER,
            false
        );
        curl_setopt(
            $ch,
            CURLOPT_SSL_VERIFYHOST,
            0
        );
        curl_setopt(
            $ch,
            CURLOPT_SSL_VERIFYPEER,
            0
        );
        $response = curl_exec($ch);
        curl_close($ch);
    } else {
        echo $url;
        $response = file_get_contents($url);
    }
    return $response;
}

// Function to serve a CSV file as a download
function downloadCsv($file_path, $base_name)
{
    // Check if the file exists
    if (!file_exists($file_path)) {
        die("File not found.");
    }

    // Get the file modification date
    $file_date = date('Y-m-d', filemtime($file_path));

    // Construct the download name with the file date
    $download_name = $base_name . '_' . $file_date . '.csv';

    // Set the headers to force download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $download_name . '"');
    header('Content-Length: ' . filesize($file_path));

    // Read the file and output its content
    readfile($file_path);

    // Terminate the script to prevent any additional output
    exit();
}

function parseCRJournal(string $webpage): array
{
    // Define the start and end markers
    $start_marker = '<b>3.1 Įregistruoti juridiniai asmenys</b>';
    $end_marker = '<h1>Document Outline</h1>';

    // Step 1: Find the start and end positions
    $start_pos = strpos($webpage, $start_marker);
    $end_pos = strpos($webpage, $end_marker);

    if ($start_pos === false || $end_pos === false) {
        die("Couldn't find the specified section in the content.");
    }

    // Adjust the start position to the end of the start marker
    $start_pos += strlen($start_marker);

    // Step 2: Extract the content between the markers
    $html = substr($webpage, $start_pos, $end_pos - $start_pos);

    //var_dump($html);

    // Split by blocks representing each company using the <hr/> tag
    //$blocks = explode('- - -<br/>', $html);
    $pattern = '/- - -<br\/>|<b>3\.2 Išregistruoti juridiniai asmenys<\/b><br\/>|<b>3\.3 Įregistruoti juridinių asmenų duomenų ar informacijos pakeitimai<\/b><br\/>/';

    // Split the document based on the pattern
    $blocks = preg_split($pattern, $html);

    $entities = [];

    foreach ($blocks as $block) {
        $entity = [];
        $block = preg_replace('/^.*Išregistruoti juridiniai asmenys.*$/m', '', $block);
        $block = preg_replace('/^.*juridinių asmenų duomenų ar informacijos pakeitimai.*$/m', '', $block);
        // $block = preg_replace('/<br\/>\s*/', ' ', $block); // Remove br with \n
        // $block = preg_replace('<br\/>', ' ', $block); // Remove br
        $block = str_replace('&#160;', ' ', $block); // Convert HTML entity to space
        $block = preg_replace('/\s+/', ' ', $block); // Replace multiple spaces with a single space
        $block = str_replace(["\r\n", "\r", "\n"], "\n", $block);

        // Extract the title (company name)
        if (preg_match('/<b>(.*?)<\/b>/', $block, $matches)) {
            $entity['ja_pavadinimas'] = str_replace('&#34;', '"', strip_tags($matches[1]));
            //$entity['title'] = strip_tags($matches[1]);
        }

        // Extract the company code
        if (preg_match('/kodas(?:\s)*<b>(\d+)<\/b>/', $block, $matches)) {
            $entity['ja_kodas'] = (int) $matches[1];
        }

        // Extract the legal form
        if (preg_match('/Teisinė(?:\s)*forma:\s*([^.]*)\./', $block, $matches)) {
            $entity['forma'] = trim($matches[1]);
            $entity['forma'] = str_replace('  ', ' ', $entity['forma']);
        }

        // Extract the legal status
        if (preg_match('/Teisinis(?:\s)*statusas:\s*([^.]*)\./', $block, $matches)) {
            $entity['statusas'] = trim($matches[1]);
            $entity['statusas'] = str_replace('<br/>', '', $entity['statusas']);
            $entity['statusas'] = str_replace('  ', ' ', $entity['statusas']);
        }

        // Extract the address
        if (preg_match('/Buveinės(?:\s)*adresas:\s*([^<]*)/', $block, $matches)) {
            $entity['adresas'] = trim($matches[1]);
        }

        // Extract the change date
        if (preg_match('/\bPakeitimų(?:\s)*įregistravimo(?:\s)*data:(?:\s)*(\d{4}-\d{2}-\d{2})/u', $block, $matches)) {
            $entity['pakeit_data'] = $matches[1];
        }

        // Extract the register date
        if (preg_match('/\bĮregistravimo(?:\s)*data:(?:\s)*(\d{4}-\d{2}-\d{2})/u', $block, $matches)) {
            $entity['ja_reg_data'] = $matches[1];
        }

        // Extract the unregister date
        if (preg_match('/\bIšregistravimo(?:\s)*data:(?:\s)*(\d{4}-\d{2}-\d{2})/u', $block, $matches)) {
            $entity['isreg_data'] = $matches[1];
        }

        // $entities[] = $entity;

        // Only add entity if it has a title and code
        if (!empty($entity['ja_pavadinimas']) && !empty($entity['ja_kodas']) && (isset($entity['ja_reg_data']) || isset($entity['pakeit_data']) || isset($entity['isreg_data']))) {
            $entities[] = $entity;
        }
    }
    return $entities;
}

/**
 * Retrieves the journals list from the CR journal page,
 * from the newest one to the first one of the month, as array
 * with 'oid' as key and 'date' as value.
 *
 * @param [type] $html
 * @return array
 */
function getRCJournalEntries($html): array
{
    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $entries = [];
    $rows = $xpath->query('//tr');

    foreach ($rows as $row) {
        $dateNode = $xpath->query('.//td[1]', $row)->item(0);
        $journalNumberNode = $xpath->query('.//td[2]', $row)->item(0);
        $linkNode = $xpath->query('.//td[3]/a', $row)->item(0);

        if ($dateNode && $journalNumberNode && $linkNode) {
            $date = substr(trim($dateNode->nodeValue), 0, 10);
            $journalNumber = trim($journalNumberNode->nodeValue);
            $link = $linkNode->getAttribute('href');
            preg_match('/oid=(\d+)/', $link, $matches);
            $oid = $matches[1] ?? null;

            if ($oid) {
                $entries[$oid] = $date;
            }

            // Stop parsing if the date is the first day of the month
            if (substr($date, -2) == '01') {
                break;
            }
        }
    }

    return array_reverse($entries);
}
