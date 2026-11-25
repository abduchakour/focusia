# Sauvegarde FocusIA : base de données (mysqldump) + fichiers déposés (storage/app/private).
# Résultat : backups/focusia-AAAAMMJJ-HHMM.zip ; les 10 dernières sauvegardes sont gardées.
# Utilisation : composer backup   (ou : powershell -ExecutionPolicy Bypass -File scripts/backup.ps1)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

# Lecture de .env (DB_*)
$envValues = @{}
Get-Content (Join-Path $root '.env') | Where-Object { $_ -match '^\s*([A-Z_]+)\s*=\s*(.*)\s*$' } | ForEach-Object {
    $envValues[$Matches[1]] = $Matches[2].Trim('"')
}
$database = $envValues['DB_DATABASE']
$user = if ($envValues['DB_USERNAME']) { $envValues['DB_USERNAME'] } else { 'root' }
$password = $envValues['DB_PASSWORD']
$dbHost = if ($envValues['DB_HOST']) { $envValues['DB_HOST'] } else { '127.0.0.1' }
$port = if ($envValues['DB_PORT']) { $envValues['DB_PORT'] } else { '3306' }

$mysqldump = @('C:\xampp\mysql\bin\mysqldump.exe', 'mysqldump') | Where-Object { Get-Command $_ -ErrorAction SilentlyContinue } | Select-Object -First 1
if (-not $mysqldump) { throw "mysqldump introuvable : installez XAMPP (MariaDB) ou ajoutez mysqldump au PATH." }

$stamp = Get-Date -Format 'yyyyMMdd-HHmm'
$backups = Join-Path $root 'backups'
$work = Join-Path $backups "focusia-$stamp"
New-Item -ItemType Directory -Force $work | Out-Null

Write-Host "Sauvegarde de la base « $database »..."
$dumpArgs = @("--host=$dbHost", "--port=$port", "--user=$user", '--single-transaction', '--routines', '--default-character-set=utf8mb4', "--result-file=$work\database.sql", $database)
if ($password) { $dumpArgs = @("--password=$password") + $dumpArgs }
& $mysqldump @dumpArgs
if ($LASTEXITCODE -ne 0) { throw "mysqldump a échoué (MariaDB est-il démarré dans XAMPP ?)." }

$files = Join-Path $root 'storage\app\private'
if (Test-Path $files) {
    Write-Host 'Copie des fichiers déposés...'
    Copy-Item $files (Join-Path $work 'files') -Recurse
}

$zip = "$work.zip"
Compress-Archive -Path "$work\*" -DestinationPath $zip -Force
Remove-Item $work -Recurse -Force

# On garde les 10 sauvegardes les plus récentes.
Get-ChildItem $backups -Filter 'focusia-*.zip' | Sort-Object LastWriteTime -Descending | Select-Object -Skip 10 | Remove-Item -Force

$size = [math]::Round((Get-Item $zip).Length / 1MB, 1)
Write-Host "Sauvegarde terminée : $zip ($size Mo)" -ForegroundColor Green
Write-Host 'Restaurer : dézipper, puis  mysql -u root focusia < database.sql  et recopier files\ dans storage\app\private\'
