<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once(__DIR__ . '/config.php');
require_once(BASE_DIR . 'common/functions.php');
require_once(BASE_DIR . 'back/api-functions.php');
require_once(BASE_DIR . 'common/classes.php');

// Connect to the database
$db = new mySQLite3(BASE_DIR . DBFILE);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['person'], $_GET['verify'])) {
    $ja_kodas = $_GET['person'];
    $verification_id = $_GET['verify'];

    // Check if the subscription exists
    $subscriptionQuery = $db->prepare('SELECT * FROM subscriptions WHERE ja_kodas = :ja_kodas AND verification_id = :verification_id');
    $subscriptionQuery->bindValue(':ja_kodas', $ja_kodas, SQLITE3_INTEGER);
    $subscriptionQuery->bindValue(':verification_id', $verification_id, SQLITE3_TEXT);
    $subscriptionResult = $subscriptionQuery->execute();

    $htmlContent = '';

    if ($subscription = $subscriptionResult->fetchArray(SQLITE3_ASSOC)) {
        // Update the subscription to verified
        $updateQuery = $db->prepare('UPDATE subscriptions SET verified = 1 WHERE id = :id');
        $updateQuery->bindValue(':id', $subscription['id'], SQLITE3_INTEGER);
        $updateQuery->execute();

        // Fetch the legal person details
        $personQuery = $db->prepare('SELECT * FROM persons WHERE ja_kodas = :ja_kodas');
        $personQuery->bindValue(':ja_kodas', $ja_kodas, SQLITE3_INTEGER);
        $personResult = $personQuery->execute();
        $person = $personResult->fetchArray(SQLITE3_ASSOC);

        $buttonBlock = '<div class="btn-group">
                    <a href="' . BASE_URL . '" class="btn btn-primary">Į svetainę</a>
                    <button onclick="window.close();" class="btn btn-secondary">Uždaryti langą</button>
                </div>';
        // Display confirmation page with legal person details
        $htmlContent = '
            <div class="alert alert-success mt-5" role="alert">
                <h4 class="alert-heading">Prenumerata patvirtinta!</h4>
                <p>Jūs sėkmingai užsiprenumeravote pranešimus apie juridinį asmenį.</p>
                <hr>
                <h5>Juridinio asmens duomenys:</h5>
                <ul>
                    <li><strong>Kodas:</strong> ' . htmlspecialchars($person['ja_kodas']) . '</li>
                    <li><strong>Pavadinimas:</strong> ' . htmlspecialchars($person['ja_pavadinimas']) . '</li>
                    <li><strong>Adresas:</strong> ' . htmlspecialchars($person['adresas']) . '</li>
                    <li><strong>Registravimo data:</strong> ' . htmlspecialchars($person['ja_reg_data']) . '</li>
                </ul>'
                 . $buttonBlock .
            '</div>';

    } else {
        // Display error message if subscription is not found or already verified
        $htmlContent = '
            <div class="alert alert-danger mt-5" role="alert">
                <h4 class="alert-heading">Klaida!</h4>
                <p>Nepavyko patvirtinti prenumeratos. Patikrinkite, ar nuoroda yra teisinga.</p>'
                 . $buttonBlock .
            '</div>';
    }

    // Print the HTML page
    echo '<!DOCTYPE html>
    <html lang="lt">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Prenumeratos patvirtinimas</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
    <body>
        <div class="container">' . $htmlContent . '</div>
    </body>
    </html>';

    return;
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate input data
if (!isset($data['ja_kodas'], $data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    respond(400, 'Neteisingi įvesties duomenys');
}

$ja_kodas = $data['ja_kodas'];
$email = $data['email'];

// Check if the legal person exists
$personQuery = $db->prepare('SELECT * FROM persons WHERE ja_kodas = :ja_kodas');
$personQuery->bindValue(':ja_kodas', $ja_kodas, SQLITE3_INTEGER);
$personResult = $personQuery->execute();

if (!$personResult->fetchArray(SQLITE3_ASSOC)) {
    respond(404, 'Juridinis asmuo nerastas');
}

// Generate a verification ID
$verification_id = bin2hex(random_bytes(10));

// Insert the subscription data into the subscriptions table
$subscriptionQuery = $db->prepare('
    INSERT INTO subscriptions (email, ja_kodas, verification_id, created_at)
    VALUES (:email, :ja_kodas, :verification_id, :created_at)
');
$subscriptionQuery->bindValue(':email', $email, SQLITE3_TEXT);
$subscriptionQuery->bindValue(':ja_kodas', $ja_kodas, SQLITE3_INTEGER);
$subscriptionQuery->bindValue(':verification_id', $verification_id, SQLITE3_TEXT);
$subscriptionQuery->bindValue(':created_at', TIMESTAMP, SQLITE3_TEXT);

if ($subscriptionQuery->execute()) {
    // Send confirmation email
    $subject = 'Patvirtinkite informacijos prenumeratą';
    $message = 'Sveiki,<br><br>';
    $message .= 'Jūsų el. paštas buvo nurodytas svetainėje „Juridinių asmenų paieška“ prenumeruojant juridinio asmens duomenis.<br>';
    $message .= 'Galima sudaryti ne daugiau nei 10-ties juridinių asmenų informacinių pranešimų prenumeratų. Prenumeratos nemokamos. <br>';
    $message .= 'Prenumeratos teikiamos be tikslumo ar išsamumo garantijų.<br><br>';
    $message .= 'Prašome patvirtinti prenumeratą paspaudžiant šią nuorodą:<br>';
    $message .= '<a href="' . BASE_URL . 'subscribe.php?person=' . $ja_kodas . '&verify=' . $verification_id . '">Patvirtinti prenumeratą</a>';

    if (emailSubscriber($email, $subject, $message)) {
        respond(200, 'Prenumerata sukurta. Prašome patikrinti savo el. paštą, kad patvirtintumėte.');
    } else {
        respond(500, 'Nepavyko išsiųsti patvirtinimo el. laiško');
    }
} else {
    respond(500, 'Nepavyko sukurti prenumeratos');
}