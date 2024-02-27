# Specify the source files
$sourcePaths = @(
    "C:\Users\AndrewBatiuk\OneDrive - Batiuk\git\evaa-load-calculator\evaa-load-calculator.php",
    "C:\Users\AndrewBatiuk\OneDrive - Batiuk\git\evaa-load-calculator\evaa-load-calculator.js"
)

# Specify the destination network share
$destinationPath = "\\192.168.99.9\appdata\wordpress\wp-content\plugins\evaa-load-calculator"

# Initialize last action times for comparison
$lastWriteTimes = @{}
$sourcePaths | ForEach-Object { $lastWriteTimes[$_] = (Get-Item $_).LastWriteTime }

# Initialize action history
$actionHistory = @()

# Main monitoring loop
while ($true) {
    # Clear and print the static header
    Clear-Host
    Write-Host "Monitoring the following files for changes:"
    $sourcePaths | ForEach-Object { Write-Host $_ }
    Write-Host "---"

    # Update and display time since last action
    $now = Get-Date
    if ($lastActionTime) {
        $elapsed = New-TimeSpan -Start $lastActionTime -End $now
        $secondsElapsed = [Math]::Floor($elapsed.TotalSeconds)
        if ($secondsElapsed -le 30) {
            Write-Host "Time since last action: $secondsElapsed seconds" -ForegroundColor Green
        } else {
            Write-Host "Time since last action: >30 seconds" -ForegroundColor Cyan
        }
    } else {
        Write-Host "Waiting for the first action..."
    }

    # Display the last 5 actions
    $actionHistory | Select-Object -Last 5 | ForEach-Object { Write-Host $_ }

    # Check each source file for changes
    foreach ($sourcePath in $sourcePaths) {
        $currentLastWriteTime = (Get-Item $sourcePath).LastWriteTime
        if ($currentLastWriteTime -ne $lastWriteTimes[$sourcePath]) {
            # File has changed, copy it to the destination
            Copy-Item -Path $sourcePath -Destination $destinationPath -Force
            $action = "Copied $(Split-Path $sourcePath -Leaf) to destination at $now."
            $actionHistory += $action
            Write-Host $action
            $lastWriteTimes[$sourcePath] = $currentLastWriteTime
            $lastActionTime = $now
        }
    }

    # Wait a bit before the next cycle
    Start-Sleep -Seconds 1
}
