#!/bin/bash

################################################################################
# Nextcloud - Automatic Installation Script for Proxmox LXC
################################################################################
# Installa Nextcloud su Docker con:
# - Disco storage configurabile (NON formatta, solo crea cartelle)
# - PostgreSQL database
# - Redis cache
# - Tailscale integration
# - SSL ready (Let's Encrypt o self-signed)
# - Integrazione rete privata Proxmox
################################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
MAGENTA='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[✓]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[⚠]${NC} $1"; }
log_error() { echo -e "${RED}[✗]${NC} $1"; }
log_step() { echo; echo -e "${MAGENTA}▶▶▶ $1${NC}"; echo; }

# Configurazione
INSTALL_DIR="/opt/nextcloud"
STORAGE_MOUNT="/mnt/shared-storage"
NEXTCLOUD_DATA_DIR=""
SUPERNOVA_TEMP_DIR=""
INSTALL_TAILSCALE=false
DOMAIN=""
USE_SSL=false

# Banner
clear
echo -e "${CYAN}"
cat << "EOF"
╔═══════════════════════════════════════════════════════════════════════════╗
║                                                                           ║
║              ☁️  NEXTCLOUD - AUTO INSTALLER FOR PROXMOX                  ║
║                                                                           ║
║                   Storage Condiviso con Supernova                        ║
║                                                                           ║
╚═══════════════════════════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"

# Verifica root
if [ "$EUID" -ne 0 ]; then
    log_error "Questo script deve essere eseguito come root"
    exit 1
fi

# Verifica sistema
if ! grep -q "Ubuntu" /etc/os-release; then
    log_warning "Questo script è ottimizzato per Ubuntu 24.04"
fi

log_step "CONFIGURAZIONE STORAGE"

log_info "Dischi disponibili:"
lsblk -o NAME,SIZE,TYPE,MOUNTPOINT,FSTYPE | grep -v "loop"
echo

log_warning "⚠️  IMPORTANTE: Il disco NON verrà formattato!"
log_info "Verrà creata solo una cartella 'nextcloud/' sui tuoi dati esistenti"
log_info "Default: /dev/sda1 (HDD 4TB principale)"
echo

read -p "$(echo -e ${YELLOW}Path dove montare lo storage [/mnt/shared-storage]:${NC} )" storage_input
STORAGE_MOUNT=${storage_input:-"/mnt/shared-storage"}

# Verifica se path esiste
if [ ! -d "$STORAGE_MOUNT" ]; then
    log_warning "Directory $STORAGE_MOUNT non esiste"

    read -p "$(echo -e ${YELLOW}Vuoi specificare un disco da montare? [y/N]:${NC} )" mount_disk

    if [[ "$mount_disk" =~ ^[Yy]$ ]]; then
        echo
        log_info "Default: /dev/sda1 (HDD 4TB principale)"
        read -p "$(echo -e ${YELLOW}Device del disco [/dev/sda1]:${NC} )" disk_device
        disk_device=${disk_device:-"/dev/sda1"}

        if [ ! -b "$disk_device" ]; then
            log_error "Device $disk_device non trovato!"
            exit 1
        fi

        # Verifica filesystem
        FSTYPE=$(lsblk -no FSTYPE "$disk_device")
        if [ -z "$FSTYPE" ]; then
            log_error "Nessun filesystem trovato su $disk_device"
            log_info "Il disco deve avere già un filesystem (ext4, xfs, etc.)"
            exit 1
        fi

        log_info "Filesystem rilevato: $FSTYPE"

        # Crea mount point
        mkdir -p "$STORAGE_MOUNT"

        # Mount temporaneo per test
        log_info "Test mount..."
        if mount "$disk_device" "$STORAGE_MOUNT"; then
            log_success "Mount riuscito!"

            # Mostra contenuto
            log_info "Contenuto attuale del disco:"
            du -sh "$STORAGE_MOUNT"/* 2>/dev/null || echo "  (disco vuoto o root files)"

            # Aggiungi a fstab
            log_info "Aggiunta mount permanente a /etc/fstab..."

            # Backup fstab
            cp /etc/fstab /etc/fstab.backup.$(date +%Y%m%d-%H%M%S)

            # Aggiungi entry se non esiste
            if ! grep -q "$disk_device" /etc/fstab; then
                echo "$disk_device $STORAGE_MOUNT $FSTYPE defaults 0 2" >> /etc/fstab
                log_success "Mount permanente configurato"
            fi
        else
            log_error "Impossibile montare $disk_device"
            exit 1
        fi
    else
        # Crea directory locale
        mkdir -p "$STORAGE_MOUNT"
        log_warning "Usando directory locale $STORAGE_MOUNT (non persistente dopo reboot)"
    fi
else
    log_success "Directory $STORAGE_MOUNT già esistente"

    # Mostra spazio disponibile
    log_info "Spazio disco:"
    df -h "$STORAGE_MOUNT" | tail -1
fi

# Crea struttura directory
log_info "Creazione struttura directory..."

NEXTCLOUD_DATA_DIR="${STORAGE_MOUNT}/nextcloud"
SUPERNOVA_TEMP_DIR="${STORAGE_MOUNT}/supernova-temp"

mkdir -p "$NEXTCLOUD_DATA_DIR"
mkdir -p "$SUPERNOVA_TEMP_DIR/public"
mkdir -p "$SUPERNOVA_TEMP_DIR/temp"

log_success "Directory create:"
echo "  📁 Nextcloud data:    $NEXTCLOUD_DATA_DIR"
echo "  📁 Supernova temp:    $SUPERNOVA_TEMP_DIR"
echo

# Salva config
cat > "$STORAGE_MOUNT/.storage-config" << STORAGE_EOF
# Storage Configuration
# Generated: $(date)
STORAGE_MOUNT=$STORAGE_MOUNT
NEXTCLOUD_DATA_DIR=$NEXTCLOUD_DATA_DIR
SUPERNOVA_TEMP_DIR=$SUPERNOVA_TEMP_DIR
STORAGE_EOF

log_success "Configurazione storage salvata"

log_step "CONFIGURAZIONE NEXTCLOUD"

# Domain
read -p "$(echo -e ${YELLOW}Dominio Nextcloud (es. nextcloud.local) [nextcloud.local]:${NC} )" domain_input
DOMAIN=${domain_input:-"nextcloud.local"}

# SSL
read -p "$(echo -e ${YELLOW}Vuoi configurare SSL/HTTPS? [y/N]:${NC} )" ssl_choice
if [[ "$ssl_choice" =~ ^[Yy]$ ]]; then
    USE_SSL=true
fi

# Tailscale
read -p "$(echo -e ${YELLOW}Vuoi installare Tailscale? [Y/n]:${NC} )" tailscale_choice
tailscale_choice=${tailscale_choice:-y}
if [[ "$tailscale_choice" =~ ^[Yy]$ ]]; then
    INSTALL_TAILSCALE=true
fi

log_success "Configurazione completata! Avvio installazione..."
sleep 2

################################################################################
# INSTALLAZIONE
################################################################################

log_step "1/7 - AGGIORNAMENTO SISTEMA"
apt-get update
apt-get upgrade -y
log_success "Sistema aggiornato"

log_step "2/7 - INSTALLAZIONE DIPENDENZE"
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
    net-tools
log_success "Dipendenze installate"

log_step "3/7 - INSTALLAZIONE DOCKER"
if command -v docker &> /dev/null; then
    log_warning "Docker già installato"
else
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg

    echo \
      "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
      $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
      tee /etc/apt/sources.list.d/docker.list > /dev/null

    apt-get update
    apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

    systemctl enable docker
    systemctl start docker

    log_success "Docker installato: $(docker --version)"
fi

log_step "4/7 - INSTALLAZIONE TAILSCALE"
if [ "$INSTALL_TAILSCALE" = true ]; then
    if command -v tailscale &> /dev/null; then
        log_warning "Tailscale già installato"
    else
        curl -fsSL https://tailscale.com/install.sh | sh
        log_success "Tailscale installato"
        log_warning "Dopo l'installazione esegui: tailscale up"
    fi
fi

log_step "5/7 - SETUP NEXTCLOUD"

# Crea directory installazione
mkdir -p "$INSTALL_DIR"
cd "$INSTALL_DIR"

# Genera password sicure
POSTGRES_PASSWORD=$(openssl rand -base64 16 | tr -d "=+/" | cut -c1-16)
REDIS_PASSWORD=$(openssl rand -base64 16 | tr -d "=+/" | cut -c1-16)
NEXTCLOUD_ADMIN_PASSWORD=$(openssl rand -base64 16 | tr -d "=+/" | cut -c1-16)

# Crea .env
cat > "$INSTALL_DIR/.env" << ENV_EOF
# Nextcloud Configuration
# Generated: $(date)

# Domain
NEXTCLOUD_DOMAIN=$DOMAIN
NEXTCLOUD_TRUSTED_DOMAINS=$DOMAIN localhost 10.0.100.20

# Admin
NEXTCLOUD_ADMIN_USER=admin
NEXTCLOUD_ADMIN_PASSWORD=$NEXTCLOUD_ADMIN_PASSWORD

# Database
POSTGRES_DB=nextcloud
POSTGRES_USER=nextcloud
POSTGRES_PASSWORD=$POSTGRES_PASSWORD

# Redis
REDIS_PASSWORD=$REDIS_PASSWORD

# Storage
NEXTCLOUD_DATA_DIR=$NEXTCLOUD_DATA_DIR

# Tailscale (se attivo)
TAILSCALE_ENABLED=$INSTALL_TAILSCALE

# SSL
USE_SSL=$USE_SSL
ENV_EOF

log_success "File .env creato"

log_warning "⚠️  CREDENZIALI GENERATE (salvale!):"
echo "  Admin Username:     admin"
echo "  Admin Password:     $NEXTCLOUD_ADMIN_PASSWORD"
echo "  Postgres Password:  $POSTGRES_PASSWORD"
echo "  Redis Password:     $REDIS_PASSWORD"
echo
echo "Premi INVIO per continuare..."
read

# Scarica docker-compose
log_info "Download docker-compose.yml..."

cat > "$INSTALL_DIR/docker-compose.yml" << 'DOCKER_EOF'
version: '3.8'

services:
  postgres:
    image: postgres:16-alpine
    container_name: nextcloud_postgres
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${POSTGRES_DB}
      POSTGRES_USER: ${POSTGRES_USER}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - postgres_data:/var/lib/postgresql/data
    networks:
      - nextcloud
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER}"]
      interval: 10s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    container_name: nextcloud_redis
    restart: unless-stopped
    command: redis-server --requirepass ${REDIS_PASSWORD}
    volumes:
      - redis_data:/data
    networks:
      - nextcloud
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 3s
      retries: 5

  nextcloud:
    image: nextcloud:latest
    container_name: nextcloud_app
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    environment:
      - POSTGRES_HOST=postgres
      - POSTGRES_DB=${POSTGRES_DB}
      - POSTGRES_USER=${POSTGRES_USER}
      - POSTGRES_PASSWORD=${POSTGRES_PASSWORD}
      - REDIS_HOST=redis
      - REDIS_HOST_PASSWORD=${REDIS_PASSWORD}
      - NEXTCLOUD_ADMIN_USER=${NEXTCLOUD_ADMIN_USER}
      - NEXTCLOUD_ADMIN_PASSWORD=${NEXTCLOUD_ADMIN_PASSWORD}
      - NEXTCLOUD_TRUSTED_DOMAINS=${NEXTCLOUD_TRUSTED_DOMAINS}
      - OVERWRITEPROTOCOL=https
      - TRUSTED_PROXIES=10.0.100.0/24
    volumes:
      - nextcloud_app:/var/www/html
      - ${NEXTCLOUD_DATA_DIR}:/var/www/html/data
    networks:
      - nextcloud
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/status.php"]
      interval: 30s
      timeout: 10s
      retries: 3

networks:
  nextcloud:
    driver: bridge

volumes:
  postgres_data:
    driver: local
  redis_data:
    driver: local
  nextcloud_app:
    driver: local
DOCKER_EOF

log_success "docker-compose.yml creato"

log_step "6/7 - AVVIO SERVIZI"

log_info "Pull immagini Docker..."
docker compose pull

log_info "Avvio container..."
docker compose up -d

log_info "Attendo che Nextcloud sia pronto (60s)..."
sleep 60

# Verifica status
if docker compose ps | grep -q "running"; then
    log_success "Nextcloud avviato!"
else
    log_error "Errori nell'avvio. Controlla i log:"
    docker compose logs
    exit 1
fi

log_step "7/7 - CONFIGURAZIONE FINALE"

# Configura Nextcloud via occ
log_info "Configurazione Nextcloud..."

# Trusted domains
docker compose exec -T nextcloud php occ config:system:set trusted_domains 0 --value="$DOMAIN"
docker compose exec -T nextcloud php occ config:system:set trusted_domains 1 --value="localhost"
docker compose exec -T nextcloud php occ config:system:set trusted_domains 2 --value="10.0.100.20"

# Redis
docker compose exec -T nextcloud php occ config:system:set redis host --value="redis"
docker compose exec -T nextcloud php occ config:system:set redis port --value="6379"
docker compose exec -T nextcloud php occ config:system:set redis password --value="$REDIS_PASSWORD"
docker compose exec -T nextcloud php occ config:system:set memcache.locking --value="\OC\Memcache\Redis"
docker compose exec -T nextcloud php occ config:system:set memcache.distributed --value="\OC\Memcache\Redis"

# Performance tuning
docker compose exec -T nextcloud php occ config:system:set default_phone_region --value="IT"
docker compose exec -T nextcloud php occ config:system:set maintenance_window_start --value="3"

# Background jobs
docker compose exec -T nextcloud php occ background:cron

log_success "Nextcloud configurato"

# Crea script di gestione
cat > /usr/local/bin/nextcloud << 'NCSCRIPT'
#!/bin/bash
cd /opt/nextcloud
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
        docker compose logs -f "${2:-nextcloud}"
        ;;
    update)
        docker compose pull
        docker compose up -d
        docker compose exec nextcloud php occ upgrade
        ;;
    occ)
        shift
        docker compose exec nextcloud php occ "$@"
        ;;
    *)
        echo "Uso: nextcloud {start|stop|restart|logs|update|occ}"
        exit 1
        ;;
esac
NCSCRIPT

chmod +x /usr/local/bin/nextcloud
log_success "Comando 'nextcloud' creato"

# Crea cron per background jobs
cat > /etc/cron.d/nextcloud << 'CRON_EOF'
*/5 * * * * root docker exec nextcloud_app php -f /var/www/html/cron.php
CRON_EOF

log_success "Cron job configurato"

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

log_success "Nextcloud installato con successo!"
echo

log_info "📍 INDIRIZZI ACCESSO:"
echo "  🌐 Nextcloud:         http://$(hostname -I | awk '{print $1}')"
echo "  🌐 Domain:            http://$DOMAIN"

if [ "$INSTALL_TAILSCALE" = true ]; then
    echo "  🔐 Tailscale:         (avvia con 'tailscale up')"
fi

echo
log_info "🔐 CREDENZIALI:"
echo "  Username:             admin"
echo "  Password:             $NEXTCLOUD_ADMIN_PASSWORD"
echo

log_info "📁 STORAGE:"
echo "  Mount point:          $STORAGE_MOUNT"
echo "  Nextcloud data:       $NEXTCLOUD_DATA_DIR"
echo "  Supernova temp:       $SUPERNOVA_TEMP_DIR"
echo "  Spazio disponibile:   $(df -h $STORAGE_MOUNT | tail -1 | awk '{print $4}')"
echo

log_info "🎮 COMANDI UTILI:"
echo "  nextcloud start       - Avvia servizi"
echo "  nextcloud stop        - Ferma servizi"
echo "  nextcloud restart     - Riavvia"
echo "  nextcloud logs        - Visualizza log"
echo "  nextcloud update      - Aggiorna Nextcloud"
echo "  nextcloud occ <cmd>   - Esegui comando occ"
echo

log_info "🔗 INTEGRAZIONE SUPERNOVA:"
echo "  Nel file .env di Supernova aggiungi:"
echo "  NEXTCLOUD_URL=http://nextcloud.local"
echo "  NEXTCLOUD_USERNAME=admin"
echo "  NEXTCLOUD_PASSWORD=$NEXTCLOUD_ADMIN_PASSWORD"
echo "  SHARED_STORAGE_PATH=$SUPERNOVA_TEMP_DIR"
echo

if [ "$INSTALL_TAILSCALE" = true ]; then
    log_warning "⚠️  NON DIMENTICARE:"
    echo "  1. Attiva Tailscale: tailscale up"
    echo "  2. Configura firewall se necessario"
fi

log_info "📚 PROSSIMI PASSI:"
echo "  1. Accedi a Nextcloud e completa setup"
echo "  2. Installa app consigliate (Calendar, Contacts, etc.)"
echo "  3. Configura External Storage per Supernova"
echo "  4. Setup rete privata con setup-network.sh"
echo

log_success "Installazione completata! ☁️"
