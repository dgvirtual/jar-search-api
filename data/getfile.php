<?php

/**
 * This is the proxy file to be used on this and other domains
 * for scrapping RC data
 * 
 * usage:
 * https://domain.lt/getfile.php?apikey=SomeSecrettttkey&url=https://www.registrucentras.lt/jar/p/dok.php?kod=124630955
 * 
 * ALSO, IF YOU USE IT OUTSIDE OF YOUR PROJECT, YOU HAVE TO REPLACE THE 
 * PROXY_API_KEY constant with the real key you are using, as defined in
 * config.php
 */

$proxyApiKey = PROXY_API_KEY; // replace with a string key in case of placement outside this app

// Allow CORS for any domain: only needed on browser access
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Handle preflight requests
    exit(0);
}

if (!isset($_GET['apikey']) || $_GET['apikey'] !== $proxyApiKey) {
    http_response_code(400);
    echo "Error: please provide api key";
    exit(1);
}

// Get the base URL from the query string
if (!isset($_GET['url'])) {
    http_response_code(400);
    echo "Error: URL parameter is missing";
    exit(1);
}

$base_url = $_GET['url'];
unset($_GET['url']); // Remove 'url' from query parameters

// Validate the base URL
if (!filter_var($base_url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo "Error: Invalid URL";
    exit(1);
}

// optional: limit requests to registrucentras.lt
if (strpos($base_url, 'registrucentras') === false) {
    echo "Rejected: this API is limited to checking data on registrucentras.lt";
    exit(1);
}

// Reconstruct the query string with the remaining parameters
$query_string = http_build_query($_GET);
$full_url = $base_url . (strpos($base_url, '?') === false ? '?' : '&') . $query_string;

// Initialize cURL
$ch = curl_init();

// Set cURL options
curl_setopt($ch, CURLOPT_URL, $full_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects

// Optionally, set a user agent
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3");

// Execute cURL request
$response = curl_exec($ch);

// Check for errors
if (curl_errno($ch)) {
    http_response_code(500);
    echo "Error: " . curl_error($ch);
    curl_close($ch);
    exit(1);
}

// Get content type of the response
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

// Close cURL
curl_close($ch);

// Set the content type header
header("Content-Type: " . $contentType);

// Output the response
echo $response;
