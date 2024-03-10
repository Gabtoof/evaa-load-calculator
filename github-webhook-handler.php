<?php
// github-webhook-handler.php
include_once 'config.php';

// Log file path
$logFilePath = dirname(__FILE__) . '/github_webhook_handler.log';

// Automatically determine the script's own filename and log file to exclude them from cleanup
$selfFilename = basename(__FILE__);
$logFilename = basename($logFilePath);

// Plugin directory
$pluginDir = dirname(__FILE__);

// For getting version # later
// Plugin's main PHP file that contains the version number
$pluginMainFile = $pluginDir . '/evaa-load-calculator.php';


// Function to append log messages to a log file
function logMessage($message) {
    global $logFilePath;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFilePath, "[$timestamp] $message\n", FILE_APPEND);
}

logMessage("Webhook handler started.");


// Read the content of the main plugin file
$pluginMainFileContent = file_get_contents($pluginMainFile);
// Use a regular expression to match the version line and extract the version number
if (preg_match('/Version:\s*(\d+(?:\.\d+){0,2})/', $pluginMainFileContent, $matches)) {
    $versionNumberBefore = $matches[1];
    logMessage("Plugin version before update: $versionNumberBefore");
} else {
    logMessage("Failed to extract plugin version.");
}




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
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            if ($filePath !== $backupFile && !in_array(basename($filePath), [$selfFilename, $logFilename])) {
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

// Number of backups to keep
$numBackupsToKeep = 5;

// Get all zip files in the backup directory
$backupFiles = glob($backupDir . '/*.zip');

// Sort files by modification time, most recent first
usort($backupFiles, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

// Remove older backups, keeping only the most recent $numBackupsToKeep
if (count($backupFiles) > $numBackupsToKeep) {
    $filesToDelete = array_slice($backupFiles, $numBackupsToKeep);
    foreach ($filesToDelete as $file) {
        unlink($file);
        logMessage("Deleted old backup file: $file");
    }
}

// Define directories and files to preserve in the cleanup process
$preserveItems = ['backups', 'config.php', $selfFilename, $logFilename]; // Dynamically include this script and the log file

// Cleanup existing files/directories in the plugin directory
$pluginItems = array_diff(scandir($pluginDir), ['..', '.', ...$preserveItems]);
foreach ($pluginItems as $item) {
    $itemPath = $pluginDir . '/' . $item;
    if (is_dir($itemPath)) {
        // Recursively delete directories
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($itemPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                rmdir($fileinfo->getRealPath());
            } else {
                unlink($fileinfo->getRealPath());
            }
        }
        rmdir($itemPath);
    } else {
        // Delete files
        unlink($itemPath);
    }
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

// Extract the ZIP file to a temporary directory
$zip = new ZipArchive;
$res = $zip->open($tempZip);
if ($res === TRUE) {
    $tempExtractDir = sys_get_temp_dir() . '/extracted_plugin_' . uniqid();
    if (!is_dir($tempExtractDir)) {
        mkdir($tempExtractDir, 0755, true);
    }

    $zip->extractTo($tempExtractDir);
    $zip->close();

    // Assume the first directory is the one we want to move files from
    $extractedFolders = array_diff(scandir($tempExtractDir), ['..', '.']);
    $githubFolder = reset($extractedFolders);
    $sourceDir = $tempExtractDir . '/' . $githubFolder;

    // Move files from the source directory to the plugin directory
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $destPath = $pluginDir . '/' . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($destPath)) {
                mkdir($destPath);
            }
        } else {
            copy($item, $destPath);
        }
    }

    // Cleanup
    array_map('unlink', glob("$tempExtractDir/*.*"));
    rmdir($tempExtractDir);


    logMessage("Plugin updated successfully.");
} else {
    logMessage("Failed to open ZIP file: " . $tempZip);
    die("Failed to open ZIP file.");
}

// Re-Read the content of the main plugin file
$pluginMainFileContent = file_get_contents($pluginMainFile);
// Use a regular expression to match the version line and extract the version number
if (preg_match('/Version:\s*(\d+(?:\.\d+){0,2})/', $pluginMainFileContent, $matches)) {
    $versionNumberAfter = $matches[1];
    logMessage("Plugin version after update: $versionNumberAfter");
} else {
    logMessage("Failed to extract plugin version.");
}


// Now compare the versions and log whether an update occurred
if ($versionNumberBefore !== $versionNumberAfter) {
    logMessage("Update successful: Version changed from $versionNumberBefore to $versionNumberAfter");
} else {
    logMessage("Update failed or unnecessary: Version remains at $versionNumberBefore");
}

unlink($tempZip); // Remove the temporary zip file

// Cleanup and final steps
logMessage("Webhook handler completed successfully.");
http_response_code(200);
?>
