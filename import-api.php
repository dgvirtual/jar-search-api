<?php
require_once('config.php');

// $db = new DatabaseConnection();
require_once('common/classes.php');

if (isset($_GET['action']) && $_GET['action'] === 'start' && !file_exists(DBFILE)) {
    // initialize db filling process and return at once

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows environment
        pclose(popen("start /B php " . BASE_DIR . "data/initialize-db.php initial > NUL 2>&1", "r"));
    } else {
        exec("php " . BASE_DIR . "data/initialize-db.php initial > /dev/null 2>&1 &");
    }

    // Return immediately
    header('Content-Type: application/json');
    echo json_encode(['status' => 'OK']);
} elseif (isset($_GET['action']) && $_GET['action'] === 'progress') {
    $db = new mySQLite3(DBFILE);

    header('Content-Type: application/json');

    try {

        if (!$db->tableExists('settings')) {
            $response = [
                'progress' => 0,
                'next' => 3,
                'current' => 'Pradedama...'
            ];
        } else {

            $progressData = $db->getSetting('import_progress');
            // For demonstration, let's assume $progressData is retrieved as follows:
            // $progressData = "80|Atsisiunčiami duomenys lentelei „Individual“";

            // Split the data
            list($progress, $next, $current) = explode('|', $progressData);

            // Prepare the response array
            $response = [
                'progress' => (int)$progress,
                'next' => (int)$next,
                'current' => $current
            ];
        }

        // Send the JSON response
        echo json_encode($response);
    } catch (Exception $e) {
        // Handle any errors that occur during the process
        echo json_encode(['error' => $e->getMessage()]);
    }
}
return "Wrong parameters, or DB already exists";
