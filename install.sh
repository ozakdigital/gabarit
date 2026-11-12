#!/usr/bin/env sh
# Installation de la CLI Gabarit — Linux et macOS.
#
#   curl -fsSL https://raw.githubusercontent.com/ozakdigital/gabarit/main/install.sh | sh
#
# Variables facultatives :
#   GABARIT_HOME     dossier d'installation          (défaut : ~/.gabarit)
#   GABARIT_BIN_DIR  dossier où créer la commande     (défaut : ~/.local/bin)
#   GABARIT_SOURCE   copie locale de Gabarit à utiliser au lieu de cloner le dépôt
#   GABARIT_REPO     dépôt git à cloner
set -eu

REPO=${GABARIT_REPO:-https://github.com/ozakdigital/gabarit.git}
INSTALL_DIR=${GABARIT_SOURCE:-${GABARIT_HOME:-$HOME/.gabarit}}
BIN_DIR=${GABARIT_BIN_DIR:-$HOME/.local/bin}

info() { printf '\033[36m→\033[0m %s\n' "$1"; }
fail() { printf '\033[31m✘ %s\033[0m\n' "$1" >&2; exit 1; }

command -v docker >/dev/null 2>&1 || fail "Docker est introuvable. Installez Docker Desktop (macOS) ou Docker Engine (Linux) : https://docs.docker.com/get-docker/"
docker info >/dev/null 2>&1 || fail "Docker est installé mais ne répond pas. Démarrez Docker (ou ajoutez votre utilisateur au groupe docker), puis relancez."

if [ -n "${GABARIT_SOURCE:-}" ]; then
    [ -f "$INSTALL_DIR/docker/cli/Dockerfile" ] || fail "GABARIT_SOURCE ne pointe pas vers une copie de Gabarit : $INSTALL_DIR"
    info "Utilisation de la copie locale $INSTALL_DIR"
else
    command -v git >/dev/null 2>&1 || fail "git est introuvable : installez-le puis relancez."
    if [ -d "$INSTALL_DIR/.git" ]; then
        info "Mise à jour de Gabarit dans $INSTALL_DIR"
        git -C "$INSTALL_DIR" pull --ff-only --quiet
    else
        info "Téléchargement de Gabarit dans $INSTALL_DIR"
        git clone --quiet --depth 1 "$REPO" "$INSTALL_DIR"
    fi
fi

info "Construction de l'image Docker gabarit-cli (quelques minutes la première fois)"
docker build -q -f "$INSTALL_DIR/docker/cli/Dockerfile" -t gabarit-cli "$INSTALL_DIR" >/dev/null

chmod +x "$INSTALL_DIR/bin/gabarit"
mkdir -p "$BIN_DIR"
# Petit script relais plutôt qu'un lien symbolique (Git Bash copie les liens au lieu de les créer).
rm -f "$BIN_DIR/gabarit"
printf '#!/usr/bin/env sh\nexec "%s/bin/gabarit" "$@"\n' "$INSTALL_DIR" > "$BIN_DIR/gabarit"
chmod +x "$BIN_DIR/gabarit"
info "Commande installée : $BIN_DIR/gabarit"

case ":$PATH:" in
    *":$BIN_DIR:"*) ;;
    *)
        printf '\n\033[33m!\033[0m %s n'"'"'est pas dans votre PATH. Ajoutez cette ligne à ~/.bashrc ou ~/.zshrc :\n' "$BIN_DIR"
        printf '    export PATH="%s:$PATH"\n\n' "$BIN_DIR"
        ;;
esac

VERSION=$("$BIN_DIR/gabarit" --version 2>/dev/null | head -n 1)

# Logo : le « G » de l'icône Gabarit, en blocs violets.
P='\033[1;38;5;135m'; R='\033[0m'; B='\033[1m'
printf '\n'
printf "  ${P} ▄██████▄${R}\n"
printf "  ${P}██▀    ▀▀${R}\n"
printf "  ${P}██   ▄▄▄▄${R}    ${B}%s${R}\n" "${VERSION:-Gabarit}"
printf "  ${P}██▄    ██${R}    Générateur de projets PHP en marque blanche\n"
printf "  ${P} ▀██████▀${R}\n\n"
printf '\033[32m✔ Gabarit est installé.\033[0m Essayez : gabarit new mon-app\n'
