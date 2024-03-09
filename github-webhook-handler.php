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
$hubSignature = $headers['X-Hub-Signature'];

// Split signature into algorithm and hash
list($algo, $hash) = explode('=', $hubSignature, 2);

// Payload
$payload = file_get_contents('php://input');

// Calculate hash based on payload and the secret
$payloadHash = hash_hmac($algo, $payload, $SECRET_KEY);

// Compare the calculated hash with the one from the header
if ($hash !== $payloadHash) {
    logMessage("GitHub signature does not match.");
    die('GitHub signature does not match.');
}

logMessage("GitHub signature verified.");

// Define the URL to the raw file on GitHub
$fileUrl = 'https://raw.githubusercontent.com/Gabtoof/evaa-load-calculator/main/evaa-load-calculator.php';

logMessage("Fetching file from GitHub: $fileUrl");

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

// Use file_get_contents to fetch the file from GitHub
$fileContents = file_get_contents($fileUrl);

if ($fileContents === false) {
    logMessage("Failed to download the file from GitHub.");
    die('Failed to download the file from GitHub.');
}

logMessage("File fetched from GitHub successfully.");

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
