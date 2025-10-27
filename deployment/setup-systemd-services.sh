#!/bin/bash

################################################################################
# Setup Systemd Services for Supernova Management
################################################################################
# Configura servizi systemd per:
# - Auto-start dei container Docker al boot
# - Monitoraggio e restart automatico
# - Logging centralizzato
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

INSTALL_DIR="/opt/supernova-management"

echo -e "${BLUE}"
cat << "EOF"
╔════════════════════════════════════════════════════════════╗
║                                                            ║
║           ⚙️  Systemd Services Configuration              ║
║                                                            ║
╚════════════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"

if [ "$EUID" -ne 0 ]; then
    log_error "Questo script deve essere eseguito come root"
    exit 1
fi

if [ ! -d "$INSTALL_DIR" ]; then
    log_error "Directory $INSTALL_DIR non trovata!"
    exit 1
fi

log_info "Creazione servizi systemd..."

# Servizio principale Docker Compose
cat > /etc/systemd/system/supernova.service << 'SERVICE_EOF'
[Unit]
Description=Supernova Management Docker Services
Requires=docker.service
After=docker.service network-online.target
Wants=network-online.target

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=/opt/supernova-management
ExecStartPre=/usr/bin/docker compose down
ExecStart=/usr/bin/docker compose up -d
ExecStop=/usr/bin/docker compose down
ExecReload=/usr/bin/docker compose restart
TimeoutStartSec=300
TimeoutStopSec=120
Restart=on-failure
RestartSec=10

[Install]
WantedBy=multi-user.target
SERVICE_EOF

log_success "Servizio supernova.service creato"

# Servizio di monitoraggio health check
cat > /etc/systemd/system/supernova-healthcheck.service << 'HEALTH_EOF'
[Unit]
Description=Supernova Management Health Check
After=supernova.service

[Service]
Type=oneshot
ExecStart=/opt/supernova-management/deployment/healthcheck.sh
StandardOutput=journal
StandardError=journal
HEALTH_EOF

# Timer per health check ogni 5 minuti
cat > /etc/systemd/system/supernova-healthcheck.timer << 'TIMER_EOF'
[Unit]
Description=Supernova Health Check Timer
Requires=supernova.service

[Timer]
OnBootSec=5min
OnUnitActiveSec=5min
Unit=supernova-healthcheck.service

[Install]
WantedBy=timers.target
TIMER_EOF

log_success "Servizio health check creato"

# Servizio backup giornaliero
cat > /etc/systemd/system/supernova-backup.service << 'BACKUP_EOF'
[Unit]
Description=Supernova Management Daily Backup
After=supernova.service

[Service]
Type=oneshot
ExecStart=/opt/supernova-management/deployment/backup.sh
StandardOutput=journal
StandardError=journal
BACKUP_EOF

# Timer per backup ogni giorno alle 3 AM
cat > /etc/systemd/system/supernova-backup.timer << 'BACKUP_TIMER_EOF'
[Unit]
Description=Supernova Daily Backup Timer

[Timer]
OnCalendar=daily
OnCalendar=*-*-* 03:00:00
Persistent=true
Unit=supernova-backup.service

[Install]
WantedBy=timers.target
BACKUP_TIMER_EOF

log_success "Servizio backup creato"

# Crea script healthcheck
cat > "$INSTALL_DIR/deployment/healthcheck.sh" << 'HEALTHCHECK_EOF'
#!/bin/bash

# Health check per servizi Supernova
set -e

INSTALL_DIR="/opt/supernova-management"
cd "$INSTALL_DIR"

# Verifica container attivi
RUNNING=$(docker compose ps --services --filter "status=running" | wc -l)
TOTAL=$(docker compose ps --services | wc -l)

if [ "$RUNNING" -lt "$TOTAL" ]; then
    echo "WARNING: Solo $RUNNING/$TOTAL container attivi"
    docker compose ps
    # Tentativo restart
    docker compose up -d
    exit 1
fi

# Verifica endpoint web
if ! curl -f -s http://localhost:80/health > /dev/null 2>&1; then
    echo "WARNING: Endpoint web non risponde"
    docker compose logs --tail=50 nginx app
    exit 1
fi

echo "OK: Tutti i servizi sono operativi ($RUNNING/$TOTAL)"
exit 0
HEALTHCHECK_EOF

chmod +x "$INSTALL_DIR/deployment/healthcheck.sh"
log_success "Script healthcheck creato"

# Ricarica systemd
log_info "Ricaricamento systemd daemon..."
systemctl daemon-reload

# Abilita servizi
log_info "Abilitazione servizi..."
systemctl enable supernova.service
systemctl enable supernova-healthcheck.timer
systemctl enable supernova-backup.timer

log_success "Servizi abilitati"

# Avvia timer
systemctl start supernova-healthcheck.timer
systemctl start supernova-backup.timer

log_success "Timer avviati"

# Summary
echo
echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║              ✓ Systemd Services Configured                 ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
echo

log_info "📋 SERVIZI INSTALLATI:"
echo "  ✓ supernova.service           - Servizio principale"
echo "  ✓ supernova-healthcheck       - Monitoraggio (ogni 5 min)"
echo "  ✓ supernova-backup            - Backup automatico (3 AM)"
echo

log_info "🎮 COMANDI SYSTEMD:"
echo "  systemctl start supernova       - Avvia servizi"
echo "  systemctl stop supernova        - Ferma servizi"
echo "  systemctl restart supernova     - Riavvia servizi"
echo "  systemctl status supernova      - Stato servizi"
echo "  journalctl -u supernova -f      - Log in tempo reale"
echo

log_info "⏲️  TIMER ATTIVI:"
systemctl list-timers supernova-*
echo

log_info "🔄 AUTO-START:"
echo "  I servizi si avvieranno automaticamente al boot del sistema"
echo

log_success "Configurazione completata!"
