#!/bin/bash

################################################################################
# Supernova Management - Backup & Restore Script
################################################################################
# Backup completo di:
# - Database PostgreSQL
# - Redis data
# - File applicazione
# - Configurazione .env
# - Docker volumes
# - Dati Nextcloud (opzionale)
#
# Supporta:
# - Backup incrementali
# - Compressione
# - Retention policy
# - Restore completo
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

# Configurazione
INSTALL_DIR="/opt/supernova-management"
BACKUP_DIR="/opt/supernova-backups"
DATA_DIR="/opt/supernova-data"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_NAME="supernova_backup_${TIMESTAMP}"
BACKUP_PATH="${BACKUP_DIR}/${BACKUP_NAME}"
RETENTION_DAYS=30

# Colori banner
echo -e "${MAGENTA}"
cat << "EOF"
╔═══════════════════════════════════════════════════════════════╗
║                                                               ║
║              💾 SUPERNOVA BACKUP & RESTORE                   ║
║                                                               ║
╚═══════════════════════════════════════════════════════════════╝
EOF
echo -e "${NC}"

usage() {
    echo "Uso: $0 [backup|restore|list|clean]"
    echo
    echo "Comandi:"
    echo "  backup         - Crea backup completo"
    echo "  restore FILE   - Ripristina da backup"
    echo "  list           - Lista backup disponibili"
    echo "  clean          - Rimuove backup vecchi (>$RETENTION_DAYS giorni)"
    echo
    exit 1
}

# Verifica se root
if [ "$EUID" -ne 0 ]; then
    log_error "Questo script deve essere eseguito come root"
    exit 1
fi

# Crea directory backup
mkdir -p "$BACKUP_DIR"
mkdir -p "$BACKUP_PATH"

################################################################################
# BACKUP
################################################################################
do_backup() {
    log_info "Inizio backup: $BACKUP_NAME"
    echo

    cd "$INSTALL_DIR"

    # 1. Backup database PostgreSQL
    log_info "[1/7] Backup database PostgreSQL..."
    docker compose exec -T postgres pg_dump -U supernova supernova | gzip > "${BACKUP_PATH}/postgres.sql.gz"
    log_success "Database backup: $(du -h ${BACKUP_PATH}/postgres.sql.gz | cut -f1)"

    # 2. Backup Redis
    log_info "[2/7] Backup Redis..."
    docker compose exec -T redis redis-cli --rdb /data/dump.rdb save
    docker cp supernova_redis:/data/dump.rdb "${BACKUP_PATH}/redis.rdb"
    log_success "Redis backup: $(du -h ${BACKUP_PATH}/redis.rdb | cut -f1)"

    # 3. Backup .env
    log_info "[3/7] Backup configurazione..."
    cp "${INSTALL_DIR}/.env" "${BACKUP_PATH}/.env"
    log_success "Configurazione salvata"

    # 4. Backup docker-compose
    log_info "[4/7] Backup docker-compose.yml..."
    cp "${INSTALL_DIR}/docker-compose.yml" "${BACKUP_PATH}/docker-compose.yml"
    log_success "Docker compose salvato"

    # 5. Backup storage Laravel
    log_info "[5/7] Backup storage Laravel..."
    if [ -d "${INSTALL_DIR}/storage" ]; then
        tar czf "${BACKUP_PATH}/storage.tar.gz" -C "${INSTALL_DIR}" storage
        log_success "Storage backup: $(du -h ${BACKUP_PATH}/storage.tar.gz | cut -f1)"
    fi

    # 6. Backup dati Nextcloud (se esistono)
    log_info "[6/7] Backup dati applicazione..."
    if [ -d "$DATA_DIR" ]; then
        tar czf "${BACKUP_PATH}/data.tar.gz" -C "$DATA_DIR" .
        log_success "Dati backup: $(du -h ${BACKUP_PATH}/data.tar.gz | cut -f1)"
    else
        log_warning "Directory dati non trovata, skip"
    fi

    # 7. Metadata backup
    log_info "[7/7] Creazione metadata..."
    cat > "${BACKUP_PATH}/metadata.json" << META_EOF
{
  "backup_date": "$(date -Iseconds)",
  "hostname": "$(hostname)",
  "app_version": "$(cd $INSTALL_DIR && git describe --tags --always 2>/dev/null || echo 'unknown')",
  "docker_version": "$(docker --version | cut -d' ' -f3 | tr -d ',')",
  "backup_size": "$(du -sh $BACKUP_PATH | cut -f1)"
}
META_EOF
    log_success "Metadata creati"

    # Compressione finale
    log_info "Compressione backup finale..."
    cd "$BACKUP_DIR"
    tar czf "${BACKUP_NAME}.tar.gz" "$BACKUP_NAME"
    rm -rf "$BACKUP_PATH"

    FINAL_SIZE=$(du -h "${BACKUP_NAME}.tar.gz" | cut -f1)
    log_success "Backup completato: ${BACKUP_NAME}.tar.gz ($FINAL_SIZE)"

    echo
    log_info "📦 Backup salvato in: ${BACKUP_DIR}/${BACKUP_NAME}.tar.gz"
    echo
}

################################################################################
# RESTORE
################################################################################
do_restore() {
    local backup_file="$1"

    if [ -z "$backup_file" ]; then
        log_error "Specifica il file di backup da ripristinare"
        echo "Uso: $0 restore <backup_file.tar.gz>"
        exit 1
    fi

    if [ ! -f "$backup_file" ]; then
        log_error "File backup non trovato: $backup_file"
        exit 1
    fi

    log_warning "ATTENZIONE: Il restore sovrascriverà tutti i dati esistenti!"
    read -p "$(echo -e ${YELLOW}Vuoi continuare? [y/N]:${NC} )" confirm
    if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
        log_info "Restore annullato"
        exit 0
    fi

    log_info "Inizio restore da: $(basename $backup_file)"
    echo

    # Estrazione backup
    log_info "Estrazione backup..."
    RESTORE_DIR="${BACKUP_DIR}/restore_$(date +%s)"
    mkdir -p "$RESTORE_DIR"
    tar xzf "$backup_file" -C "$RESTORE_DIR"

    BACKUP_EXTRACTED=$(ls -1 "$RESTORE_DIR" | head -1)
    RESTORE_PATH="${RESTORE_DIR}/${BACKUP_EXTRACTED}"

    log_success "Backup estratto in: $RESTORE_PATH"

    # Stop servizi
    log_info "Arresto servizi..."
    cd "$INSTALL_DIR"
    docker compose down
    log_success "Servizi fermati"

    # Restore database
    log_info "[1/5] Restore database PostgreSQL..."
    docker compose up -d postgres
    sleep 5
    gunzip < "${RESTORE_PATH}/postgres.sql.gz" | docker compose exec -T postgres psql -U supernova supernova
    log_success "Database ripristinato"

    # Restore Redis
    log_info "[2/5] Restore Redis..."
    docker compose up -d redis
    sleep 3
    docker cp "${RESTORE_PATH}/redis.rdb" supernova_redis:/data/dump.rdb
    docker compose restart redis
    log_success "Redis ripristinato"

    # Restore .env
    log_info "[3/5] Restore configurazione..."
    cp "${RESTORE_PATH}/.env" "${INSTALL_DIR}/.env"
    log_success "Configurazione ripristinata"

    # Restore storage
    log_info "[4/5] Restore storage..."
    if [ -f "${RESTORE_PATH}/storage.tar.gz" ]; then
        rm -rf "${INSTALL_DIR}/storage"
        tar xzf "${RESTORE_PATH}/storage.tar.gz" -C "$INSTALL_DIR"
        log_success "Storage ripristinato"
    fi

    # Restore dati
    log_info "[5/5] Restore dati applicazione..."
    if [ -f "${RESTORE_PATH}/data.tar.gz" ]; then
        mkdir -p "$DATA_DIR"
        tar xzf "${RESTORE_PATH}/data.tar.gz" -C "$DATA_DIR"
        log_success "Dati ripristinati"
    fi

    # Restart completo
    log_info "Riavvio tutti i servizi..."
    docker compose up -d
    sleep 10

    # Cleanup
    rm -rf "$RESTORE_DIR"

    echo
    log_success "✓ Restore completato!"
    log_info "Verifica i servizi con: docker compose ps"
    echo
}

################################################################################
# LIST BACKUPS
################################################################################
list_backups() {
    log_info "Backup disponibili in: $BACKUP_DIR"
    echo

    if [ ! -d "$BACKUP_DIR" ] || [ -z "$(ls -A $BACKUP_DIR/*.tar.gz 2>/dev/null)" ]; then
        log_warning "Nessun backup trovato"
        exit 0
    fi

    printf "%-40s %10s %20s\n" "NOME" "DIMENSIONE" "DATA"
    echo "────────────────────────────────────────────────────────────────────────"

    for backup in "$BACKUP_DIR"/*.tar.gz; do
        if [ -f "$backup" ]; then
            name=$(basename "$backup")
            size=$(du -h "$backup" | cut -f1)
            date=$(stat -c %y "$backup" 2>/dev/null | cut -d' ' -f1,2 | cut -d'.' -f1 || stat -f "%Sm" -t "%Y-%m-%d %H:%M:%S" "$backup")
            printf "%-40s %10s %20s\n" "$name" "$size" "$date"
        fi
    done

    echo
    total_size=$(du -sh "$BACKUP_DIR" 2>/dev/null | cut -f1 || echo "N/A")
    log_info "Spazio totale utilizzato: $total_size"
    echo
}

################################################################################
# CLEAN OLD BACKUPS
################################################################################
clean_backups() {
    log_info "Pulizia backup più vecchi di $RETENTION_DAYS giorni..."

    if [ ! -d "$BACKUP_DIR" ]; then
        log_warning "Directory backup non trovata"
        exit 0
    fi

    count=0
    while IFS= read -r -d '' backup; do
        log_info "Eliminazione: $(basename $backup)"
        rm -f "$backup"
        ((count++))
    done < <(find "$BACKUP_DIR" -name "*.tar.gz" -type f -mtime +$RETENTION_DAYS -print0)

    if [ $count -eq 0 ]; then
        log_info "Nessun backup da eliminare"
    else
        log_success "Eliminati $count backup vecchi"
    fi
    echo
}

################################################################################
# MAIN
################################################################################
case "${1:-}" in
    backup)
        do_backup
        clean_backups
        ;;
    restore)
        do_restore "$2"
        ;;
    list)
        list_backups
        ;;
    clean)
        clean_backups
        ;;
    *)
        usage
        ;;
esac
