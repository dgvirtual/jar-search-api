<?php if (count(get_included_files()) == 1) die('This file is not meant to be accessed directly.');

// Helper function to return JSON response for the API
function respond(
    int $status_code,
    string $status_message,
    array $data = [],
    ?string $queriesInfoString = null,
    ?array $recordCount = null,
    bool $cache = false
) {

    // Allow CORS for any domain
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");
    // Set JSON content type
    header("Content-Type: application/json");

    if ($cache) {
        // Set Cache-Control header to cache for 1 year (31536000 seconds)
        header('Cache-Control: public, max-age=31536000');

        // Set Expires header to 1 year from now
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

        // Generate a unique ETag for the response (optional, but recommended)
        $etag = md5(json_encode($data));
        header('ETag: ' . $etag);

        // Check if the ETag matches the client's copy to respond with 304 Not Modified
        if (
            isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag
        ) {
            header('HTTP/1.1 304 Not Modified');
            exit();
        }
    }

    http_response_code($status_code);
    $execution_time = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
    $response = [
        'status_code' => $status_code,
        'status_message' => $status_message,
        'execution_time' => round($execution_time, 4),
        'data' => $data
    ];
    if ($queriesInfoString) {
        $response['queries'] = $queriesInfoString;
    }

    if ($recordCount) {
        $response['count'] = $recordCount;
    }

    echo json_encode($response);
    exit;
}

/**
 * request parameter validation functions
 */
function isValidId($code = null)
{
    if (is_null($code) || empty($code)) return true;

    $code = substr($code, 0, 9);
    // Check if the code is exactly 9 digits long
    if (!preg_match('/^\d{9}$/', $code)) {
        return false;
    }

    // Convert code to an array of digits
    $digits = str_split($code);
    $sum = 0;

    // Calculate the control number
    for ($i = 0; $i < 8; $i++) {
        $sum += $digits[$i] * (1 + $i % 9);
    }

    // Calculate the remainder
    $remainder = $sum % 11;
    if ($remainder == 10) {
        $sum = 0;
        for ($i = 0; $i < 8; $i++) {
            $sum += $digits[$i] * (1 + ($i + 2) % 9);
        }
        $remainder = $sum % 11;
        if ($remainder == 10) {
            $remainder = 0;
        }
    }

    // Check if the last digit matches the remainder
    return $remainder == $digits[8];
}

function validateIds($ids)
{
    if (empty(trim($ids)))
        return true;

    return preg_match('/^[0-9,]+$/', $ids);
}

function validateIdsFirst($ids)
{
    if (empty(trim($ids)))
        return true;

    return strlen($ids) >= 9 && is_numeric(substr($ids, 0, 9));
}

function validateTextField($text)
{
    // Trim the input to remove leading and trailing whitespace
    $text = trim($text);

    // Return true if the text is empty after trimming
    if (empty($text)) {
        return true;
    }

    // Regular expression to match typical address characters including quotes
    return preg_match('/^[\p{L}\p{N}\s,\.\'"\-]+$/u', $text);
}

/**
 * Scrapping functions
 */

function scrapHtml(int $id, $date = null)
{
    $date = $date ?: date('Y-m-d');
    $success = false;

    // 1. Get web page content
    $url = RC_WEB . SCRAP_URL . $id;
    $html = file_get_contents($url);
    if ($html === false) {
        return ['success' => $success];
    }

    $warning_text = '<span class="text-danger fw-bold">Statistine prasme mažai tikėtina, bet įmanoma, kad duomenys yra pasikeitę per pastarąsias 24 valandas</span>.';
    $targetDivRegex = '/<div\s+align="right">Šiandien Jūsų atliktų užklausų skaičius:\s*(\d+)<\/div>/';
    if (preg_match('/Jūs viršijote leistiną dienos limitą/', $html, $matches)) {
        $remaining = 'Svetainės užklausų limitas RC sistemoje viršytas (200 užklausų). ' . $warning_text;
    } elseif (preg_match($targetDivRegex, $html, $matches)) {
        $number = intval($matches[1]);
        $remaining = 'Patikrinimų RC viešoje paieškoje šiandien: ' . $number . '/200 (liko ' . 200 - $number . ')';
        $success = true;
    } else {
        $remaining = 'Nepavyko gauti patikrinimo duomenų iš RC. ' . $warning_text;
    }

    // 3. Find the table with string "Dokumentas / aprašymas" in the first th
    $targetTableRegex = '/<table[^>]*>\s*<tr[^>]*>\s*<th[^>]*>\s*Dokumentas \/ aprašymas.*?<\/table>/s';
    if (preg_match($targetTableRegex, $html, $matches)) {
        $targetTableHTML = $matches[0];
    } else $targetTableHTML = '';

    // Remove 'nobr' tags
    $targetTableHTML = str_replace('<nobr>', '', $targetTableHTML);
    $targetTableHTML = str_replace('</nobr>', '', $targetTableHTML);



    // 4. Parse its contents and add each first and fourth column contents to an array
    $documentArray = [];
    $dateThreshold = $date; // Supplied variable
    if (!empty($targetTableHTML)) {
        $dom = new DOMDocument();
        // Specify the encoding explicitly when loading HTML content
        $dom->loadHTML('<?xml encoding="UTF-8">' . $targetTableHTML, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $rows = $dom->getElementsByTagName('tr');
        foreach ($rows as $row) {
            $cols = $row->getElementsByTagName('td');
            if ($cols->length >= 4) {
                $docDate = trim($cols->item(3)->textContent);
                if (strtotime($docDate) > strtotime($dateThreshold)) {
                    $documentArray[] = [
                        'naujas_dok' => trim($cols->item(0)->textContent),
                        'dok_reg_data' => $docDate
                    ];
                }
            }
        }
    }

    return ['new_events' => $documentArray, 'queries' => $remaining, 'success' => $success];
}


/**unfinished and unused */
function checkGetParam($param, $value = '', $operator = '=')
{
    if (!isset($_GET[$param])) {
        return null;
    } elseif (isset($_GET[$param]) && empty($value)) {
        return true;
    }

    $paramValue = $_GET[$param];

    switch ($operator) {
        case '=':
            return $paramValue == $value;
        case '!=':
            return $paramValue != $value;
        case '>':
            return $paramValue > $value;
        case '<':
            return $paramValue < $value;
        case '>=':
            return $paramValue >= $value;
        case '<=':
            return $paramValue <= $value;
        default:
            throw new InvalidArgumentException("Unsupported operator: $operator");
    }
}
