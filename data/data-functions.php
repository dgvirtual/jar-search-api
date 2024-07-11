<?php if (count(get_included_files()) == 1) die('This file is not meant to be accessed directly.');

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
    } else if ($rezultatuNerastaFound !== false) {
        $person['tikr_statusas'] = 'Not found';
    } else if ($limitoVirsytasFound !== false) {
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
            TRUE
        );
        curl_setopt(
            $ch,
            CURLOPT_HEADER,
            FALSE
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
