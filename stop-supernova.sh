#!/bin/bash

# Supernova Management - Script di Arresto
# ========================================

echo ""
echo "====================================="
echo "  SUPERNOVA MANAGEMENT - ARRESTO APP"
echo "====================================="
echo ""

# Colori per output
RED='\033[0;31m'
GREEN='\033[0;32m'
NC='\033[0m'

# Funzioni per messaggi colorati
print_success() {
    echo -e "${GREEN}[OK]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERRORE]${NC} $1"
}

# Cambio directory
cd "/mnt/g/Supernova/supernova-management" || {
    print_error "Impossibile accedere alla directory del progetto!"
    exit 1
}

# Controllo se Docker è in esecuzione
if ! docker version &> /dev/null; then
    print_error "Docker non è in esecuzione!"
    exit 1
fi

# Arresto container
echo "Arresto container in corso..."
if docker compose down; then
    echo ""
    print_success "Tutti i container sono stati arrestati correttamente."
else
    echo ""
    print_error "Problemi durante l'arresto dei container."
    echo "Provo forzatura..."
    docker compose kill
    docker compose down
fi

# Non rimuovere automaticamente i volumi per proteggere i dati
# Se necessario, l'utente può farlo manualmente con: docker compose down -v

echo ""
echo "====================================="
echo "  ARRESTO COMPLETATO"
echo "====================================="
echo ""
read -p "Premi invio per chiudere..."