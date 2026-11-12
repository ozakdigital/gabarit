@echo off
rem Commande `gabarit` pour Windows (cmd et PowerShell).
rem
rem Execute la CLI dans l'image Docker `gabarit-cli`, avec le dossier courant monte :
rem `gabarit new mon-app` cree donc le projet dans le dossier ou vous etes.
rem
rem Commandes propres au lanceur :
rem   gabarit self-update   met a jour Gabarit (git pull) et reconstruit l'image
setlocal
if "%GABARIT_IMAGE%"=="" set "GABARIT_IMAGE=gabarit-cli"
set "GABARIT_HOME=%~dp0.."

where docker >nul 2>nul
if errorlevel 1 (
    echo gabarit : Docker est introuvable. Installez Docker Desktop. 1>&2
    exit /b 2
)

if /i "%~1"=="self-update" (
    if exist "%GABARIT_HOME%\.git" git -C "%GABARIT_HOME%" pull --ff-only
    call :build
    if errorlevel 1 exit /b 2
    echo Gabarit est a jour. 1>&2
    exit /b 0
)

docker image inspect %GABARIT_IMAGE% >nul 2>nul
if errorlevel 1 (
    call :build
    if errorlevel 1 exit /b 2
)

rem Pseudo-terminal pour les menus ; GABARIT_NO_TTY=1 pour les scripts (entree redirigee).
set "TTY_FLAG=-it"
if "%GABARIT_NO_TTY%"=="1" set "TTY_FLAG=-i"
docker run --rm %TTY_FLAG% -v "%CD%:/work" -w /work %GABARIT_IMAGE% %*
exit /b %ERRORLEVEL%

:build
echo Construction de l'image %GABARIT_IMAGE%... 1>&2
docker build -q -f "%GABARIT_HOME%\docker\cli\Dockerfile" -t %GABARIT_IMAGE% "%GABARIT_HOME%" >nul
exit /b %ERRORLEVEL%
