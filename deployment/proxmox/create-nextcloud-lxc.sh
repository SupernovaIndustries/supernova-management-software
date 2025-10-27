#!/bin/bash

################################################################################
# Nextcloud - Proxmox LXC Container Creation Script
################################################################################
# Crea un container LXC su Proxmox ottimizzato per Nextcloud
################################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Configurazione predefinita
CTID=${CTID:-201}
HOSTNAME=${HOSTNAME:-nextcloud}
TEMPLATE=${TEMPLATE:-ubuntu-24.04-standard}
STORAGE=${STORAGE:-local-lvm}
DISK_SIZE=${DISK_SIZE:-40}  # Disk container (piccolo, i dati vanno sul disco condiviso)
CORES=${CORES:-4}
MEMORY=${MEMORY:-12288}  # 12GB
SWAP=${SWAP:-2048}
BRIDGE=${BRIDGE:-vmbr0}
IP_ADDRESS=${IP_ADDRESS:-dhcp}
GATEWAY=${GATEWAY:-}
PASSWORD=${PASSWORD:-}
STORAGE_DISK=${STORAGE_DISK:-}  # Disco aggiuntivo per dati

# Banner
echo -e "${BLUE}"
cat << "EOF"
╔═══════════════════════════════════════════════════════════════╗
║                                                               ║
║          NEXTCLOUD - PROXMOX LXC DEPLOYER                    ║
║                                                               ║
║               Automatic Container Setup                       ║
║                                                               ║
╚═══════════════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"

prompt_input() {
    local prompt="$1"
    local default="$2"
    local result
    read -p "$(echo -e ${YELLOW}${prompt}${NC} [${default}]: )" result
    echo "${result:-$default}"
}

# Verifica Proxmox
if ! command -v pct &> /dev/null; then
    log_error "Questo script deve essere eseguito su un host Proxmox!"
    exit 1
fi

log_info "=== CONFIGURAZIONE CONTAINER NEXTCLOUD ==="
CTID=$(prompt_input "CT ID" "$CTID")
HOSTNAME=$(prompt_input "Hostname" "$HOSTNAME")
STORAGE=$(prompt_input "Storage" "$STORAGE")
DISK_SIZE=$(prompt_input "Dimensione disco sistema (GB)" "$DISK_SIZE")
CORES=$(prompt_input "CPU cores" "$CORES")
MEMORY=$(prompt_input "RAM (MB)" "$MEMORY")
SWAP=$(prompt_input "SWAP (MB)" "$SWAP")
echo

log_info "=== CONFIGURAZIONE RETE ==="
BRIDGE=$(prompt_input "Network bridge" "$BRIDGE")
IP_ADDRESS=$(prompt_input "IP Address (dhcp o CIDR)" "$IP_ADDRESS")

if [ "$IP_ADDRESS" != "dhcp" ]; then
    GATEWAY=$(prompt_input "Gateway" "$GATEWAY")
fi
echo

log_info "=== STORAGE AGGIUNTIVO (OPZIONALE) ==="
log_info "Per i dati Nextcloud puoi usare un disco dedicato"
read -p "$(echo -e ${YELLOW}Vuoi aggiungere un disco per i dati? [y/N]:${NC} )" add_disk

if [[ "$add_disk" =~ ^[Yy]$ ]]; then
    log_info "Dischi disponibili:"
    lsblk -d -o NAME,SIZE,TYPE | grep disk

    STORAGE_DISK=$(prompt_input "Device disco (es. /dev/sdb)" "")

    if [ -n "$STORAGE_DISK" ] && [ -b "$STORAGE_DISK" ]; then
        log_success "Disco $STORAGE_DISK verrà passato al container"
    else
        log_warning "Disco non valido, skip"
        STORAGE_DISK=""
    fi
fi
echo

# Password
if [ -z "$PASSWORD" ]; then
    log_warning "Impostazione password root del container"
    read -s -p "$(echo -e ${YELLOW}Password root:${NC} )" PASSWORD
    echo
fi

# Verifica se container esiste
if pct status $CTID &> /dev/null; then
    log_error "Container $CTID esiste già!"
    read -p "$(echo -e ${YELLOW}Vuoi eliminarlo e ricrearlo? [y/N]:${NC} )" confirm
    if [[ $confirm == [yY] ]]; then
        log_warning "Eliminazione container $CTID..."
        pct stop $CTID 2>/dev/null || true
        pct destroy $CTID
        log_success "Container eliminato"
    else
        exit 0
    fi
fi

# Verifica template
if ! pveam list $STORAGE | grep -q $TEMPLATE; then
    log_warning "Template $TEMPLATE non trovato. Download..."
    pveam download $STORAGE $TEMPLATE
fi

# Creazione container
log_info "Creazione container LXC per Nextcloud..."

CREATE_CMD="pct create $CTID $STORAGE:vztmpl/${TEMPLATE}.tar.zst \
    --hostname $HOSTNAME \
    --cores $CORES \
    --memory $MEMORY \
    --swap $SWAP \
    --storage $STORAGE \
    --rootfs $STORAGE:$DISK_SIZE \
    --net0 name=eth0,bridge=$BRIDGE,firewall=1"

if [ "$IP_ADDRESS" != "dhcp" ]; then
    CREATE_CMD="$CREATE_CMD,ip=$IP_ADDRESS"
    if [ -n "$GATEWAY" ]; then
        CREATE_CMD="$CREATE_CMD,gw=$GATEWAY"
    fi
else
    CREATE_CMD="$CREATE_CMD,ip=dhcp"
fi

CREATE_CMD="$CREATE_CMD \
    --password '$PASSWORD' \
    --features nesting=1,keyctl=1 \
    --unprivileged 1 \
    --onboot 1"

eval $CREATE_CMD
log_success "Container $CTID creato!"

# Passthrough disco aggiuntivo (se specificato)
if [ -n "$STORAGE_DISK" ]; then
    log_info "Aggiunta disco dati al container..."

    # Aggiungi mountpoint
    pct set $CTID -mp0 $STORAGE_DISK,mp=/mnt/shared-storage

    log_success "Disco $STORAGE_DISK aggiunto come /mnt/shared-storage"
fi

# Avvio container
log_info "Avvio container..."
pct start $CTID
sleep 10

# DNS
pct exec $CTID -- bash -c "echo 'nameserver 8.8.8.8' > /etc/resolv.conf"
pct exec $CTID -- bash -c "echo 'nameserver 8.8.4.4' >> /etc/resolv.conf"

log_success "Container $CTID configurato!"
echo

# Riepilogo
log_info "=== RIEPILOGO CONFIGURAZIONE ==="
echo "CT ID:          $CTID"
echo "Hostname:       $HOSTNAME"
echo "IP Address:     $IP_ADDRESS"
echo "CPU Cores:      $CORES"
echo "RAM:            ${MEMORY}MB"
echo "Disk sistema:   ${DISK_SIZE}GB"
if [ -n "$STORAGE_DISK" ]; then
    echo "Disk dati:      $STORAGE_DISK → /mnt/shared-storage"
fi
echo

log_success "Container Nextcloud creato!"
log_info "Prossimi passi:"
echo "  1. Copia script installazione:"
echo "     cd /root/supernova-management-software/deployment"
echo "     pct push $CTID nextcloud/install-nextcloud.sh /root/install-nextcloud.sh"
echo
echo "  2. Rendi eseguibile:"
echo "     pct exec $CTID -- chmod +x /root/install-nextcloud.sh"
echo
echo "  3. Esegui installazione:"
echo "     pct exec $CTID -- /root/install-nextcloud.sh"
echo
