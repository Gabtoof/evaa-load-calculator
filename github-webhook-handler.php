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

// Plugin directory
$pluginDir = dirname(__FILE__);

// Backup directory within the plugin directory
$backupDir = $pluginDir . '/backups';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true); // Ensure the backup directory exists
}

// Adjusted backup file path to save within the new backups directory
$backupFile = $backupDir . '/evaa-load-calculator-backup-' . date('Y-m-d-H-i-s') . '.zip';

// Create a zip archive of the current plugin directory, excluding zip files
$zip = new ZipArchive();
if ($zip->open($backupFile, ZipArchive::CREATE) === TRUE) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginDir), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($files as $name => $file) {
        // Skip directories and zip files
        if (!$file->isDir() && $file->getExtension() !== 'zip') {
            $filePath = $file->getRealPath();
            // Ensure the backup zip file itself is not included
            if ($filePath !== $backupFile) {
                $relativePath = substr($filePath, strlen($pluginDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
    $zip->close();
    logMessage("Backup created successfully: $backupFile");
} else {
    logMessage("Failed to create a backup of the existing plugin directory.");
    die('Failed to create a backup of the existing plugin directory.');
}

// GitHub API URL to download the repository zip
$repoZipUrl = 'https://api.github.com/repos/Gabtoof/evaa-load-calculator/zipball/main';

$tempZip = $pluginDir . '/temp_plugin.zip'; // Temporary file path for the downloaded zip

// Set up a cURL handle for the API request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $repoZipUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Accept: application/vnd.github.v3+json',
    'User-Agent: EVAA Updater',
    'Authorization: token ' . $PAT // Use the PAT for authentication
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_FAILONERROR, true);

// Save the downloaded zip file
$output = fopen($tempZip, 'w');
curl_setopt($ch, CURLOPT_FILE, $output);

// Execute the request
$response = curl_exec($ch);
if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    logMessage("Failed to download the repository zip: " . $error_msg);
    die('Failed to download the repository zip.');
}
fclose($output);
curl_close($ch);

// Extract the zip file
$zip = new ZipArchive;
if ($zip->open($tempZip) === TRUE) {
    $zip->extractTo($pluginDir);
    $zip->close();
    logMessage("Plugin updated successfully.");
    unlink($tempZip); // Remove the temporary zip file
} else {
    logMessage("Failed to update the plugin.");
    die('Failed to update the plugin.');
}

// Cleanup and final steps
logMessage("Webhook handler completed successfully.");
http_response_code(200); // Indicate successful completion
?>
