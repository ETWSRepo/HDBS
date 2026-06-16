# Watch for file changes and auto-deploy to Hostinger
# Usage: .\watch.ps1

$creds = @{}
Get-Content "$PSScriptRoot\.ftp-credentials" | ForEach-Object {
    if ($_ -match "^(\w+)=(.+)$") { $creds[$Matches[1]] = $Matches[2] }
}
$ftpHost = $creds["FTP_HOST"]
$ftpUser = $creds["FTP_USER"]
$ftpPass = $creds["FTP_PASS"]
$ftpPort = $creds["FTP_PORT"]
$local   = $PSScriptRoot
$apiBase = "https://handmadedesignsbysuzi.com/api"

$exclude = @(".git",".ftp-credentials","deploy.ps1","watch.ps1","CLAUDE.md","README.md","node_modules","product_images","secrets.php","debug.php","debug.flag","drop_tn_tax.php","fix_tax.php","sq_test.php","run_tests.html","reset_nav.php","default.php","get_products.php")

function Should-Exclude($path) {
    foreach ($ex in $exclude) {
        if ((Split-Path $path -Leaf) -like $ex) { return $true }
        if ($path -like "*\$ex\*") { return $true }
        if ($path -like "*/.git/*") { return $true }
    }
    return $false
}

function Deploy-File($rel) {
    $localPath  = Join-Path $local $rel
    $remotePath = ($rel -replace "\\", "/")
    $url = "ftp://${ftpHost}:${ftpPort}/${remotePath}"
    Write-Host "[$([datetime]::Now.ToString('HH:mm:ss'))] Deploying $rel ..." -ForegroundColor Cyan
    $out = & curl.exe --ftp-create-dirs -u "${ftpUser}:${ftpPass}" -T $localPath $url 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "  OK" -ForegroundColor Green
        if ($rel -like '*regression_test.php') {
            try {
                $body = @{action='increment_minor_version'} | ConvertTo-Json -Compress
                $json = Invoke-RestMethod -Uri "$apiBase/admin.php" -Method Post -Body $body -ContentType "application/json"
                Write-Host "  Version incremented to $($json.version)" -ForegroundColor Cyan
            } catch {}
        }
    } else {
        Write-Host "  FAILED: $out" -ForegroundColor Red
    }
}

$watcher = New-Object System.IO.FileSystemWatcher
$watcher.Path = $local
$watcher.IncludeSubdirectories = $true
$watcher.EnableRaisingEvents = $true
$watcher.NotifyFilter = [System.IO.NotifyFilters]::LastWrite

# Debounce: track last deploy time per file
$lastDeploy = @{}
$debounceMs = 800

$onChange = {
    $path = $Event.SourceEventArgs.FullPath
    $rel  = $path.Substring($local.Length + 1)

    if (Should-Exclude $path) { return }
    if (-not (Test-Path $path -PathType Leaf)) { return }

    $now = [datetime]::UtcNow
    if ($lastDeploy.ContainsKey($rel)) {
        $elapsed = ($now - $lastDeploy[$rel]).TotalMilliseconds
        if ($elapsed -lt $debounceMs) { return }
    }
    $lastDeploy[$rel] = $now

    Deploy-File $rel
}

Register-ObjectEvent $watcher Changed -Action $onChange | Out-Null
Register-ObjectEvent $watcher Created -Action $onChange | Out-Null

Write-Host "Watching $local for changes. Press Ctrl+C to stop." -ForegroundColor Yellow
Write-Host "Excluded: $($exclude -join ', ')" -ForegroundColor DarkGray
Write-Host ""

try {
    while ($true) { Start-Sleep -Seconds 1 }
} finally {
    $watcher.EnableRaisingEvents = $false
    $watcher.Dispose()
    Get-EventSubscriber | Unregister-Event
    Write-Host "Watcher stopped." -ForegroundColor Yellow
}
