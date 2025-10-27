#!/bin/bash

################################################################################
# Supernova Management - Automatic Installation Script
################################################################################
# Installa automaticamente tutte le dipendenze e configura Supernova Management
# su un container LXC Ubuntu 24.04
#
# Supporta:
# - Docker & Docker Compose
# - Ollama (AI locale) - opzionale
# - Tailscale - opzionale
# - Nextcloud integration
# - Backup automatici
################################################################################

set -e

# Colori
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
MAGENTA='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configurazione
INSTALL_DIR="/opt/supernova-management"
DATA_DIR="/opt/supernova-data"
BACKUP_DIR="/opt/supernova-backups"
INSTALL_OLLAMA=false
INSTALL_TAILSCALE=false
OLLAMA_MODEL="qwen2.5:7b"
GIT_BRANCH="main"

# Banner
clear
echo -e "${CYAN}"
cat << "EOF"
╔═══════════════════════════════════════════════════════════════════════════╗
║                                                                           ║
║              ✨ SUPERNOVA MANAGEMENT - AUTO INSTALLER ✨                  ║
║                                                                           ║
║                     Installazione Automatica Completa                    ║
║                                                                           ║
╚═══════════════════════════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"

# Funzioni di log
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[⚠]${NC} $1"
}

log_error() {
    echo -e "${RED}[✗]${NC} $1"
}

log_step() {
    echo
    echo -e "${MAGENTA}▶▶▶ $1${NC}"
    echo
}

prompt_yes_no() {
    local prompt="$1"
    local default="${2:-n}"
    local response

    if [ "$default" = "y" ]; then
        read -p "$(echo -e ${YELLOW}${prompt}${NC} [Y/n]: )" response
        response=${response:-y}
    else
        read -p "$(echo -e ${YELLOW}${prompt}${NC} [y/N]: )" response
        response=${response:-n}
    fi

    [[ "$response" =~ ^[Yy]$ ]]
}

# Verifica se è root
if [ "$EUID" -ne 0 ]; then
    log_error "Questo script deve essere eseguito come root"
    exit 1
fi

# Verifica sistema operativo
if ! grep -q "Ubuntu" /etc/os-release; then
    log_warning "Questo script è ottimizzato per Ubuntu 24.04"
    if ! prompt_yes_no "Vuoi continuare comunque?"; then
        exit 1
    fi
fi

# Wizard configurazione
log_step "CONFIGURAZIONE INSTALLAZIONE"

log_info "Risorse sistema rilevate:"
echo "  RAM totale:    $(free -h | awk '/^Mem:/ {print $2}')"
echo "  CPU cores:     $(nproc)"
echo "  Disk libero:   $(df -h / | awk 'NR==2 {print $4}')"
echo

if prompt_yes_no "Vuoi installare Ollama (AI locale) in questo container?" "y"; then
    INSTALL_OLLAMA=true
    log_info "Modelli AI consigliati per il tuo setup:"
    echo "  1. qwen2.5:7b       - Migliore qualità (richiede 6GB RAM)"
    echo "  2. phi3:mini        - Veloce e leggero (3GB RAM)"
    echo "  3. gemma2:9b        - Ottimo compromesso (8GB RAM)"
    echo "  4. llama3.2:3b      - Ultra leggero (2GB RAM)"
    echo
    read -p "$(echo -e ${YELLOW}Scegli modello [1-4]:${NC} )" model_choice
    case $model_choice in
        1) OLLAMA_MODEL="qwen2.5:7b" ;;
        2) OLLAMA_MODEL="phi3:mini" ;;
        3) OLLAMA_MODEL="gemma2:9b" ;;
        4) OLLAMA_MODEL="llama3.2:3b" ;;
        *) OLLAMA_MODEL="qwen2.5:7b" ;;
    esac
    log_info "Modello selezionato: $OLLAMA_MODEL"
fi

if prompt_yes_no "Vuoi installare Tailscale (VPN mesh)?"; then
    INSTALL_TAILSCALE=true
fi

# Repository Git
read -p "$(echo -e ${YELLOW}URL repository Git [https://github.com/tuo-user/supernova-management.git]:${NC} )" git_url
git_url=${git_url:-"https://github.com/tuo-user/supernova-management.git"}

read -p "$(echo -e ${YELLOW}Branch Git [main]:${NC} )" git_branch_input
GIT_BRANCH=${git_branch_input:-"main"}

echo
log_success "Configurazione completata! Avvio installazione..."
sleep 2

################################################################################
# INSTALLAZIONE
################################################################################

log_step "1/8 - AGGIORNAMENTO SISTEMA"
apt-get update
apt-get upgrade -y
log_success "Sistema aggiornato"

log_step "2/8 - INSTALLAZIONE DIPENDENZE BASE"
apt-get install -y \
    curl \
    wget \
    git \
    unzip \
    software-properties-common \
    apt-transport-https \
    ca-certificates \
    gnupg \
    lsb-release \
    htop \
    vim \
    net-tools \
    dnsutils \
    jq
log_success "Dipendenze base installate"

log_step "3/8 - INSTALLAZIONE DOCKER"
if command -v docker &> /dev/null; then
    log_warning "Docker già installato, skip"
else
    # Aggiungi repository Docker
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg

    echo \
      "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
      $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
      tee /etc/apt/sources.list.d/docker.list > /dev/null

    apt-get update
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

    # Avvia Docker
    systemctl enable docker
    systemctl start docker

    log_success "Docker installato: $(docker --version)"
fi

log_step "4/8 - INSTALLAZIONE OLLAMA (AI)"
if [ "$INSTALL_OLLAMA" = true ]; then
    if command -v ollama &> /dev/null; then
        log_warning "Ollama già installato"
    else
        curl -fsSL https://ollama.com/install.sh | sh

        # Avvia servizio
        systemctl enable ollama
        systemctl start ollama

        log_success "Ollama installato"
    fi

    # Download modello
    log_info "Download modello $OLLAMA_MODEL (può richiedere alcuni minuti)..."
    ollama pull $OLLAMA_MODEL
    log_success "Modello $OLLAMA_MODEL scaricato"

    # Test
    if ollama list | grep -q "$OLLAMA_MODEL"; then
        log_success "Ollama operativo con modello $OLLAMA_MODEL"
    fi
else
    log_info "Installazione Ollama saltata"
fi

log_step "5/8 - INSTALLAZIONE TAILSCALE"
if [ "$INSTALL_TAILSCALE" = true ]; then
    if command -v tailscale &> /dev/null; then
        log_warning "Tailscale già installato"
    else
        curl -fsSL https://tailscale.com/install.sh | sh
        log_success "Tailscale installato"
        log_warning "Dopo l'installazione esegui: tailscale up"
    fi
else
    log_info "Installazione Tailscale saltata"
fi

log_step "6/8 - CLONAZIONE REPOSITORY"
if [ -d "$INSTALL_DIR" ]; then
    log_warning "Directory $INSTALL_DIR esiste già"
    if prompt_yes_no "Vuoi eliminare e riclonare?"; then
        rm -rf "$INSTALL_DIR"
    else
        log_info "Usando repository esistente"
    fi
fi

if [ ! -d "$INSTALL_DIR" ]; then
    log_info "Clonazione da $git_url (branch: $GIT_BRANCH)..."
    git clone -b "$GIT_BRANCH" "$git_url" "$INSTALL_DIR"
    log_success "Repository clonato"
else
    log_info "Aggiornamento repository..."
    cd "$INSTALL_DIR"
    git pull
    log_success "Repository aggiornato"
fi

cd "$INSTALL_DIR"

log_step "7/8 - CONFIGURAZIONE AMBIENTE"

# Crea directory necessarie
mkdir -p "$DATA_DIR"
mkdir -p "$BACKUP_DIR"
mkdir -p "$INSTALL_DIR/storage/app/public"
mkdir -p "$INSTALL_DIR/storage/logs"
mkdir -p "$INSTALL_DIR/bootstrap/cache"

# Copia .env.example se .env non esiste
if [ ! -f "$INSTALL_DIR/.env" ]; then
    cp "$INSTALL_DIR/.env.example" "$INSTALL_DIR/.env"
    log_info "File .env creato da .env.example"

    # Genera APP_KEY casuale
    APP_KEY=$(openssl rand -base64 32)
    sed -i "s|APP_KEY=|APP_KEY=base64:$APP_KEY|g" "$INSTALL_DIR/.env"

    # Genera password casuali per database
    DB_PASSWORD=$(openssl rand -base64 16 | tr -d "=+/" | cut -c1-16)
    REDIS_PASSWORD=$(openssl rand -base64 16 | tr -d "=+/" | cut -c1-16)
    MEILI_KEY=$(openssl rand -base64 24 | tr -d "=+/" | cut -c1-24)

    sed -i "s|DB_PASSWORD=password|DB_PASSWORD=$DB_PASSWORD|g" "$INSTALL_DIR/.env"
    sed -i "s|REDIS_PASSWORD=null|REDIS_PASSWORD=$REDIS_PASSWORD|g" "$INSTALL_DIR/.env"
    sed -i "s|MEILISEARCH_KEY=masterKey|MEILISEARCH_KEY=$MEILI_KEY|g" "$INSTALL_DIR/.env"

    # Configura Ollama se installato
    if [ "$INSTALL_OLLAMA" = true ]; then
        sed -i "s|OLLAMA_MODEL=.*|OLLAMA_MODEL=$OLLAMA_MODEL|g" "$INSTALL_DIR/.env"
        sed -i "s|OLLAMA_API_URL=http://localhost:11434|OLLAMA_API_URL=http://host.docker.internal:11434|g" "$INSTALL_DIR/.env"
    fi

    # Configura percorsi per produzione
    sed -i "s|APP_ENV=local|APP_ENV=production|g" "$INSTALL_DIR/.env"
    sed -i "s|APP_DEBUG=true|APP_DEBUG=false|g" "$INSTALL_DIR/.env"
    sed -i "s|SYNCTHING_ROOT_PATH=.*|SYNCTHING_ROOT_PATH=$DATA_DIR|g" "$INSTALL_DIR/.env"

    log_success "File .env configurato"
    log_warning "Password generate (salvale!):"
    echo "  DB_PASSWORD:    $DB_PASSWORD"
    echo "  REDIS_PASSWORD: $REDIS_PASSWORD"
    echo "  MEILI_KEY:      $MEILI_KEY"
    echo
    echo "Premi INVIO per continuare..."
    read
else
    log_info "File .env esistente, skip configurazione"
fi

# Permessi
chown -R www-data:www-data "$INSTALL_DIR/storage"
chown -R www-data:www-data "$INSTALL_DIR/bootstrap/cache"
chmod -R 775 "$INSTALL_DIR/storage"
chmod -R 775 "$INSTALL_DIR/bootstrap/cache"

log_success "Permessi configurati"

log_step "8/8 - AVVIO SERVIZI DOCKER"

# Copia docker-compose.production.yml se esiste
if [ -f "$INSTALL_DIR/deployment/docker-compose.production.yml" ]; then
    cp "$INSTALL_DIR/deployment/docker-compose.production.yml" "$INSTALL_DIR/docker-compose.yml"
    log_info "Usando docker-compose.production.yml"
fi

# Build e avvio
log_info "Build immagini Docker..."
docker compose build

log_info "Avvio container..."
docker compose up -d

# Attendi che i container siano pronti
log_info "Attendo che i servizi siano pronti (30s)..."
sleep 30

# Esegui setup Laravel
log_info "Setup applicazione Laravel..."
docker compose exec -T app composer install --optimize-autoloader --no-dev
docker compose exec -T app php artisan key:generate --force
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan db:seed --force
docker compose exec -T app php artisan storage:link
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
docker compose exec -T app php artisan filament:optimize

log_success "Applicazione configurata"

# Verifica servizi
log_step "VERIFICA SERVIZI"
echo "Container attivi:"
docker compose ps
echo

# Test connessione
if curl -f http://localhost:80 > /dev/null 2>&1; then
    log_success "✓ Supernova Management: http://localhost"
else
    log_warning "⚠ Web server non raggiungibile"
fi

if [ "$INSTALL_OLLAMA" = true ]; then
    if curl -f http://localhost:11434/api/tags > /dev/null 2>&1; then
        log_success "✓ Ollama: http://localhost:11434"
    else
        log_warning "⚠ Ollama non raggiungibile"
    fi
fi

# Crea script di gestione
cat > /usr/local/bin/supernova << 'EOFSCRIPT'
#!/bin/bash
cd /opt/supernova-management
case "$1" in
    start)
        docker compose up -d
        ;;
    stop)
        docker compose down
        ;;
    restart)
        docker compose restart
        ;;
    logs)
        docker compose logs -f "${2:-app}"
        ;;
    update)
        git pull
        docker compose build
        docker compose up -d
        docker compose exec app php artisan migrate --force
        docker compose exec app php artisan optimize:clear
        ;;
    backup)
        /opt/supernova-management/deployment/backup.sh
        ;;
    *)
        echo "Uso: supernova {start|stop|restart|logs|update|backup}"
        exit 1
        ;;
esac
EOFSCRIPT

chmod +x /usr/local/bin/supernova
log_success "Comando 'supernova' creato"

################################################################################
# COMPLETAMENTO
################################################################################

echo
echo -e "${GREEN}"
cat << "EOF"
╔═══════════════════════════════════════════════════════════════════════════╗
║                                                                           ║
║                  ✨ INSTALLAZIONE COMPLETATA! ✨                          ║
║                                                                           ║
╚═══════════════════════════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"

log_success "Supernova Management è stato installato con successo!"
echo

log_info "📍 INDIRIZZI SERVIZI:"
echo "  🌐 Supernova Management:  http://$(hostname -I | awk '{print $1}')"
echo "  📧 Mailpit (test email):  http://$(hostname -I | awk '{print $1}'):8025"
echo "  🔍 Meilisearch:           http://$(hostname -I | awk '{print $1}'):7700"

if [ "$INSTALL_OLLAMA" = true ]; then
    echo "  🤖 Ollama API:            http://$(hostname -I | awk '{print $1}'):11434"
    echo "     Modello attivo:        $OLLAMA_MODEL"
fi

echo
log_info "🎮 COMANDI UTILI:"
echo "  supernova start        - Avvia tutti i servizi"
echo "  supernova stop         - Ferma tutti i servizi"
echo "  supernova restart      - Riavvia i servizi"
echo "  supernova logs [app]   - Visualizza log"
echo "  supernova update       - Aggiorna applicazione"
echo "  supernova backup       - Backup completo"
echo

log_info "📁 PERCORSI IMPORTANTI:"
echo "  Applicazione:  $INSTALL_DIR"
echo "  Dati:          $DATA_DIR"
echo "  Backup:        $BACKUP_DIR"
echo

log_info "🔐 PRIMO ACCESSO:"
echo "  1. Vai su http://$(hostname -I | awk '{print $1}')/admin"
echo "  2. Crea il primo utente amministratore"
echo "  3. Configura Nextcloud in Settings (se necessario)"
echo

if [ "$INSTALL_TAILSCALE" = true ]; then
    log_warning "⚠️  NON DIMENTICARE: Attiva Tailscale con 'tailscale up'"
fi

log_info "📚 DOCUMENTAZIONE:"
echo "  $INSTALL_DIR/deployment/README.md"
echo

log_success "Installazione completata! 🎉"
