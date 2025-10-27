#!/bin/bash

# Supernova Management - Script di Controllo e Diagnostica
# ========================================================

# Colori
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

clear
echo ""
echo "====================================="
echo "  SUPERNOVA MANAGEMENT - DIAGNOSTICA"
echo "====================================="
echo ""

# Cambio directory
cd "/mnt/g/Supernova/supernova-management" || {
    echo -e "${RED}[ERRORE]${NC} Impossibile accedere alla directory del progetto!"
    exit 1
}

# Menu principale
while true; do
    echo ""
    echo "Cosa vuoi controllare?"
    echo ""
    echo "1. Stato container"
    echo "2. Log applicazione Laravel"
    echo "3. Log database PostgreSQL"
    echo "4. Log web server Nginx"
    echo "5. Log Redis"
    echo "6. Test connessione database"
    echo "7. Esegui comando Artisan"
    echo "8. Shell nel container app"
    echo "9. Riavvia un servizio specifico"
    echo "0. Esci"
    echo ""
    read -p "Seleziona un'opzione: " choice

    case $choice in
        1)
            echo ""
            echo -e "${BLUE}=== STATO CONTAINER ===${NC}"
            docker compose ps
            echo ""
            echo -e "${BLUE}=== UTILIZZO RISORSE ===${NC}"
            docker stats --no-stream
            read -p "Premi invio per continuare..."
            ;;
        2)
            echo ""
            echo -e "${BLUE}=== LOG APPLICAZIONE LARAVEL (Ctrl+C per uscire) ===${NC}"
            docker compose logs -f --tail=50 app
            ;;
        3)
            echo ""
            echo -e "${BLUE}=== LOG DATABASE POSTGRESQL (Ctrl+C per uscire) ===${NC}"
            docker compose logs -f --tail=50 postgres
            ;;
        4)
            echo ""
            echo -e "${BLUE}=== LOG WEB SERVER NGINX (Ctrl+C per uscire) ===${NC}"
            docker compose logs -f --tail=50 nginx
            ;;
        5)
            echo ""
            echo -e "${BLUE}=== LOG REDIS (Ctrl+C per uscire) ===${NC}"
            docker compose logs -f --tail=50 redis
            ;;
        6)
            echo ""
            echo -e "${BLUE}=== TEST CONNESSIONE DATABASE ===${NC}"
            docker compose exec -T app php artisan tinker --execute="
                echo 'Database: ' . DB::connection()->getDatabaseName();
                echo '\nTables: ' . implode(', ', array_map(fn(\$t) => \$t->tablename, DB::select('SELECT tablename FROM pg_tables WHERE schemaname = \'public\'')));
                echo '\nConnection: OK';
            "
            echo ""
            read -p "Premi invio per continuare..."
            ;;
        7)
            echo ""
            read -p "Inserisci comando artisan (es: cache:clear): " cmd
            echo ""
            echo "Esecuzione: php artisan $cmd"
            docker compose exec -T app php artisan $cmd
            echo ""
            read -p "Premi invio per continuare..."
            ;;
        8)
            echo ""
            echo -e "${BLUE}=== SHELL CONTAINER APP (digita 'exit' per uscire) ===${NC}"
            docker compose exec app /bin/bash
            ;;
        9)
            echo ""
            echo "Quale servizio vuoi riavviare?"
            echo "1. App Laravel"
            echo "2. PostgreSQL"
            echo "3. Nginx"
            echo "4. Redis"
            echo "5. Tutti"
            echo "0. Annulla"
            read -p "Seleziona servizio: " service_choice
            
            case $service_choice in
                1)
                    docker compose restart app
                    echo -e "${GREEN}[OK]${NC} App Laravel riavviata"
                    ;;
                2)
                    docker compose restart postgres
                    echo -e "${GREEN}[OK]${NC} PostgreSQL riavviato"
                    ;;
                3)
                    docker compose restart nginx
                    echo -e "${GREEN}[OK]${NC} Nginx riavviato"
                    ;;
                4)
                    docker compose restart redis
                    echo -e "${GREEN}[OK]${NC} Redis riavviato"
                    ;;
                5)
                    docker compose restart
                    echo -e "${GREEN}[OK]${NC} Tutti i servizi riavviati"
                    ;;
                *)
                    echo "Annullato"
                    ;;
            esac
            read -p "Premi invio per continuare..."
            ;;
        0)
            echo ""
            echo "Arrivederci!"
            exit 0
            ;;
        *)
            echo -e "${RED}Opzione non valida${NC}"
            ;;
    esac
done