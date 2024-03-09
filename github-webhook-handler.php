<?php
// github-webhook-handler.php
include_once 'config.php';

// Log file path
$logFilePath = 'github_webhook_handler.log';

// Function to append log messages to a log file
function logMessage($message) {
    global $logFilePath;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFilePath, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("Webhook handler started.");

// Validate the GitHub signature
$headers = getallheaders();
$hubSignature = $headers['X-Hub-Signature'] ?? '';

// Split signature into algorithm and hash
list($algo, $hash) = explode('=', $hubSignature, 2);

// Payload
$payload = file_get_contents('php://input');

// Calculate hash based on payload and the secret
$payloadHash = hash_hmac($algo, $payload, $SECRET_KEY);

// Compare the calculated hash with the one from the header
if (!hash_equals($hash, $payloadHash)) {
    logMessage("GitHub signature does not match.");
    die('GitHub signature does not match.');
}

logMessage("GitHub signature verified.");

// Define the API URL to fetch the file content from GitHub
$apiUrl = 'https://api.github.com/repos/Gabtoof/evaa-load-calculator/contents/evaa-load-calculator.php';

logMessage("Fetching file from GitHub API: $apiUrl");

// Set up a cURL handle for the API request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Accept: application/vnd.github.v3.raw',
    'User-Agent: My-GitHub-App' // GitHub requires a user-agent header
    // If using authentication, add 'Authorization: token YOUR_PERSONAL_ACCESS_TOKEN' here
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Check if the API request was successful
if ($httpCode != 200) {
    logMessage("Failed to fetch file via GitHub API. HTTP status code: $httpCode");
    die('Failed to fetch file via GitHub API.');
}

$fileContents = $response;

logMessage("File fetched from GitHub API successfully.");

// Define the path to where the file should be saved
$localFilePath = dirname(__FILE__) . '/evaa-load-calculator.php';

logMessage("Local file path: $localFilePath");

// Backup existing file before updating
$backupFilePath = $localFilePath . '.bak';
if (!copy($localFilePath, $backupFilePath)) {
    logMessage("Failed to create a backup of the existing plugin file.");
    die('Failed to create a backup of the existing plugin file.');
}

logMessage("Backup created successfully: $backupFilePath");

// Use file_put_contents to save the file to the local path, replacing the old one
$result = file_put_contents($localFilePath, $fileContents);

if ($result === false) {
    logMessage("Failed to update the plugin file.");
    die('Failed to update the plugin file.');
}

logMessage("Plugin file updated successfully. Bytes written: $result");

// Log the payload for debugging purposes
file_put_contents('webhook_payload.log', $payload, FILE_APPEND);

logMessage("Webhook handler completed successfully.");

http_response_code(200); // Respond to GitHub that the webhook was received successfully
?>
