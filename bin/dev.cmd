@echo off
rem Commandes de developpement de Gabarit sous Windows, sans make ni PHP : Docker suffit.
rem Memes noms que le Makefile.   Usage : bin\dev <commande> [VAR=valeur...]   (bin\dev help)
setlocal
cd /d "%~dp0.."
set COMPOSE_BAKE=false
set "CMD=%~1"
if "%CMD%"=="" set CMD=help
rem Le reste de la ligne tel quel : cmd couperait FW=laravel en deux avec %%1, %%2...
set "ARGS="
for /f "tokens=1,* delims= " %%a in ("%*") do set "ARGS= %%b"
if "%ARGS%"==" " set "ARGS="

if /i "%CMD%"=="up" goto up
if /i "%CMD%"=="down" goto down
if /i "%CMD%"=="sh" goto shell
if /i "%CMD%"=="web" goto web
if /i "%CMD%"=="web-down" goto webdown
if /i "%CMD%"=="web-dev" goto webdev
if /i "%CMD%"=="web-dev-down" goto webdevdown
if /i "%CMD%"=="hooks" goto hooks
if /i "%CMD%"=="test-e2e" goto e2e
if /i "%CMD%"=="help" goto help
docker compose run --rm -T php make %CMD%%ARGS%
goto end

:up
docker compose up -d --build php || goto end
docker compose run --rm -T php make install
goto end

:down
docker compose --profile web-dev down
goto end

:shell
docker compose run --rm php sh
goto end

:web
docker build -q -t gabarit-web-api --target api -f apps/web/Dockerfile . >nul || goto end
docker build -q -t gabarit-web-web --target web -f apps/web/Dockerfile . >nul || goto end
if not defined APP_SECRET set APP_SECRET=gabarit-dev-secret
docker compose -f apps/web/compose.yaml up -d
if not defined WEB_PORT set WEB_PORT=8090
echo Site : http://localhost:%WEB_PORT%
goto end

:webdown
set APP_SECRET=x
docker compose -f apps/web/compose.yaml down
goto end

:webdev
docker compose build php || goto end
docker compose --profile web-dev up -d api-dev ui-dev
if not defined UI_DEV_PORT set UI_DEV_PORT=5173
echo Interface : http://localhost:%UI_DEV_PORT%   (journaux : docker compose logs -f ui-dev)
goto end

:webdevdown
docker compose --profile web-dev rm -sf api-dev ui-dev
goto end

:hooks
git config core.hooksPath .githooks
echo Verifications avant commit activees (contourner une fois : git commit --no-verify)
goto end

:e2e
if not defined WEB_PORT set WEB_PORT=8090
docker run --rm --add-host=host.docker.internal:host-gateway -e "E2E_URL=http://host.docker.internal:%WEB_PORT%" -v "%CD%\apps\web\ui:/ui" -v /ui/node_modules -w /ui mcr.microsoft.com/playwright:v1.65.0-noble sh -c "npm ci --no-audit --no-fund && npx playwright test"
goto end

:help
echo Usage : bin\dev ^<commande^> [VAR=valeur...]
echo.
docker compose run --rm -T php make help
goto end

:end
endlocal
