#!/bin/bash

################################################################################
# Supernova Management - Proxmox LXC Container Creation Script
################################################################################
# Questo script crea un container LXC su Proxmox ottimizzato per Supernova
# Management, con tutte le risorse necessarie preconfigurate.
################################################################################

set -e

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configurazione predefinita
CTID=${CTID:-200}
HOSTNAME=${HOSTNAME:-supernova-mgmt}
TEMPLATE=${TEMPLATE:-ubuntu-24.04-standard}
STORAGE=${STORAGE:-local-lvm}
DISK_SIZE=${DISK_SIZE:-32}
CORES=${CORES:-4}
MEMORY=${MEMORY:-8192}
SWAP=${SWAP:-2048}
BRIDGE=${BRIDGE:-vmbr0}
IP_ADDRESS=${IP_ADDRESS:-dhcp}
GATEWAY=${GATEWAY:-}
PASSWORD=${PASSWORD:-}
SSH_KEY_FILE=${SSH_KEY_FILE:-}

# Banner
echo -e "${BLUE}"
cat << "EOF"
╔═══════════════════════════════════════════════════════════════╗
║                                                               ║
║          SUPERNOVA MANAGEMENT - PROXMOX DEPLOYER             ║
║                                                               ║
║               Automatic LXC Container Setup                   ║
║                                                               ║
╚═══════════════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"

# Funzione per log
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Funzione per richiedere input
prompt_input() {
    local prompt="$1"
    local default="$2"
    local result

    read -p "$(echo -e ${YELLOW}${prompt}${NC} [${default}]: )" result
    echo "${result:-$default}"
}

# Verifica se siamo su Proxmox host
if ! command -v pct &> /dev/null; then
    log_error "Questo script deve essere eseguito su un host Proxmox!"
    exit 1
fi

log_info "Benvenuto nel wizard di setup Supernova Management su Proxmox"
echo

# Configurazione interattiva
log_info "=== CONFIGURAZIONE CONTAINER ==="
CTID=$(prompt_input "CT ID" "$CTID")
HOSTNAME=$(prompt_input "Hostname" "$HOSTNAME")
STORAGE=$(prompt_input "Storage" "$STORAGE")
DISK_SIZE=$(prompt_input "Dimensione disco (GB)" "$DISK_SIZE")
CORES=$(prompt_input "CPU cores" "$CORES")
MEMORY=$(prompt_input "RAM (MB)" "$MEMORY")
SWAP=$(prompt_input "SWAP (MB)" "$SWAP")
echo

log_info "=== CONFIGURAZIONE RETE ==="
BRIDGE=$(prompt_input "Network bridge" "$BRIDGE")
IP_ADDRESS=$(prompt_input "IP Address (dhcp o CIDR es. 192.168.1.100/24)" "$IP_ADDRESS")

if [ "$IP_ADDRESS" != "dhcp" ]; then
    GATEWAY=$(prompt_input "Gateway" "$GATEWAY")
fi

echo

# Password
if [ -z "$PASSWORD" ]; then
    log_warning "Impostazione password root del container"
    read -s -p "$(echo -e ${YELLOW}Password root:${NC} )" PASSWORD
    echo
fi

# Verifica se container esiste già
if pct status $CTID &> /dev/null; then
    log_error "Container $CTID esiste già!"
    read -p "$(echo -e ${YELLOW}Vuoi eliminarlo e ricrearlo?${NC} [y/N]: )" confirm
    if [[ $confirm == [yY] ]]; then
        log_warning "Eliminazione container $CTID..."
        pct stop $CTID 2>/dev/null || true
        pct destroy $CTID
        log_success "Container eliminato"
    else
        log_info "Operazione annullata"
        exit 0
    fi
fi

# Lista template disponibili
log_info "Template disponibili:"
pveam available | grep ubuntu | tail -5

# Verifica template
if ! pveam list $STORAGE | grep -q $TEMPLATE; then
    log_warning "Template $TEMPLATE non trovato. Download in corso..."
    pveam download $STORAGE $TEMPLATE
fi

# Creazione container
log_info "Creazione container LXC..."

CREATE_CMD="pct create $CTID $STORAGE:vztmpl/${TEMPLATE}.tar.zst \
    --hostname $HOSTNAME \
    --cores $CORES \
    --memory $MEMORY \
    --swap $SWAP \
    --storage $STORAGE \
    --rootfs $STORAGE:$DISK_SIZE \
    --net0 name=eth0,bridge=$BRIDGE,firewall=1"

# Aggiungi IP se non DHCP
if [ "$IP_ADDRESS" != "dhcp" ]; then
    CREATE_CMD="$CREATE_CMD,ip=$IP_ADDRESS"
    if [ -n "$GATEWAY" ]; then
        CREATE_CMD="$CREATE_CMD,gw=$GATEWAY"
    fi
else
    CREATE_CMD="$CREATE_CMD,ip=dhcp"
fi

# Aggiungi password
CREATE_CMD="$CREATE_CMD --password '$PASSWORD'"

# Features utili per Docker
CREATE_CMD="$CREATE_CMD \
    --features nesting=1,keyctl=1 \
    --unprivileged 1 \
    --onboot 1"

eval $CREATE_CMD

log_success "Container $CTID creato con successo!"

# Aggiungi SSH key se fornita
if [ -n "$SSH_KEY_FILE" ] && [ -f "$SSH_KEY_FILE" ]; then
    log_info "Aggiunta chiave SSH..."
    pct set $CTID --ssh-public-keys "$SSH_KEY_FILE"
    log_success "Chiave SSH configurata"
fi

# Avvio container
log_info "Avvio container..."
pct start $CTID

# Attendi che il container sia pronto
log_info "Attendo che il container sia pronto..."
sleep 10

# Configurazione DNS
log_info "Configurazione DNS..."
pct exec $CTID -- bash -c "echo 'nameserver 8.8.8.8' > /etc/resolv.conf"
pct exec $CTID -- bash -c "echo 'nameserver 8.8.4.4' >> /etc/resolv.conf"

log_success "Container configurato!"
echo

# Riepilogo
log_info "=== RIEPILOGO CONFIGURAZIONE ==="
echo "CT ID:          $CTID"
echo "Hostname:       $HOSTNAME"
echo "IP Address:     $IP_ADDRESS"
echo "CPU Cores:      $CORES"
echo "RAM:            ${MEMORY}MB"
echo "Disk:           ${DISK_SIZE}GB"
echo "Storage:        $STORAGE"
echo

log_success "Container LXC creato con successo!"
log_info "Prossimi passi:"
echo "  1. Accedi al container: pct enter $CTID"
echo "  2. Esegui lo script di installazione: /root/install-supernova.sh"
echo
log_info "Oppure copia lo script di installazione:"
echo "  pct push $CTID ./install-supernova.sh /root/install-supernova.sh"
echo "  pct exec $CTID -- chmod +x /root/install-supernova.sh"
echo "  pct exec $CTID -- /root/install-supernova.sh"
