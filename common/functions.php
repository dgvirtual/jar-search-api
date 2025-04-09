<?php

if (count(get_included_files()) == 1) {
    die('This file is not meant to be accessed directly.');
}

function logRequest(array $request): void
{
    $logFilePath = BASE_DIR . 'writable/logs/' . date('Y-m-d') . '.log';
    $logData = date('Y-m-d H:i:s') . ' - ';

    foreach ($request as $key => $value) {
        if (!empty($value)) {
            $logData .= "$key=$value ";
        }
    }

    $logData = rtrim($logData) . PHP_EOL;
    file_put_contents($logFilePath, $logData, FILE_APPEND);
}

function getDailyLogContent()
{
    $logFilePath = BASE_DIR . 'writable/logs/' . date('Y-m-d') . '.log';
    if (file_exists($logFilePath)) {
        $logContent = file_get_contents($logFilePath);
        return "This is what the users have been searching for today:\n\n" . $logContent;
    } else {
        return "No user searches today.";
    }
}

/*
* Extract the email address from a string
* @param string $string
* @return string|null
*/
function extractEmail($string)
{
    // Use a regular expression to match the email address
    if (preg_match('/<([^>]+)>/', $string, $matches)) {
        return $matches[1];
    }
    return null;
}

function emailAdmin($subject, $message): bool
{
    $headers = "From: " . FROM_EMAIL;
    return mail(ADMIN_EMAIL, $subject, $message, $headers, "-f" . extractEmail(FROM_EMAIL));
}


function emailSubscriber($email, $subject, $message, $testing = false): bool
{
    $headers = "From: " . FROM_EMAIL . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $message = '
            <html>
            <head>
            <title>' . $subject . '</title>
            </head>
            <body>
            <p>' . $message . '</p>
            </body>
            </html>
            ';
    if ($testing) {
        // log the email target and subject line and time to the log file
        log_fake_email('info', 'Email would be sent to: ' . $email . ' with subject: ' . $subject);
        return  true;
    }
    // Set envelope sender with -f
    return mail($email, $subject, $message, $headers, "-f" . extractEmail(FROM_EMAIL));

}

function log_fake_email($level, $message): void
{
    $logFilePath = BASE_DIR . 'writable/logs/' . date('Y-m-d') . '_fake_emails.log';
    $logData = date('Y-m-d H:i:s') . " [$level] - $message" . PHP_EOL;
    file_put_contents($logFilePath, $logData, FILE_APPEND);
}

function log_message($level, $message): void
{
    $logFilePath = BASE_DIR . 'writable/logs/' . date('Y-m-d') . '_testing.log';
    $logData = date('Y-m-d H:i:s') . " [$level] - $message" . PHP_EOL;
    file_put_contents($logFilePath, $logData, FILE_APPEND);
}

function getBaseUrl()
{
    return BASE_URL;
}

function base_url()
{
    return BASE_URL;

    //  $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';

    //  // Get the host (domain name)
    //  $host = $_SERVER['HTTP_HOST'];

    //  if (substr($host, 0, 5) === 'local' || SUBDIR === '') {
    //     $path = '/';
    // } else {
    //     $path = '/' . SUBDIR . '/';
    // }

    // // Construct the base URL
    // return $protocol . '://' . $host . $path;

}


function loadEnv($filePath)
{
    if (!file_exists($filePath)) {
        throw new Exception("The .env file does not exist: " . $filePath);
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Ensure the line contains an equal sign
        if (strpos($line, '=') === false) {
            continue;
        }

        // Parse lines
        list($key, $value) = explode('=', $line, 2);

        $key = trim($key);
        $value = trim($value);

        // Remove surrounding quotes from the value if present
        if (preg_match('/^["\'].*["\']$/', $value)) {
            $value = substr($value, 1, -1);
        }

        // Check for namespaced keys
        if (strpos($key, '.') !== false) {
            // Split the key into parts
            $keys = explode('.', $key);
            $mainKey = array_shift($keys);

            // Initialize the global array if not already done
            if (!isset($GLOBALS[$mainKey])) {
                $GLOBALS[$mainKey] = [];
            }

            // Assign the value to the nested array
            $current = &$GLOBALS[$mainKey];
            foreach ($keys as $part) {
                if (!isset($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
            $current = $value;
        } else {
            // Set environment variable if not already set
            if (!getenv($key)) {
                putenv(sprintf('%s=%s', $key, $value));
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }

            // Define constant if not already defined
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
}


if (! function_exists('d')) {
    function d($data)
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
        $caller = $backtrace[0];

        echo '<pre style="background: #f4f4f4; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">';
        echo '<strong>Debug at ' . $caller['file'] . ' (line ' . $caller['line'] . '):</strong><br><br>';

        if (is_array($data) || is_object($data)) {
            print_r($data);
        } else {
            var_dump($data);
        }

        echo '</pre>';
    }
}


if (! function_exists('dd')) {
    function dd($data)
    {
        d($data);

        exit;
    }
}

/**
 * Should this be moved to classes, db class?
 * @param object $db
 * @param string $email
 * @return array $subscriptionData
 */
function getVerifiedSubscriptions($db, $email): array
{
    // Load environment variables
    $unlimitedEmails = array_map('trim', explode(',', SUBSCRIPTIONS_UNLIMITED));
    $subscriptionLimit = (int)SUBSCRIPTION_LIMIT;

    // Retrieve the list of verified subscriptions
    $subscriptionQuery = $db->prepare('SELECT verification_id FROM subscriptions WHERE email = :email AND verified = 1');
    $subscriptionQuery->bindValue(':email', $email, SQLITE3_TEXT);
    $subscriptionResult = $subscriptionQuery->execute();

    $verificationIds = [];
    while ($row = $subscriptionResult->fetchArray(SQLITE3_ASSOC)) {
        $verificationIds[] = $row['verification_id'];
    }

    $subscriptionCount = count($verificationIds);

    if ($subscriptionCount === 0) {
        return [
            'count' => 0,
            'maxedOut' => false,
            'manageKey' => ''
        ];
    }

    // Calculate the manage key
    $manageKey = substr(hash('sha256', implode('', $verificationIds)), 0, 30);

    // Check if the email is in the list of unlimited subscriptions
    $unlimited = in_array($email, $unlimitedEmails);

    return [
        'count' => $subscriptionCount,
        'maxedOut' => $unlimited ? false : $subscriptionCount >= $subscriptionLimit,
        'manageKey' => $manageKey
    ];
}

function saltedEmailHash($email)
{
    return substr(hash('sha256', strtolower($email) . SALT), 0, 30);
}
