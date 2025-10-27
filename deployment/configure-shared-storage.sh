#!/bin/bash

################################################################################
# Configure Shared Storage for Supernova Management
################################################################################
# Configura lo storage condiviso tra Nextcloud e Supernova
# - Monta disco condiviso (opzionale)
# - Configura .env per usare storage condiviso per temp/public
# - I file permanenti (fatture, documenti) continuano su Nextcloud
################################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[✓]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[⚠]${NC} $1"; }
log_error() { echo -e "${RED}[✗]${NC} $1"; }

INSTALL_DIR="/opt/supernova-management"
STORAGE_MOUNT="/mnt/shared-storage"
SUPERNOVA_TEMP_DIR=""

echo -e "${CYAN}"
cat << "EOF"
╔═══════════════════════════════════════════════════════════════╗
║                                                               ║
║      🔗 Supernova - Shared Storage Configuration            ║
║                                                               ║
╚═══════════════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"

if [ "$EUID" -ne 0 ]; then
    log_error "Questo script deve essere eseguito come root"
    exit 1
fi

if [ ! -d "$INSTALL_DIR" ]; then
    log_error "Supernova non trovato in $INSTALL_DIR"
    log_info "Esegui prima install-supernova.sh"
    exit 1
fi

log_info "Configurazione storage condiviso"
echo

# Opzione 1: Rileva storage config da Nextcloud
if [ -f "${STORAGE_MOUNT}/.storage-config" ]; then
    log_success "Trovata configurazione storage da Nextcloud!"
    source "${STORAGE_MOUNT}/.storage-config"

    log_info "Storage rilevato:"
    echo "  Mount point:      $STORAGE_MOUNT"
    echo "  Nextcloud data:   $NEXTCLOUD_DATA_DIR"
    echo "  Supernova temp:   $SUPERNOVA_TEMP_DIR"
    echo

    read -p "$(echo -e ${YELLOW}Vuoi usare questa configurazione? [Y/n]:${NC} )" use_detected
    use_detected=${use_detected:-y}

    if [[ ! "$use_detected" =~ ^[Yy]$ ]]; then
        STORAGE_MOUNT=""
    fi
fi

# Opzione 2: Chiedi path manualmente
if [ -z "$SUPERNOVA_TEMP_DIR" ]; then
    read -p "$(echo -e ${YELLOW}Path storage condiviso [/mnt/shared-storage]:${NC} )" storage_input
    STORAGE_MOUNT=${storage_input:-"/mnt/shared-storage"}

    if [ ! -d "$STORAGE_MOUNT" ]; then
        log_error "Directory $STORAGE_MOUNT non esiste!"
        log_info "Opzioni:"
        echo "  1. Monta il disco prima con lo script di Nextcloud"
        echo "  2. Crea la directory manualmente"
        exit 1
    fi

    SUPERNOVA_TEMP_DIR="${STORAGE_MOUNT}/supernova-temp"
    mkdir -p "$SUPERNOVA_TEMP_DIR/public"
    mkdir -p "$SUPERNOVA_TEMP_DIR/temp"
fi

# Verifica permessi
log_info "Configurazione permessi..."
chown -R www-data:www-data "$SUPERNOVA_TEMP_DIR"
chmod -R 775 "$SUPERNOVA_TEMP_DIR"
log_success "Permessi configurati"

# Aggiorna .env
log_info "Aggiornamento configurazione .env..."

ENV_FILE="$INSTALL_DIR/.env"

if [ ! -f "$ENV_FILE" ]; then
    log_error "File .env non trovato in $INSTALL_DIR"
    exit 1
fi

# Backup .env
cp "$ENV_FILE" "${ENV_FILE}.backup.$(date +%Y%m%d-%H%M%S)"

# Aggiungi/aggiorna configurazione storage condiviso
if grep -q "SHARED_STORAGE_PATH" "$ENV_FILE"; then
    # Update esistente
    sed -i "s|SHARED_STORAGE_PATH=.*|SHARED_STORAGE_PATH=$SUPERNOVA_TEMP_DIR|g" "$ENV_FILE"
else
    # Aggiungi nuovo
    cat >> "$ENV_FILE" << STORAGE_ENV

# Shared Storage Configuration (aggiunto $(date))
# Storage temporaneo condiviso con Nextcloud
SHARED_STORAGE_PATH=$SUPERNOVA_TEMP_DIR
# I file permanenti vanno su Nextcloud via NextcloudService
STORAGE_ENV
fi

log_success "File .env aggiornato"

# Crea link simbolici per Laravel storage (opzionale)
log_info "Creazione link storage..."

cd "$INSTALL_DIR"

# Link per storage public (temp)
if [ -L "storage/app/public-temp" ] || [ -d "storage/app/public-temp" ]; then
    rm -rf "storage/app/public-temp"
fi
ln -s "$SUPERNOVA_TEMP_DIR/public" "storage/app/public-temp"

# Link per temp files
if [ -L "storage/app/temp" ] || [ -d "storage/app/temp" ]; then
    rm -rf "storage/app/temp"
fi
ln -s "$SUPERNOVA_TEMP_DIR/temp" "storage/app/temp"

log_success "Link simbolici creati"

# Info Nextcloud
log_info "Configurazione Nextcloud..."

echo
log_info "Per completare l'integrazione con Nextcloud:"
echo "  1. Assicurati che Nextcloud sia installato e configurato"
echo "  2. Aggiungi queste variabili al .env (se non presenti):"
echo
echo "     NEXTCLOUD_URL=http://nextcloud.local"
echo "     NEXTCLOUD_USERNAME=admin"
echo "     NEXTCLOUD_PASSWORD=<tua-password>"
echo "     NEXTCLOUD_BASE_PATH=/remote.php/dav/files/admin"
echo
echo "  3. Verifica connessione: php artisan tinker"
echo "     > app(\\App\\Services\\NextcloudService::class)->testConnection()"
echo

# Restart servizi se Docker è attivo
if docker compose ps >/dev/null 2>&1; then
    log_info "Riavvio servizi Docker..."
    docker compose restart
    log_success "Servizi riavviati"
fi

echo
echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║              ✓ Configurazione Completata                      ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════╝${NC}"
echo

log_success "Storage condiviso configurato!"
log_info "📁 MAPPING STORAGE:"
echo "  Storage temp/public:  $SUPERNOVA_TEMP_DIR (disco condiviso)"
echo "  File permanenti:      Nextcloud (via NextcloudService)"
echo

log_info "💾 USO STORAGE:"
echo "  ✅ Su disco condiviso:"
echo "     - File temporanei (elaborazione)"
echo "     - Cache immagini"
echo "     - Upload temporanei"
echo "     - Loghi pubblici"
echo
echo "  ✅ Su Nextcloud (gestito da Laravel):"
echo "     - Fatture (ricevute/emesse)"
echo "     - Preventivi"
echo "     - Contratti"
echo "     - Documenti progetto"
echo "     - Certificazioni"
echo

log_info "🔍 VERIFICA:"
echo "  df -h $STORAGE_MOUNT"
df -h "$STORAGE_MOUNT" | tail -1
echo

log_success "Setup completato! 🚀"
