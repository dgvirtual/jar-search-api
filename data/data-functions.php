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

function extractJournalNumber($string) {
    // Use a regular expression to match the journal number pattern
    if (preg_match('/Nr\.(\d{4}-\d{3})<br\/>/', $string, $matches)) {
        return $matches[1];
    }
    return null; // Return null if no match is found
}

function parseAllJournal(string $webpage): array
{

    $journalNumber = extractJournalNumber($webpage);

    $markers = [
        [
            'start' => '<b>1.1 Atskyrimas</b>',
            'end' => '<b>1.2 Dalyvių susirinkimo sušaukimas</b>',
            'title' => '1. Juridinių asmenų vieši pranešimai: 1.1 Atskyrimas'
        ],
        [
            'start' => '<b>1.2 Dalyvių susirinkimo sušaukimas</b>',
            'end' => '<b>1.3 Europos bendrovės, Europos kooperatinės bendrovės buveinės perkėlimas</b>',
            'title' => '1. Juridinių asmenų vieši pranešimai: 1.2 Dalyvių susirinkimo sušaukimas'
        ],
        [
            'start' => '<b>1.3 Europos bendrovės, Europos kooperatinės bendrovės buveinės perkėlimas</b>',
            'end' => '<b>1.4 Kapitalo mažinimas</b>',
            'title' => '1. Juridinių asmenų vieši pranešimai: 1.3 Europos bendrovės, Europos kooperatinės bendrovės buveinės perkėlimas'
        ],
        [
            'start' => '<b>1.4 Kapitalo mažinimas</b>',
            'end' => '<b>1.5 Likvidavimas</b>',
            'title' => '1. Juridinių asmenų vieši pranešimai: 1.4 Kapitalo mažinimas'
        ],
        [
            'start' => '<b>1.5 Likvidavimas</b>',
            'end' => '<b>1.6 Materialiųjų akcijų ar akcijų sertifikatų negrąžinimas, sunaikinimas, praradimas</b>',
            'title' => '1. Juridinių asmenų vieši pranešimai: 1.5 Likvidavimas'
        ],
        [
            'start' => '<b>1.6 Materialiųjų akcijų ar akcijų sertifikatų negrąžinimas, sunaikinimas, praradimas</b>',
            'end' => '<b>1.7 Pagal Vertybinių popierių įstatymo nustatytus reikalavimus skelbiami vieši pranešimai</b>',
            'title' => '1. Juridinių asmenų vieši pranešimai: 1.6 Materialiųjų akcijų ar akcijų sertifikatų negrąžinimas, sunaikinimas, praradimas'
        ],
        [
            'start' => '<b>1.7 Pagal Vertybinių popierių įstatymo nustatytus reikalavimus skelbiami vieši pranešimai</b>',
            'end' => '<b>1.8 Pasiūlymas pirmumo teise įsigyti akcijų, konvertuojamųjų obligacijų</b>',
            'title' => '1. Juridinių asmenų vieši pranešimai: 1.7 Pagal Vertybinių popierių įstatymo nustatytus reikalavimus skelbiami vieši pranešimai'
        ],
        [
            'start' => '<b>1.8 Pasiūlymas pirmumo teise įsigyti akcijų, konvertuojamųjų obligacijų</b>',
            'end' => '<b>1.9 Pavadinimo keitimas</b>',
            'title' => '1. Juridinių asmenų vieši pranešimai: 1.8 Pasiūlymas pirmumo teise įsigyti akcijų, konvertuojamųjų obligacijų'
        ],
        [
            'start' => '<b>1.9 Pavadinimo keitimas</b>',
            'end' => '<b>1.10 Pertvarkymas</b>',
            'title' => '1. Juridinių asmenų vieši pranešimai: 1.9 Pavadinimo keitimas'
        ],
        [
            'start' => '<b>1.10 Pertvarkymas</b>',
            'end' => '<b>1.11 Reorganizavimas (jungimas, skaidymas)</b>',
            'title' => '1. Juridinių asmenų vieši pranešimai: 1.10 Pertvarkymas'
        ],
        [
            'start' => '<b>1.11 Reorganizavimas (jungimas, skaidymas)</b>',
            'end' => '<b>1.12 Užsienio juridinio asmens filialų ir atstovybių viešas pranešimas</b>',
            'title' => '1. Juridinių asmenų vieši pranešimai: 1.11 Reorganizavimas (jungimas, skaidymas)'
        ],
        [
            'start' => '<b>1.12 Užsienio juridinio asmens filialų ir atstovybių viešas pranešimas</b>',
            'end' => '<b>1.13 Valdymo (holdingo) Europos bendrovės steigimo sąlygų projekto paskelbimas</b>',
            'title' => '1. Juridinių asmenų vieši pranešimai: 1.12 Užsienio juridinio asmens filialų ir atstovybių viešas pranešimas'
        ],
        [
            'start' => '<b>1.13 Valdymo (holdingo) Europos bendrovės steigimo sąlygų projekto paskelbimas</b>',
            'end' => '<b>1.14 Juridinio asmens pranešimas apie kolegialaus organo nario atranką</b>',
            'title' => '1. Juridinių asmenų vieši pranešimai: 1.13 Valdymo (holdingo) Europos bendrovės steigimo sąlygų projekto paskelbimas'
        ],
        [
            'start' => '<b>1.14 Juridinio asmens pranešimas apie kolegialaus organo nario atranką</b>',
            'end' => '<b>1.15 Informacija apie obligacijų savininkų patikėtinį ir sutarties dėl obligacijų savininkų interesų</b>', // nukirpta
            'title' => '1. Juridinių asmenų vieši pranešimai: 1.14 Juridinio asmens pranešimas apie kolegialaus organo nario atranką'
        ],
        [
            'start' => '<b>1.15 Informacija apie obligacijų savininkų patikėtinį ir sutarties dėl obligacijų savininkų interesų</b>', // nukirpta
            'end' => '<b>1.16 Obligacijų savininkų susirinkimo sušaukimas</b>',
            'title' => '1. Juridinių asmenų vieši pranešimai: 1.15 Informacija apie obligacijų savininkų patikėtinį ir sutarties dėl obligacijų savininkų interesų gynimo sudarymą'
        ],
        [
            'start' => '<b>1.16 Obligacijų savininkų susirinkimo sušaukimas</b>',
            'end' => '<b>1.17 Kiti pranešimai</b>',
            'title' => '1. Juridinių asmenų vieši pranešimai: 1.16 Obligacijų savininkų susirinkimo sušaukimas'
        ],
        [
            'start' => '<b>1.17 Kiti pranešimai</b>',
            'end' => '<b>2.1 Pranešimai apie numatomą buveinės išregistravimą patalpų savininko prašymu</b>',
            'title' => '1. Juridinių asmenų vieši pranešimai: 1.17 Kiti pranešimai'
        ],
        [
            'start' => '<b>2.1 Pranešimai apie numatomą buveinės išregistravimą patalpų savininko prašymu</b>',
            'end' => '<b>2.2 Pranešimai apie pasiūlymą įsigyti bendrovės akcijų, pasinaudojant pirmumo teise</b>',
            'title' => '2. Registro tvarkytojo pranešimai: 2.1 Pranešimai apie numatomą buveinės išregistravimą patalpų savininko prašymu'
        ],
        [
            'start' => '<b>2.2 Pranešimai apie pasiūlymą įsigyti bendrovės akcijų, pasinaudojant pirmumo teise</b>',
            'end' => '<b>2.3 Pranešimai apie pasiūlymą įsigyti bendrovės konvertuojamųjų obligacijų, pasinaudojant</b>',
            'title' => '2. Registro tvarkytojo pranešimai: 2.2 Pranešimai apie pasiūlymą įsigyti bendrovės akcijų, pasinaudojant pirmumo teise'
        ],
        [
            'start' => '<b>2.3 Pranešimai apie pasiūlymą įsigyti bendrovės konvertuojamųjų obligacijų, pasinaudojant</b>',
            'end' => '<b>2.4 Pranešimai apie sprendimą sumažinti įstatinį kapitalą</b>',
            'title' => '2. Registro tvarkytojo pranešimai: 2.3 Pranešimai apie pasiūlymą įsigyti bendrovės konvertuojamųjų obligacijų, pasinaudojant pirmumo teise'
        ],
        [
            'start' => '<b>2.4 Pranešimai apie sprendimą sumažinti įstatinį kapitalą</b>',
            'end' => '<b>3.1 Įregistruoti juridiniai asmenys</b>',
            'title' => '2. Registro tvarkytojo pranešimai: 2.4 Pranešimai apie sprendimą sumažinti įstatinį kapitalą'
        ],
        [
            'start' => '<b>3.1 Įregistruoti juridiniai asmenys</b>',
            'end' => '<b>3.2 Išregistruoti juridiniai asmenys</b>',
            'title' => '3. Registro tvarkytojo skelbimai: 3.1 Įregistruoti juridiniai asmenys'
        ],
        [
            'start' => '<b>3.2 Išregistruoti juridiniai asmenys</b>',
            'end' => '<b>3.3 Įregistruoti juridinių asmenų duomenų ar informacijos pakeitimai</b>',
            'title' => '3. Registro tvarkytojo skelbimai: 3.2 Išregistruoti juridiniai asmenys'
        ],
        [
            'start' => '<b>3.3 Įregistruoti juridinių asmenų duomenų ar informacijos pakeitimai</b>',
            'end' => '<b>4.1 Pranešimai apie numatomą inicijuoti juridinio asmens likvidavimą</b>',
            'title' => '3. Registro tvarkytojo skelbimai: 3.3 Įregistruoti juridinių asmenų duomenų ar informacijos pakeitimai'
        ],
        [
            'start' => '<b>4.1 Pranešimai apie numatomą inicijuoti juridinio asmens likvidavimą</b>',
            'end' => '<b>4.2 Skelbimai apie juridinio asmens teisinio statuso &#34;Inicijuojamas likvidavimas&#34; įregistravimą</b>',
            'title' => '4. Registro tvarkytojo skelbimai apie juridinio asmens likvidavimą Registro tvarkytojo iniciatyva: 4.1 Pranešimai apie numatomą inicijuoti juridinio asmens likvidavimą'
        ],
        [
            'start' => '<b>4.2 Skelbimai apie juridinio asmens teisinio statuso &#34;Inicijuojamas likvidavimas&#34; įregistravimą</b>',
            'end' => '<b>4.3 Skelbimai apie juridinio asmens teisinio statuso &#34;Likviduojamas&#34; įregistravimą</b>',
            'title' => '4. Registro tvarkytojo skelbimai apie juridinio asmens likvidavimą Registro tvarkytojo iniciatyva: 4.2 Skelbimai apie juridinio asmens teisinio statuso "Inicijuojamas likvidavimas" įregistravimą'
        ],
        [
            'start' => '<b>4.3 Skelbimai apie juridinio asmens teisinio statuso &#34;Likviduojamas&#34; įregistravimą</b>',
            'end' => '<b>4.4 Skelbimai apie išregistruotus juridinius asmenis</b>',
            'title' => '4. Registro tvarkytojo skelbimai apie juridinio asmens likvidavimą Registro tvarkytojo iniciatyva: 4.3 Skelbimai apie juridinio asmens teisinio statuso "Likviduojamas" įregistravimą'
        ],
        [
            'start' => '<b>4.4 Skelbimai apie išregistruotus juridinius asmenis</b>',
            'end' => '<h1>Document Outline</h1>',
            'title' => '4. Registro tvarkytojo skelbimai apie juridinio asmens likvidavimą Registro tvarkytojo iniciatyva: 4.4 Skelbimai apie išregistruotus juridinius asmenis'
        ]
    ];

    $content = [];

    foreach ($markers as $marker) {

        // Step 1: Find the start and end positions
        $start_pos = strpos($webpage, $marker['start']);
        $end_pos = strpos($webpage, $marker['end']);

        if ($start_pos === false || $end_pos === false) {
            d($marker['end']);
            die("Couldn't find the specified section in the content.");
        }

        // Adjust the start position to the end of the start marker
        $start_pos += strlen($marker['start']);

        // Step 2: Extract the content between the markers
        $content[] = [
            'title' => $marker['title'],
            'html' => substr($webpage, $start_pos, $end_pos - $start_pos),
        ];
    }

    // Split by blocks representing each company using the <hr/> tag
    //$blocks = explode('- - -<br/>', $html);
    $pattern = '/- - -<br\/>|<b>3\.2 Išregistruoti juridiniai asmenys<\/b><br\/>|<b>3\.3 Įregistruoti juridinių asmenų duomenų ar informacijos pakeitimai<\/b><br\/>/';

    // Split the document based on the pattern
    $entities = [];

    foreach ($content as $item) {
        $blocks = preg_split($pattern, $item['html']);

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

            // Only add entity if it has a title and code
            if (!empty($entity['ja_pavadinimas']) && !empty($entity['ja_kodas'])) {
                $entity['section'] = $item['title'];
                $entity['content'] = $block;
                $entity['journal_no'] = $journalNumber;
                $entities[] = $entity;
            }
        }
    }

    return $entities;
}
