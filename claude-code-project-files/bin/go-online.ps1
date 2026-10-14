<#
.SYNOPSIS
    Puts this computer's copy of the system online for a demo.

.DESCRIPTION
    Normally started by start-online.bat, which first checks PHP and MySQL and applies migrations.

    1. Warns if a demo account still has the password from database/seed.php.
    2. Opens a Cloudflare quick tunnel and reads the public https://....trycloudflare.com
       address it is given.
    3. Starts the PHP server on its own port, for this run only, with:
         TRUSTED_PROXIES  so each visitor is seen by their own address and the session
                          cookie is marked Secure;
         APP_BASE_URL     set to the public address, so the login page's QR code and
                          "Open mobile preview" link work from any network.

    The site is online only while this window stays open. Press Ctrl+C or close the
    window to take it offline. All data stays in the database on this computer.

    Needs cloudflared. Install it once with:
        winget install --id Cloudflare.cloudflared
    then open a new window.

.EXAMPLE
    .\bin\go-online.ps1
#>
param(
    [int]$Port = 8010,
    [switch]$NoBrowser
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot

$cloudflaredCommand = Get-Command cloudflared -ErrorAction SilentlyContinue
$cloudflared = if ($cloudflaredCommand) { $cloudflaredCommand.Source } else { Join-Path ${env:ProgramFiles(x86)} 'cloudflared\cloudflared.exe' }
if (-not (Test-Path -LiteralPath $cloudflared)) {
    throw "cloudflared is not installed. Run 'winget install --id Cloudflare.cloudflared', open a new window, and start this again."
}

$php = 'C:\xampp\php\php.exe'
if (-not (Test-Path -LiteralPath $php)) {
    $phpCommand = Get-Command php -ErrorAction SilentlyContinue
    if (-not $phpCommand) { throw 'PHP was not found at C:\xampp\php\php.exe or on PATH.' }
    $php = $phpCommand.Source
}

if (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue) {
    throw "Port $Port is already in use. Close the program using it, or choose another port: .\bin\go-online.ps1 -Port 8011"
}

Push-Location $root
try {
    $unchanged = & $php -d display_errors=0 'database/check-demo-passwords.php'
    $checkExit = $LASTEXITCODE
} finally {
    Pop-Location
}
if ($checkExit -eq 1) {
    Write-Warning 'These demo accounts still use the passwords written in DEMO-ACCOUNTS.md:'
    $unchanged | ForEach-Object { Write-Warning "    $_" }
    Write-Warning 'Anyone who has the public address and knows those passwords can sign in. Change them unless this is a short demo.'
} elseif ($checkExit -ne 0) {
    Write-Warning 'Could not check the demo account passwords (is the database running?).'
}

$logDirectory = Join-Path $root 'logs'
$serverLog = Join-Path $logDirectory 'online-server.log'
$tunnelLog = Join-Path $logDirectory 'online-tunnel.log'
$tunnelOut = Join-Path $logDirectory 'online-tunnel.out.log'
# Public address of this run, refreshed every 2 seconds; the normal server (start-system.bat) reads it to show the same QR code.
$addressFile = Join-Path $logDirectory 'online-address.log'
Remove-Item -LiteralPath $tunnelLog, $tunnelOut, $addressFile -ErrorAction SilentlyContinue

$tunnel = $null
$server = $null
try {
    Write-Host 'Opening the tunnel...'
    # -NoNewWindow keeps both programs attached to this window, so closing the window
    # stops them too and the site cannot stay online unseen.
    $tunnel = Start-Process -FilePath $cloudflared `
        -ArgumentList @('tunnel', '--no-autoupdate', '--url', "http://127.0.0.1:$Port") `
        -NoNewWindow -PassThru `
        -RedirectStandardError $tunnelLog -RedirectStandardOutput $tunnelOut

    # Wait for the address and for the connection behind it. The address is not opened from
    # here: asking for it before it exists makes this computer remember that it does not.
    $publicUrl = $null
    $connected = $false
    for ($i = 0; $i -lt 60 -and -not ($publicUrl -and $connected); $i++) {
        Start-Sleep -Milliseconds 500
        if ($tunnel.HasExited) { throw "The tunnel stopped while starting. See $tunnelLog" }
        if (Test-Path -LiteralPath $tunnelLog) {
            $match = Select-String -LiteralPath $tunnelLog -Pattern 'https://(?!api\.)[a-z0-9-]+\.trycloudflare\.com' | Select-Object -First 1
            if ($match) { $publicUrl = $match.Matches[0].Value }
            $connected = [bool](Select-String -LiteralPath $tunnelLog -Pattern 'Registered tunnel connection' -Quiet)
        }
    }
    if (-not ($publicUrl -and $connected)) {
        throw "The tunnel did not come up within 30 seconds. Check your internet connection, then see $tunnelLog"
    }

    Write-Host "Starting the web server on 127.0.0.1:$Port..."
    # These apply to the PHP server started below only.
    # The tunnel connects from this machine, so forwarded headers are trusted only from loopback.
    $env:TRUSTED_PROXIES = '127.0.0.1,::1'
    $env:APP_BASE_URL = $publicUrl
    try {
        $server = Start-Process -FilePath $php `
            -ArgumentList @('-d', 'display_errors=0', '-d', 'log_errors=1', '-d', 'upload_max_filesize=20M', '-d', 'post_max_size=25M', '-S', "127.0.0.1:$Port", '-t', 'public', 'public/index.php') `
            -WorkingDirectory $root -NoNewWindow -PassThru `
            -RedirectStandardError $serverLog
    }
    finally {
        Remove-Item Env:TRUSTED_PROXIES, Env:APP_BASE_URL -ErrorAction SilentlyContinue
    }

    $ready = $false
    for ($i = 0; $i -lt 20; $i++) {
        Start-Sleep -Milliseconds 500
        try {
            $response = Invoke-WebRequest -Uri "http://127.0.0.1:$Port/login" -UseBasicParsing -TimeoutSec 3
            if ($response.StatusCode -eq 200) { $ready = $true; break }
        } catch { }
    }
    if (-not $ready) {
        throw "The web server did not answer on port $Port. See $serverLog"
    }
    Set-Content -LiteralPath $addressFile -Value $publicUrl -Encoding ascii

    Write-Host ''
    Write-Host '  The system is online at:' -ForegroundColor Green
    Write-Host "      $publicUrl" -ForegroundColor Green
    Write-Host ''
    Write-Host "  Sign-in page:    $publicUrl/login"
    Write-Host "  On this laptop:  http://127.0.0.1:$Port/login  (shows the QR code to scan)"
    Write-Host ''
    Write-Host '  Keep this window open. Press Ctrl+C or close it to take the site offline.'
    Write-Host '  A new address is issued each time this starts.'
    Write-Host ''

    if (-not $NoBrowser) { Start-Process "http://127.0.0.1:$Port/login" }

    while (-not $tunnel.HasExited -and -not $server.HasExited) {
        Start-Sleep -Seconds 2
        # Heartbeat: the login page trusts the address only while this file keeps being refreshed.
        Set-Content -LiteralPath $addressFile -Value $publicUrl -Encoding ascii
    }
    if ($tunnel.HasExited) { Write-Warning "The tunnel stopped. See $tunnelLog" }
    if ($server.HasExited) { Write-Warning "The web server stopped. See $serverLog" }
}
finally {
    foreach ($process in @($tunnel, $server)) {
        if ($process -and -not $process.HasExited) {
            Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue
        }
    }
    Remove-Item -LiteralPath $addressFile -ErrorAction SilentlyContinue
    Write-Host 'Tunnel closed and web server stopped. The site is offline.'
}
