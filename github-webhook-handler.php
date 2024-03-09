<?php
// github-webhook-handler.php
//include_once 'config.php';
$SECRET_KEY = 'test';

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
    die('GitHub signature does not match.');
}


// Define the URL to the raw file on GitHub
$fileUrl = 'https://raw.githubusercontent.com/Gabtoof/evaa-load-calculator/main/evaa-load-calculator.php';

// Define the path to where the file should be saved (typically in your plugin's directory)
//$localFilePath = plugin_dir_path(__FILE__) . 'evaa-load-calculator.php'; //asumes handler.php is in same folder as load-calc
$localFilePath = dirname(__FILE__) . '/evaa-load-calculator.php'; // Assuming they're in the same directory


// Backup existing file before updating
$backupFilePath = $localFilePath . '.bak';
if (!copy($localFilePath, $backupFilePath)) {
    die('Failed to create a backup of the existing plugin file.');
}

// Use file_get_contents to fetch the file from GitHub
$fileContents = file_get_contents($fileUrl);

if ($fileContents === false) {
    die('Failed to download the file from GitHub.');
}

// Use file_put_contents to save the file to the local path, replacing the old one
$result = file_put_contents($localFilePath, $fileContents);

if ($result === false) {
    die('Failed to update the plugin file.');
}

http_response_code(200); // Respond to GitHub that the webhook was received successfully
?>