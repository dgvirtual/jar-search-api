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

// Ensure this process waits for a write lock instead of failing immediately if
// another process (e.g. the scraper finishing its maintenance queries) is still
// writing to the database.
$db->exec('PRAGMA busy_timeout = 30000;');

// Send emails
$notificationQuery = $db->query('SELECT * FROM notifications WHERE sent_at IS NULL');

$failedSending = 0; // Move this outside the loop
$sentCount = 0;
while ($notification = $notificationQuery->fetchArray(SQLITE3_ASSOC)) {
    $email = $notification['email'];
    $subject = $notification['subject'];
    $content = $notification['content'];

    // reset to false for production
    $testing = false;
    if (!emailSubscriber($email, $subject, $content, $testing)) {
        $failedSending++;
        continue;
    }

    // A notification may only be considered handled if the sent_at flag was
    // actually written. If this write fails (e.g. the database is locked), the row
    // is left untouched so it is retried on the next run instead of being silently
    // re-sent on every run forever.
    $updated = $db->exec('UPDATE notifications SET sent_at = "' . date('Y-m-d H:i:s') . '" WHERE id = ' . (int) $notification['id']);
    if ($updated === false) {
        log_message('error', 'Could not mark notification ' . $notification['id'] . ' as sent: ' . $db->lastErrorMsg());
        $failedSending++;
    } else {
        $sentCount++;
    }
}
// Notify admin once if there are failures
if ($failedSending > 0) {
    emailAdmin('Failed to send emails', "Failed to send $failedSending emails; attempts will be repeated tomorrow.");
}

log_message('info', "Sent $sentCount emails, $failedSending failed.");

log_message('info', 'End sending emails');
