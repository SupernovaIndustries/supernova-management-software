#!/bin/bash

################################################################################
# Configure SSH for Claude Code Access
################################################################################
# Configura SSH nei container LXC per permettere accesso remoto con Claude Code
# Supporta:
# - Generazione chiavi SSH
# - Configurazione SSHD sicura
# - Firewall rules
# - Tailscale integration
################################################################################

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[✓]${NC} $1"; }
log_warning() { echo -e "${YELLOW}[⚠]${NC} $1"; }
log_error() { echo -e "${RED}[✗]${NC} $1"; }

# Banner
echo -e "${BLUE}"
cat << "EOF"
╔════════════════════════════════════════════════════════════╗
║                                                            ║
║           🔐 SSH Configuration for Claude Code            ║
║                                                            ║
╚════════════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"

# Verifica se root
if [ "$EUID" -ne 0 ]; then
    log_error "Questo script deve essere eseguito come root"
    exit 1
fi

log_info "Sistema: $(hostname)"
log_info "IP: $(hostname -I | awk '{print $1}')"
echo

# Installazione SSH server
log_info "Installazione OpenSSH Server..."
apt-get update
apt-get install -y openssh-server

# Backup configurazione esistente
if [ -f /etc/ssh/sshd_config ]; then
    cp /etc/ssh/sshd_config /etc/ssh/sshd_config.backup.$(date +%Y%m%d-%H%M%S)
    log_info "Backup configurazione creato"
fi

# Configurazione SSHD sicura ma accessibile
log_info "Configurazione SSHD..."
cat > /etc/ssh/sshd_config << 'SSHD_EOF'
# Supernova Management - SSH Configuration for Claude Code
# Ottimizzato per accesso remoto sicuro

# Network
Port 22
AddressFamily any
ListenAddress 0.0.0.0

# Security
Protocol 2
PermitRootLogin prohibit-password
PubkeyAuthentication yes
PasswordAuthentication no
PermitEmptyPasswords no
ChallengeResponseAuthentication no
UsePAM yes
X11Forwarding no
PrintMotd no
AcceptEnv LANG LC_*

# Key-based authentication
AuthorizedKeysFile .ssh/authorized_keys .ssh/authorized_keys2

# Performance
ClientAliveInterval 60
ClientAliveCountMax 3
TCPKeepAlive yes

# Logging
SyslogFacility AUTH
LogLevel INFO

# Subsystems
Subsystem sftp /usr/lib/openssh/sftp-server
SSHD_EOF

log_success "SSHD configurato"

# Crea utente per Claude Code se non esiste
CLAUDE_USER="claude"
if ! id "$CLAUDE_USER" &>/dev/null; then
    log_info "Creazione utente '$CLAUDE_USER' per Claude Code..."
    useradd -m -s /bin/bash "$CLAUDE_USER"

    # Aggiungi a gruppi necessari
    usermod -aG docker,sudo "$CLAUDE_USER"

    log_success "Utente '$CLAUDE_USER' creato"
else
    log_warning "Utente '$CLAUDE_USER' già esistente"
fi

# Setup directory SSH per utente claude
CLAUDE_HOME="/home/$CLAUDE_USER"
mkdir -p "$CLAUDE_HOME/.ssh"
chmod 700 "$CLAUDE_HOME/.ssh"

# Genera chiave SSH per il container (per connessioni in uscita)
if [ ! -f "$CLAUDE_HOME/.ssh/id_ed25519" ]; then
    log_info "Generazione chiave SSH per container..."
    ssh-keygen -t ed25519 -C "claude@$(hostname)" -f "$CLAUDE_HOME/.ssh/id_ed25519" -N ""
    log_success "Chiave SSH generata"
fi

# Crea file authorized_keys
touch "$CLAUDE_HOME/.ssh/authorized_keys"
chmod 600 "$CLAUDE_HOME/.ssh/authorized_keys"

# Permessi corretti
chown -R "$CLAUDE_USER:$CLAUDE_USER" "$CLAUDE_HOME/.ssh"

log_info "Per aggiungere la tua chiave SSH per Claude Code:"
echo
echo -e "${YELLOW}Dalla tua macchina locale, esegui:${NC}"
echo "  cat ~/.ssh/id_ed25519_supernova.pub | ssh root@$(hostname -I | awk '{print $1}') 'cat >> /home/$CLAUDE_USER/.ssh/authorized_keys'"
echo
echo -e "${YELLOW}Oppure copia manualmente la chiave pubblica in:${NC}"
echo "  /home/$CLAUDE_USER/.ssh/authorized_keys"
echo

# Configura sudo senza password per claude
log_info "Configurazione sudo per utente claude..."
echo "$CLAUDE_USER ALL=(ALL) NOPASSWD:ALL" > /etc/sudoers.d/claude
chmod 440 /etc/sudoers.d/claude
log_success "Sudo configurato"

# Restart SSH
log_info "Riavvio servizio SSH..."
systemctl enable ssh
systemctl restart ssh
log_success "SSH server attivo"

# Firewall (UFW) - permetti SSH
if command -v ufw &> /dev/null; then
    log_info "Configurazione firewall..."
    ufw allow 22/tcp comment 'SSH for Claude Code'
    log_success "Firewall configurato"
fi

# Test configurazione
log_info "Test configurazione SSH..."
if sshd -t; then
    log_success "Configurazione SSH valida"
else
    log_error "Errore nella configurazione SSH!"
    exit 1
fi

# Informazioni Tailscale
if command -v tailscale &> /dev/null; then
    TAILSCALE_IP=$(tailscale ip -4 2>/dev/null || echo "non configurato")
    log_success "Tailscale rilevato: $TAILSCALE_IP"
    echo
    log_info "Connessione via Tailscale:"
    echo "  ssh $CLAUDE_USER@$TAILSCALE_IP"
fi

# Summary
echo
echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║                  ✓ Configurazione Completata              ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
echo
log_info "📋 INFORMAZIONI CONNESSIONE:"
echo "  Sistema:        $(hostname)"
echo "  IP Locale:      $(hostname -I | awk '{print $1}')"
if command -v tailscale &> /dev/null; then
    echo "  IP Tailscale:   $TAILSCALE_IP"
fi
echo "  Utente SSH:     $CLAUDE_USER"
echo "  Porta SSH:      22"
echo
log_info "🔑 CONNESSIONE DA CLAUDE CODE:"
echo "  ssh $CLAUDE_USER@$(hostname -I | awk '{print $1}')"
echo
log_info "🔐 CHIAVE PUBBLICA DEL CONTAINER:"
cat "$CLAUDE_HOME/.ssh/id_ed25519.pub"
echo
log_info "📝 PROSSIMI PASSI:"
echo "  1. Aggiungi la tua chiave pubblica SSH al file authorized_keys"
echo "  2. Testa connessione: ssh $CLAUDE_USER@$(hostname -I | awk '{print $1}')"
echo "  3. Configura Claude Code per usare questo host"
echo

log_success "Setup completato!"
