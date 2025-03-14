<?php

require_once(__DIR__ . '/../config.php');
require_once(BASE_DIR . 'data/data-functions.php');
require_once(BASE_DIR . 'common/classes.php');
require_once(BASE_DIR . 'common/functions.php');


// Check if the script is run from the command line with the correct parameter
if (!isset($argv[1]) || $argv[1] !== 'run') {
    die('This script can only be run from the command line with the correct parameter.');
}


if (!isset($db)) {
    $db = new mySQLite3(BASE_DIR . DBFILE);
}

// Send emails
$notificationQuery = $db->query('SELECT * FROM notifications WHERE sent_at IS NULL');

while ($notification = $notificationQuery->fetchArray(SQLITE3_ASSOC)) {
    $email = $notification['email'];
    $subject = $notification['subject'];
    $content = $notification['content'];

    $failedSending = 0;
    if (emailSubscriber($email, $subject, $content)) {
        $db->exec('UPDATE notifications SET sent_at = "' . date('Y-m-d H:i:s') . '" WHERE id = ' . $notification['id']);
    } else {
        $failedSending++;
    }

    if ($failedSending > 0) {
        emailAdmin('Failed to send emails', "Failed to send $failedSending emails; attempts will be repeated tomorrow.");
    }
}
