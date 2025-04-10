<?php if (count(get_included_files()) == 1) die('This file is not meant to be accessed directly.');


if (!isset($db)) {
    $db = new mySQLite3(BASE_DIR . DBFILE);
}

// input html
$html = file_get_contents(BASE_DIR . 'writable/contents.html');

// Parse the HTML and get the entities
$entities = parseAllJournal($html);

log_message('info', 'Updating the journal table with ' . count($entities) . ' entries');

foreach ($entities as $entity) {

    // Prepare the insert query
    $query = $db->prepare('
        INSERT INTO journal (ja_kodas, ja_pavadinimas, content, section, journal_no, created_at)
        VALUES (:ja_kodas, :ja_pavadinimas, :content, :section, :journal_no, :created_at)
    ');


    // Bind values to the placeholders
    $query->bindValue(':ja_kodas', $entity['ja_kodas'], SQLITE3_INTEGER);
    $query->bindValue(':ja_pavadinimas', $entity['ja_pavadinimas'], SQLITE3_TEXT);
    $query->bindValue(':content', $entity['content'], SQLITE3_TEXT);
    $query->bindValue(':section', $entity['section'], SQLITE3_TEXT);
    $query->bindValue(':journal_no', $entity['journal_no'], SQLITE3_TEXT);
    $query->bindValue(':created_at', date('Y-m-d H:i:s'), SQLITE3_TEXT);

    // Execute the insert query
    $result = $query->execute();
}

log_message('info', 'Retrieving today\'s journal entries');

// Select all journal entries added today
$journalQuery = $db->prepare('SELECT * FROM journal WHERE created_at LIKE :created_at');
$journalQuery->bindValue(':created_at', date('Y-m-d') . ' %', SQLITE3_TEXT);
$journalResults = $journalQuery->execute();

$countEmailsToBeSent = 0;

log_message('info', 'Start filling notifications table');

while ($journalEntry = $journalResults->fetchArray(SQLITE3_ASSOC)) {
    // Retrieve all subscriptions matching the ja_kodas of the journal entry
    $subscriptionsQuery = $db->prepare('SELECT * FROM subscriptions WHERE ja_kodas = :ja_kodas AND verified = 1');
    $subscriptionsQuery->bindValue(':ja_kodas', $journalEntry['ja_kodas'], SQLITE3_INTEGER);
    $subscriptionsResults = $subscriptionsQuery->execute();

    while ($subscription = $subscriptionsResults->fetchArray(SQLITE3_ASSOC)) {
        // Form the notification content
        $subject = "Naujas pranešimas apie juridinį asmenį " . $journalEntry['ja_pavadinimas'];
        $content = "<strong>Pranešimas:</strong><br> " . trim($journalEntry['content']);
        $content .= '<br><br>RC informacinių pranešimų žurnalo numeris: ' . $journalEntry['journal_no'];
        $content .= '<br><br>Žurnalo sekcija: <strong>' . $journalEntry['section'] . '</strong>';
        $content .= '<br><br><a href="' . RC_WEB . JOURNAL_LIST_URL . '">RC žurnalų puslapis</a>';

        // Check if a similar notification already exists
        $existingNotificationQuery = $db->prepare('SELECT COUNT(*) AS count FROM notifications WHERE email = :email AND person = :person AND subject = :subject');
        $existingNotificationQuery->bindValue(':email', $subscription['email'], SQLITE3_TEXT);
        $existingNotificationQuery->bindValue(':person', $subscription['ja_kodas'], SQLITE3_TEXT);
        $existingNotificationQuery->bindValue(':subject', $subject, SQLITE3_TEXT);
        $existingNotificationResult = $existingNotificationQuery->execute()->fetchArray(SQLITE3_ASSOC);

        if ($existingNotificationResult['count'] > 0) {
            continue;
        }

        // Prepare and execute the insert query for notifications
        $notificationQuery = $db->prepare('
            INSERT INTO notifications (email, person, subject, content, created_at)
            VALUES (:email, :person, :subject, :content, :created_at)
        ');
        $notificationQuery->bindValue(':email', $subscription['email'], SQLITE3_TEXT);
        $notificationQuery->bindValue(':person', $subscription['ja_kodas'], SQLITE3_TEXT);
        $notificationQuery->bindValue(':subject', $subject, SQLITE3_TEXT);
        $notificationQuery->bindValue(':content', $content, SQLITE3_TEXT);
        $notificationQuery->bindValue(':created_at', date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $notificationQuery->execute();

        $countEmailsToBeSent++;
    }
}

//count subscriptions
$verifiedCountQuery = $db->query('SELECT COUNT(*) AS verified_count FROM subscriptions WHERE verified = 1');
$verifiedCountResult = $verifiedCountQuery->fetchArray(SQLITE3_ASSOC);
$verifiedSubscriptionCount = $verifiedCountResult['verified_count'];

//count subscribers
$subscriberCountQuery = $db->query('SELECT COUNT(DISTINCT email) AS subscriber_count FROM subscriptions WHERE verified = 1');
$subscriberCountResult = $subscriberCountQuery->fetchArray(SQLITE3_ASSOC);
$subscriberCount = $subscriberCountResult['subscriber_count'];

log_message('info', 'Starting external script to send emails');

// Trigger the separate PHP process for sending emails
if ($countEmailsToBeSent > 0) {
    exec('php ' . BASE_DIR . 'data/sendEmails.php run > /dev/null 2>&1 &');
}
