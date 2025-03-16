<?php

require_once(__DIR__ . '/config.php');
require_once(BASE_DIR . 'common/functions.php');
require_once(BASE_DIR . 'back/api-functions.php');
require_once(BASE_DIR . 'common/classes.php');

// Connect to the database
if (!isset($db)) {
    $db = new mySQLite3(BASE_DIR . DBFILE);
}

$buttonBlock = '<div class="btn-group">
                    <a href="' . BASE_URL . '" class="btn btn-primary">Į svetainę</a>
                    <button onclick="window.close();" class="btn btn-danger">Uždaryti langą</button>
                </div>';

$unlimitedEmails = array_map('trim', explode(',', SUBSCRIPTIONS_UNLIMITED));

// Get the email and key from the GET parameters
$email = isset($_REQUEST['email']) ? $_REQUEST['email'] : null;
$key = isset($_REQUEST['key']) ? $_REQUEST['key'] : null;

if (!$email || !$key) {
    header('Location: ' . BASE_URL);
    exit;
}

// Get verified subscriptions and manage key
$subscriptionData = getVerifiedSubscriptions($db, $email);

// case when there are no subscriptions, and the key represents the email+salt hash
if (saltedEmailHash($email) === $key) {
    // Display the subscriptions data in a form
    $htmlContent = '
                <div class="alert alert-warning mt-5" role="alert">
                    <h4 class="alert-heading">Sistemoje nėra (nebėra) Jūsų prenumeratų</h4>
                    <p>Galite prenumeruoti naujienas apie juridinius asmenis sugrįžę į paieškos puslapį.</p>
                    ' . $buttonBlock . '
                </div>';
} elseif ($subscriptionData['manageKey'] === $key) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Handle form submission
        $subscriptions = isset($_POST['subscriptions']) ? $_POST['subscriptions'] : [];

        // Fetch all subscriptions for the email
        $subscriptionsQuery = $db->prepare('SELECT * FROM subscriptions WHERE email = :email');
        $subscriptionsQuery->bindValue(':email', $email, SQLITE3_TEXT);
        $subscriptionsResult = $subscriptionsQuery->execute();

        $allSubscriptions = [];
        while ($row = $subscriptionsResult->fetchArray(SQLITE3_ASSOC)) {
            $allSubscriptions[] = $row;
        }

        // Check if the selected number of subscriptions exceeds the allowed limit
        $subscriptionLimit = (int)SUBSCRIPTION_LIMIT;
        if (!in_array($email, $unlimitedEmails) && count($subscriptions) > $subscriptionLimit) {
            // Redirect back with a toast message about exceeding the subscription limit
            header('Location: ' . BASE_URL . 'manage.php?email=' . urlencode($email) . '&key=' . $key . '&toast=limit_exceeded');
            exit;
        }

        // Update subscriptions
        foreach ($allSubscriptions as $subscription) {
            $id = $subscription['id'];
            if (isset($subscriptions[$id])) {
                // Set verified to 1 for selected subscriptions
                $updateQuery = $db->prepare('UPDATE subscriptions SET verified = 1 WHERE id = :id AND email = :email');
                $updateQuery->bindValue(':id', $id, SQLITE3_INTEGER);
                $updateQuery->bindValue(':email', $email, SQLITE3_TEXT);
                $updateQuery->execute();
            } else {
                // Delete unselected subscriptions
                $deleteQuery = $db->prepare('DELETE FROM subscriptions WHERE id = :id AND email = :email');
                $deleteQuery->bindValue(':id', $id, SQLITE3_INTEGER);
                $deleteQuery->bindValue(':email', $email, SQLITE3_TEXT);
                $deleteQuery->execute();
            }
        }

        // Generate a new manage key
        $newSubscriptionData = getVerifiedSubscriptions($db, $email);
        $newKey = $newSubscriptionData['manageKey'];
        if (empty($newKey)) {
            $newKey = saltedEmailHash($email);
        }

        // Redirect to the form page with a success toast
        header('Location: ' . BASE_URL . 'manage.php?email=' . urlencode($email) . '&key=' . $newKey . '&toast=success');
        exit;
    }

    // Fetch all subscriptions for the email
    $subscriptionsQuery = $db->prepare('SELECT s.*, p.ja_pavadinimas FROM subscriptions s JOIN persons p ON s.ja_kodas = p.ja_kodas WHERE s.email = :email');
    $subscriptionsQuery->bindValue(':email', $email, SQLITE3_TEXT);
    $subscriptionsResult = $subscriptionsQuery->execute();

    $subscriptions = [];
    while ($row = $subscriptionsResult->fetchArray(SQLITE3_ASSOC)) {
        $subscriptions[] = $row;
    }

    // Display the subscriptions data in a form
    $htmlContent = '
        <div class="my-3" style="max-width: 900px;">
            <h1 class="display-5">Tvarkyti prenumeratas</h1>';

    if (!in_array($email, $unlimitedEmails)) {
        $htmlContent .= '<p>Galite prenumeruoti iki ' . SUBSCRIPTION_LIMIT . ' juridinių asmenų pranešimus.</p>';
    }

    $htmlContent .= '
            <form method="post" action="manage.php?email=' . urlencode($email) . '&key=' . $key . '">
                <input type="hidden" name="email" value="' . htmlspecialchars($email) . '">
                <input type="hidden" name="key" value="' . htmlspecialchars($key) . '">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Kodas</th>
                                <th>Pavadinimas</th>
                                <th>Prenumeruota</th>
                                <th>Patvirtinta</th>
                            </tr>
                        </thead>
                        <tbody>';

        foreach ($subscriptions as $subscription) {
            $checked = $subscription['verified'] ? 'checked' : '';
            $htmlContent .= '
                <tr>
                    <td>' . $subscription['ja_kodas'] . '</td>
                    <td>' . htmlspecialchars($subscription['ja_pavadinimas']) . '</td>
                    <td>' . substr($subscription['created_at'], 0, 10) . '</td>
                    <td><input type="checkbox" name="subscriptions[' . htmlspecialchars($subscription['id']) . ']" ' . $checked . '></td>
                </tr>';
        }

        $htmlContent .= '
                        </tbody>
                    </table>
                </div>
                <p class="alert alert-info">Išsaugojus formą nepažymėtos prenumeratos bus ištrintos iš Jūsų sąrašo.</p>
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">Išsaugoti</button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href=\'' . BASE_URL . '\'">Grįžti į svetainę</button>
                </div>
            </form>
        </div>';
} else {
    // Display a button to send an email with the updated key
    $subject = 'Tvarkykite savo prenumeratas';
    $message = 'Prenumeratas galite tvarkyti pasinaudoję šia nuoroda (nuoroda galios iki pirmo prenumeratų pakeitimo):<br>';
    $message .= '<a href="' . BASE_URL . 'manage.php?email=' . urlencode($email) . '&key=' . $subscriptionData['manageKey'] . '">Tvarkyti prenumeratas</a>';

    if ($key === 'GetNewKey') {
        $startString = 'Pateikėte prašymą dėl tvarkymo nuorodos gavimo. ';
    } else {
        $startString = 'Prenumeratų neturite, arba verifikavimo kodas nebegalioja. ';
    }

    $displayMessage = '<p> ' . $startString . '<br> Jei prenumeratų turite, el. laiškas buvo išsiųstas adresu ' . htmlspecialchars($email) . ' su nuoroda kurią paspaudę galėsite tvarkyti prenumeratas.</p>';

    if ($subscriptionData['count'] === 0 || emailSubscriber($email, $subject, $message)) {
        $htmlContent = '
        <div class="container my-5">
            <div class="alert alert-info mt-5" role="alert">
                <h4 class="alert-heading">Prenumeratų tvarkymo nuorodos</h4>
                ' . $displayMessage . $buttonBlock . '
            </div>
        </div>';
    } else {
        $htmlContent = '
        <div class="container my-5" style="max-width: 900px;">
            <div class="alert alert-danger mt-5" role="alert">
                <h4 class="alert-heading">Prenumeratų tvarkymo nuorodos</h4>
                <p>El. laiško išsiųsti nepavyko. Bandykite dar kartą arba susisiekite su administratoriumi.</p>
                ' . $buttonBlock . '
            </div>
        </div>';
    }
}

include(BASE_DIR . 'views/header.php');

echo $htmlContent;

echo '<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
        <div id="toast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastMessage"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const urlParams = new URLSearchParams(window.location.search);
            const toastElement = document.getElementById("toast");
            const toastMessage = document.getElementById("toastMessage");
            if (urlParams.get("toast") === "success") {
                toastMessage.textContent = "Prenumeratos sėkmingai atnaujintos.";
                toastElement.classList.add("text-bg-success");
                const toast = new bootstrap.Toast(toastElement);
                toast.show();
            } else if (urlParams.get("toast") === "limit_exceeded") {
                toastMessage.textContent = "Viršytas leidžiamų prenumeratų skaičius.";
                toastElement.classList.add("text-bg-danger");
                const toast = new bootstrap.Toast(toastElement);
                toast.show();
            }
        });
    </script>';

include(BASE_DIR . 'views/footer.php');
