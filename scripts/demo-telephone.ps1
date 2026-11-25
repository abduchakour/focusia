# Démo sur téléphone : ouvre FocusIA en HTTPS sur Internet via un tunnel Cloudflare gratuit
# (sans compte, sans carte bancaire). Le HTTPS est nécessaire pour le mode hors ligne et
# l'installation de l'application sur le téléphone.
# Avant : lancer FocusIA dans une autre fenêtre avec  composer start
# Utilisation : composer telephone   (ou : powershell -ExecutionPolicy Bypass -File scripts/demo-telephone.ps1)

$ErrorActionPreference = 'Stop'
$port = 8001

$cloudflared = Get-Command cloudflared -ErrorAction SilentlyContinue
if (-not $cloudflared) {
    $local = Join-Path $env:ProgramFiles 'cloudflared\cloudflared.exe'
    if (Test-Path $local) { $cloudflared = $local }
}
if (-not $cloudflared) {
    Write-Host 'cloudflared n''est pas installé. Installation gratuite :' -ForegroundColor Yellow
    Write-Host '    winget install --id Cloudflare.cloudflared'
    Write-Host 'Puis fermez et rouvrez le terminal, et relancez cette commande.'
    exit 1
}

try {
    Invoke-WebRequest "http://127.0.0.1:$port/up" -UseBasicParsing -TimeoutSec 5 | Out-Null
} catch {
    Write-Host "FocusIA ne répond pas sur le port $port. Lancez d'abord  composer start  dans une autre fenêtre." -ForegroundColor Yellow
    exit 1
}

Write-Host ''
Write-Host 'Ouverture du tunnel... Cherchez la ligne https://....trycloudflare.com ci-dessous' -ForegroundColor Cyan
Write-Host 'et ouvrez cette adresse sur le téléphone (Chrome). Ctrl+C pour arrêter.' -ForegroundColor Cyan
Write-Host ''
& $cloudflared tunnel --url "http://127.0.0.1:$port"
