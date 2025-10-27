#!/bin/bash

# Script di test per le notifiche email di cambio stato progetti
# Usage: ./TEST-EMAIL-NOTIFICATION.sh

echo "=========================================="
echo "Test Notifiche Email Cambio Stato Progetto"
echo "=========================================="
echo ""

# Colori per output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Richiedi ID progetto
echo -e "${YELLOW}Inserisci l'ID del progetto da testare:${NC}"
read PROJECT_ID

# Richiedi email destinatario
echo -e "${YELLOW}Inserisci l'email di destinazione per il test:${NC}"
read TEST_EMAIL

# Menu selezione test
echo ""
echo "Seleziona il tipo di test:"
echo "1) Planning → In Progress (Progetto Avviato)"
echo "2) In Progress → Testing (Fase di Test)"
echo "3) Testing → Consegna Prototipo Test"
echo "4) Testing → Completed (Progetto Completato)"
echo "5) In Progress → On Hold (Progetto in Pausa)"
echo "6) Planning → Cancelled (Progetto Annullato)"
echo "7) Custom (inserisci stati personalizzati)"
echo ""
echo -e "${YELLOW}Scelta [1-7]:${NC}"
read CHOICE

case $CHOICE in
    1)
        OLD_STATUS="planning"
        NEW_STATUS="in_progress"
        ;;
    2)
        OLD_STATUS="in_progress"
        NEW_STATUS="testing"
        ;;
    3)
        OLD_STATUS="testing"
        NEW_STATUS="consegna_prototipo_test"
        ;;
    4)
        OLD_STATUS="testing"
        NEW_STATUS="completed"
        ;;
    5)
        OLD_STATUS="in_progress"
        NEW_STATUS="on_hold"
        ;;
    6)
        OLD_STATUS="planning"
        NEW_STATUS="cancelled"
        ;;
    7)
        echo -e "${YELLOW}Inserisci stato vecchio:${NC}"
        read OLD_STATUS
        echo -e "${YELLOW}Inserisci stato nuovo:${NC}"
        read NEW_STATUS
        ;;
    *)
        echo -e "${RED}Scelta non valida!${NC}"
        exit 1
        ;;
esac

echo ""
echo "=========================================="
echo -e "${GREEN}Invio email di test...${NC}"
echo "Progetto ID: $PROJECT_ID"
echo "Email: $TEST_EMAIL"
echo "Cambio stato: $OLD_STATUS → $NEW_STATUS"
echo "=========================================="
echo ""

# Esegui comando
php artisan project:test-status-email $PROJECT_ID $TEST_EMAIL --old-status=$OLD_STATUS --new-status=$NEW_STATUS

EXIT_CODE=$?

echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo -e "${GREEN}=========================================="
    echo "✅ Test completato con successo!"
    echo "==========================================${NC}"
    echo ""
    echo "Controlla:"
    echo "1. La casella email: $TEST_EMAIL"
    echo "2. I log: storage/logs/laravel.log"
    echo ""
    echo "Comando per visualizzare i log:"
    echo "tail -f storage/logs/laravel.log | grep 'Project status'"
else
    echo -e "${RED}=========================================="
    echo "❌ Test fallito!"
    echo "==========================================${NC}"
    echo ""
    echo "Verifica:"
    echo "1. Configurazione email nel file .env"
    echo "2. ID progetto corretto"
    echo "3. Log: storage/logs/laravel.log"
fi

echo ""
