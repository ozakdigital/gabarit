# Installation de la CLI Gabarit - Windows (PowerShell 5.1 ou plus).
#
#   irm https://raw.githubusercontent.com/ozakdigital/gabarit/main/install.ps1 | iex
#
# Variables facultatives :
#   GABARIT_HOME     dossier d'installation        (defaut : %USERPROFILE%\.gabarit)
#   GABARIT_SOURCE   copie locale de Gabarit a utiliser au lieu de cloner le depot
#   GABARIT_REPO     depot git a cloner
#   GABARIT_NO_PATH  1 = ne pas modifier le PATH de l'utilisateur (tests, CI)

$ErrorActionPreference = 'Stop'

function Info($message) { Write-Host "-> $message" -ForegroundColor Cyan }
function Fail($message) { Write-Host "X $message" -ForegroundColor Red; exit 1 }

$repo = if ($env:GABARIT_REPO) { $env:GABARIT_REPO } else { 'https://github.com/ozakdigital/gabarit.git' }
if ($env:GABARIT_SOURCE) {
    $installDir = $env:GABARIT_SOURCE
} elseif ($env:GABARIT_HOME) {
    $installDir = $env:GABARIT_HOME
} else {
    $installDir = Join-Path $env:USERPROFILE '.gabarit'
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Fail 'Docker est introuvable. Installez Docker Desktop : https://docs.docker.com/desktop/setup/install/windows-install/'
}
docker info *> $null
if ($LASTEXITCODE -ne 0) { Fail 'Docker est installe mais ne repond pas. Demarrez Docker Desktop, puis relancez.' }

if ($env:GABARIT_SOURCE) {
    if (-not (Test-Path (Join-Path $installDir 'docker\cli\Dockerfile'))) { Fail "GABARIT_SOURCE ne pointe pas vers une copie de Gabarit : $installDir" }
    Info "Utilisation de la copie locale $installDir"
} else {
    if (-not (Get-Command git -ErrorAction SilentlyContinue)) { Fail 'git est introuvable : installez Git for Windows (https://git-scm.com) puis relancez.' }
    if (Test-Path (Join-Path $installDir '.git')) {
        Info "Mise a jour de Gabarit dans $installDir"
        git -C $installDir pull --ff-only --quiet
    } else {
        Info "Telechargement de Gabarit dans $installDir"
        git clone --quiet --depth 1 $repo $installDir
    }
    if ($LASTEXITCODE -ne 0) { Fail 'Echec de git.' }
}

Info "Construction de l'image Docker gabarit-cli (quelques minutes la premiere fois)"
$env:COMPOSE_BAKE = 'false'
docker build -q -f (Join-Path $installDir 'docker\cli\Dockerfile') -t gabarit-cli $installDir | Out-Null
if ($LASTEXITCODE -ne 0) { Fail 'Echec de la construction de l''image Docker.' }

# Ajoute bin\ au PATH de l'utilisateur (permanent, sauf GABARIT_NO_PATH=1) et de la session courante.
$binDir = Join-Path $installDir 'bin'
$userPath = [Environment]::GetEnvironmentVariable('Path', 'User')
if (-not $userPath) { $userPath = '' }
if (-not $env:GABARIT_NO_PATH -and (($userPath -split ';') -notcontains $binDir)) {
    [Environment]::SetEnvironmentVariable('Path', ($userPath.TrimEnd(';') + ';' + $binDir).TrimStart(';'), 'User')
    Info "Ajout de $binDir au PATH (nouveaux terminaux)"
}
if (($env:Path -split ';') -notcontains $binDir) { $env:Path = "$env:Path;$binDir" }

& (Join-Path $binDir 'gabarit.cmd') --version
Write-Host ''
Write-Host 'Gabarit est installe. Ouvrez un nouveau terminal puis essayez : gabarit new mon-app' -ForegroundColor Green
