# Configure php.ini for portable PHP
$IniPath = "$PSScriptRoot\..\php\php.ini"
$DevIni = "$PSScriptRoot\..\php\php.ini-development"

if (Test-Path $DevIni) {
    Copy-Item $DevIni $IniPath -Force
    $Content = Get-Content $IniPath -Raw
    
    # Enable extensions and set extension directory
    $Content = $Content.Replace(';extension_dir = "ext"', 'extension_dir = "ext"')
    $Content = $Content.Replace(';extension=pdo_sqlite', 'extension=pdo_sqlite')
    $Content = $Content.Replace(';extension=openssl', 'extension=openssl')
    $Content = $Content.Replace(';extension=mbstring', 'extension=mbstring')
    $Content = $Content.Replace(';extension=fileinfo', 'extension=fileinfo')
    $Content = $Content.Replace(';extension=gd', 'extension=gd')
    
    Set-Content $IniPath $Content -NoNewline
    Write-Host "php.ini configured successfully."
} else {
    Write-Error "php.ini-development not found."
}
