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
    mkdir($backupDir, 0755, true);
}

// Adjusted backup file path to save within the new backups directory
$backupFile = $backupDir . '/evaa-load-calculator-backup-' . date('Y-m-d-H-i-s') . '.zip';

// Create a zip archive of the current plugin directory, excluding zip files
$zip = new ZipArchive();
if ($zip->open($backupFile, ZipArchive::CREATE) === TRUE) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pluginDir), RecursiveIteratorIterator::LEAVES_ONLY);
    foreach ($files as $name => $file) {
        if (!$file->isDir() && $file->getExtension() !== 'zip') {
            $filePath = $file->getRealPath();
            if ($filePath !== $backupFile) {
                $relativePath = substr($filePath, strlen($pluginDir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
    $zip->close();
    logMessage("Backup created successfully: $backupFile");
} else {
    logMessage("Failed to create a backup.");
    die('Failed to create a backup.');
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
    'Authorization: token ' . $PAT
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
$res = $zip->open($tempZip);
if ($res === TRUE) {
    // Create a temporary directory for extraction
    $tempExtractDir = $pluginDir . '/temp_extract';
    if (!is_dir($tempExtractDir)) {
        mkdir($tempExtractDir, 0755, true);
    }

    // Extract everything into the temporary directory
    $zip->extractTo($tempExtractDir);
    $zip->close();

    // Move files from the temp directory to the plugin directory
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tempExtractDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $destPath = $pluginDir . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($destPath)) {
                mkdir($destPath);
            }
        } else {
            rename($item, $destPath);
        }
    }

    // Cleanup: Remove the temporary extraction directory
    $iterator = new RecursiveDirectoryIterator($tempExtractDir, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator(
        $iterator,
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($tempExtractDir);

    logMessage("Plugin updated successfully.");
    unlink($tempZip); // Remove the temporary zip file
} else {
    logMessage("Failed to open ZIP file: " . $tempZip);
    die("Failed to open ZIP file.");
}

// Cleanup and final steps
logMessage("Webhook handler completed successfully.");
http_response_code(200);
?>
