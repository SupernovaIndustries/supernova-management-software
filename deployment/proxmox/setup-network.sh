#!/bin/bash

################################################################################
# Proxmox Container Network Configuration
################################################################################
# Configura il networking tra container LXC su Proxmox per permettere
# comunicazione tra Supernova Management, Nextcloud e Ollama
#
# Network Architecture:
# - vmbr1: Bridge interno privato (10.0.100.0/24)
# - Ogni container ha 2 interfacce:
#   - eth0: WAN/Internet (vmbr0)
#   - eth1: LAN privata (vmbr1)
################################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
MAGENTA='\033[0;35m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[✓]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[⚠]${NC} $1"; }
log_error() { echo -e "${RED}[✗]${NC} $1"; }
log_step() { echo; echo -e "${MAGENTA}▶▶▶ $1${NC}"; echo; }

# Banner
echo -e "${BLUE}"
cat << "EOF"
╔═══════════════════════════════════════════════════════════════╗
║                                                               ║
║         🌐 Proxmox Container Network Configuration           ║
║                                                               ║
║              Private Network for Service Communication        ║
║                                                               ║
╚═══════════════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"

# Verifica Proxmox
if ! command -v pct &> /dev/null; then
    log_error "Questo script deve essere eseguito su un host Proxmox!"
    exit 1
fi

# Configurazione
PRIVATE_BRIDGE="vmbr1"
PRIVATE_SUBNET="10.0.100.0/24"
PRIVATE_GATEWAY="10.0.100.1"

# Container IDs (personalizza questi)
SUPERNOVA_CTID=200
SUPERNOVA_IP="10.0.100.10"

NEXTCLOUD_CTID=201
NEXTCLOUD_IP="10.0.100.20"

OLLAMA_CTID=202
OLLAMA_IP="10.0.100.30"

log_step "1/4 - CONFIGURAZIONE BRIDGE PRIVATO"

# Verifica se vmbr1 esiste già
if ip link show "$PRIVATE_BRIDGE" &> /dev/null; then
    log_warning "Bridge $PRIVATE_BRIDGE già esistente"
else
    log_info "Creazione bridge privato $PRIVATE_BRIDGE..."

    # Aggiungi bridge alla configurazione di rete Proxmox
    cat >> /etc/network/interfaces << BRIDGE_EOF

# Private bridge for container communication
auto $PRIVATE_BRIDGE
iface $PRIVATE_BRIDGE inet static
    address $PRIVATE_GATEWAY
    netmask 255.255.255.0
    bridge-ports none
    bridge-stp off
    bridge-fd 0
    # NAT per accesso internet dai container (opzionale)
    post-up echo 1 > /proc/sys/net/ipv4/ip_forward
    post-up iptables -t nat -A POSTROUTING -s '$PRIVATE_SUBNET' -o vmbr0 -j MASQUERADE
    post-down iptables -t nat -D POSTROUTING -s '$PRIVATE_SUBNET' -o vmbr0 -j MASQUERADE
BRIDGE_EOF

    # Attiva bridge
    ifup "$PRIVATE_BRIDGE"
    log_success "Bridge $PRIVATE_BRIDGE creato"
fi

# Funzione per aggiungere interfaccia privata a container
add_private_interface() {
    local ctid=$1
    local ip=$2
    local name=$3

    log_info "Configurazione rete privata per CT$ctid ($name)..."

    # Verifica se container esiste
    if ! pct status "$ctid" &> /dev/null; then
        log_warning "Container $ctid non trovato, skip"
        return
    fi

    # Ferma container
    if pct status "$ctid" | grep -q "running"; then
        log_info "Arresto temporaneo CT$ctid..."
        pct stop "$ctid"
        sleep 2
    fi

    # Aggiungi seconda interfaccia di rete (eth1)
    if pct config "$ctid" | grep -q "net1"; then
        log_warning "Interfaccia net1 già configurata su CT$ctid"
    else
        pct set "$ctid" -net1 name=eth1,bridge="$PRIVATE_BRIDGE",firewall=1,ip="$ip/24",gw="$PRIVATE_GATEWAY"
        log_success "Interfaccia privata aggiunta a CT$ctid"
    fi

    # Avvia container
    log_info "Avvio CT$ctid..."
    pct start "$ctid"
    sleep 5

    log_success "CT$ctid configurato: $ip"
}

log_step "2/4 - CONFIGURAZIONE CONTAINER"

# Configura ogni container
add_private_interface "$SUPERNOVA_CTID" "$SUPERNOVA_IP" "Supernova Management"
add_private_interface "$NEXTCLOUD_CTID" "$NEXTCLOUD_IP" "Nextcloud"
add_private_interface "$OLLAMA_CTID" "$OLLAMA_IP" "Ollama AI"

log_step "3/4 - TEST CONNETTIVITÀ"

# Test ping tra container
test_connectivity() {
    local from_ctid=$1
    local to_ip=$2
    local to_name=$3

    if pct status "$from_ctid" | grep -q "running"; then
        if pct exec "$from_ctid" -- ping -c 2 "$to_ip" &> /dev/null; then
            log_success "CT$from_ctid → $to_name ($to_ip): OK"
        else
            log_warning "CT$from_ctid → $to_name ($to_ip): FAIL"
        fi
    fi
}

log_info "Test connettività tra container..."
echo

# Supernova → Altri
test_connectivity "$SUPERNOVA_CTID" "$NEXTCLOUD_IP" "Nextcloud"
test_connectivity "$SUPERNOVA_CTID" "$OLLAMA_IP" "Ollama"

# Nextcloud → Altri
test_connectivity "$NEXTCLOUD_CTID" "$SUPERNOVA_IP" "Supernova"
test_connectivity "$NEXTCLOUD_CTID" "$OLLAMA_IP" "Ollama"

log_step "4/4 - CONFIGURAZIONE DNS/HOSTS"

# Aggiungi entries /etc/hosts in ogni container per name resolution
configure_hosts() {
    local ctid=$1

    if ! pct status "$ctid" | grep -q "running"; then
        return
    fi

    log_info "Configurazione DNS per CT$ctid..."

    pct exec "$ctid" -- bash -c "cat >> /etc/hosts << HOSTS_EOF
# Supernova Private Network
$SUPERNOVA_IP    supernova supernova.local
$NEXTCLOUD_IP    nextcloud nextcloud.local
$OLLAMA_IP       ollama ollama.local
HOSTS_EOF"

    log_success "DNS configurato per CT$ctid"
}

configure_hosts "$SUPERNOVA_CTID"
configure_hosts "$NEXTCLOUD_CTID"
configure_hosts "$OLLAMA_CTID"

# Summary
echo
echo -e "${GREEN}╔═══════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║              ✓ Network Configuration Completed                ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════════════════════════════╝${NC}"
echo

log_info "📊 NETWORK TOPOLOGY:"
echo
echo "  ┌─────────────────────────────────────────────────┐"
echo "  │           Internet / vmbr0 (WAN)                │"
echo "  └──────────┬──────────┬──────────┬────────────────┘"
echo "             │          │          │"
echo "        ┌────┴────┐ ┌──┴─────┐ ┌──┴─────┐"
echo "        │ CT$SUPERNOVA_CTID   │ │ CT$NEXTCLOUD_CTID  │ │ CT$OLLAMA_CTID   │"
echo "        │ Supernv │ │ Nextcld│ │ Ollama │"
echo "        └────┬────┘ └────┬───┘ └───┬────┘"
echo "             │           │         │"
echo "  ┌──────────┴───────────┴─────────┴────────────────┐"
echo "  │     vmbr1 (Private LAN) - 10.0.100.0/24         │"
echo "  └─────────────────────────────────────────────────┘"
echo

log_info "📋 IP ASSIGNMENTS:"
echo "  Bridge Gateway:      $PRIVATE_GATEWAY"
echo "  Supernova (CT$SUPERNOVA_CTID):  $SUPERNOVA_IP"
echo "  Nextcloud (CT$NEXTCLOUD_CTID):  $NEXTCLOUD_IP"
echo "  Ollama (CT$OLLAMA_CTID):     $OLLAMA_IP"
echo

log_info "🔧 CONFIGURAZIONE APPLICAZIONI:"
echo
echo "  In Supernova Management (.env):"
echo "    NEXTCLOUD_URL=http://nextcloud.local"
echo "    OLLAMA_API_URL=http://ollama.local:11434"
echo
echo "  In Nextcloud:"
echo "    External Storage: http://supernova.local"
echo
echo "  Test connessione:"
echo "    pct exec $SUPERNOVA_CTID -- curl http://nextcloud.local"
echo "    pct exec $SUPERNOVA_CTID -- curl http://ollama.local:11434/api/tags"
echo

log_success "Setup networking completato!"
