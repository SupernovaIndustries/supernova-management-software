#!/bin/bash

# Supernova Management - Script di Risoluzione Problemi
# =====================================================

# Colori
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

clear
echo ""
echo "====================================="
echo "  SUPERNOVA - RISOLUZIONE PROBLEMI"
echo "====================================="
echo ""

# Cambio directory
cd "/mnt/g/Supernova/supernova-management" || {
    echo -e "${RED}[ERRORE]${NC} Impossibile accedere alla directory del progetto!"
    exit 1
}

echo "Questo script tenterà di risolvere i problemi comuni."
echo ""
echo -e "${YELLOW}ATTENZIONE: Alcune opzioni potrebbero cancellare dati!${NC}"
echo ""
read -p "Premi invio per continuare..."

# Menu principale
while true; do
    clear
    echo ""
    echo "Seleziona il problema da risolvere:"
    echo ""
    echo "1. Errore 504 Gateway Timeout"
    echo "2. Errore connessione database"
    echo "3. Pagina bianca / errore 500"
    echo "4. Container che non si avviano"
    echo "5. Problemi di permessi file"
    echo "6. Reset completo (CANCELLA TUTTO)"
    echo "7. Backup database"
    echo "8. Ripristino database"
    echo "0. Esci"
    echo ""
    read -p "Seleziona opzione: " choice

    case $choice in
        1)
            echo ""
            echo -e "${BLUE}=== RISOLUZIONE ERRORE 504 ===${NC}"
            echo ""
            echo "1. Riavvio container..."
            docker compose down
            docker compose up -d
            sleep 10

            echo "2. Attesa servizi..."
            while ! docker compose exec -T postgres pg_isready &> /dev/null; do
                echo -n "."
                sleep 2
            done
            echo ""

            echo "3. Clear cache..."
            docker compose exec -T app php artisan config:cache
            docker compose exec -T app php artisan route:cache
            docker compose exec -T app php artisan view:clear

            echo "4. Test connessione..."
            HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" http://localhost/admin)
            if [ "$HTTP_STATUS" -eq 200 ] || [ "$HTTP_STATUS" -eq 302 ]; then
                echo ""
                echo -e "${GREEN}[OK]${NC} Problema risolto!"
            else
                echo -e "${RED}[ERRORE]${NC} Il problema persiste (HTTP $HTTP_STATUS). Prova opzione 6 per reset completo."
            fi
            read -p "Premi invio per continuare..."
            ;;
        
        2)
            echo ""
            echo -e "${BLUE}=== RISOLUZIONE ERRORI DATABASE ===${NC}"
            echo ""
            echo "1. Test connessione..."
            if ! docker compose exec -T postgres pg_isready; then
                echo "Database non raggiungibile. Riavvio..."
                docker compose restart postgres
                sleep 10
            fi

            echo "2. Reset migrazioni..."
            if ! docker compose exec -T app php artisan migrate:fresh --seed --force; then
                echo "Errore migrazioni. Provo a ricreare database..."
                docker compose exec -T postgres psql -U supernova -c "DROP DATABASE IF EXISTS supernova_management;"
                docker compose exec -T postgres psql -U supernova -c "CREATE DATABASE supernova_management;"
                docker compose exec -T app php artisan migrate:fresh --seed --force
            fi

            echo ""
            echo -e "${GREEN}[OK]${NC} Database ricreato"
            read -p "Premi invio per continuare..."
            ;;
        
        3)
            echo ""
            echo -e "${BLUE}=== RISOLUZIONE ERRORE 500 / PAGINA BIANCA ===${NC}"
            echo ""
            echo "1. Controllo logs..."
            docker compose exec -T app tail -n 50 /var/www/storage/logs/laravel.log

            echo ""
            echo "2. Fix permessi storage..."
            docker compose exec -T app chmod -R 777 storage bootstrap/cache

            echo "3. Clear tutto..."
            docker compose exec -T app php artisan config:clear
            docker compose exec -T app php artisan cache:clear
            docker compose exec -T app php artisan view:clear
            docker compose exec -T app php artisan route:clear

            echo "4. Rigenera cache..."
            docker compose exec -T app php artisan config:cache
            docker compose exec -T app php artisan route:cache

            echo "5. Composer update..."
            docker compose exec -T app composer install --no-dev --optimize-autoloader

            echo ""
            echo -e "${GREEN}[OK]${NC} Operazioni completate"
            read -p "Premi invio per continuare..."
            ;;
        
        4)
            echo ""
            echo -e "${BLUE}=== FIX CONTAINER CHE NON SI AVVIANO ===${NC}"
            echo ""
            echo "1. Stop forzato..."
            docker compose kill
            docker compose down

            echo "2. Pulizia Docker..."
            docker system prune -f
            docker volume prune -f

            echo "3. Rebuild images..."
            docker compose build --no-cache

            echo "4. Avvio..."
            docker compose up -d

            echo ""
            echo -e "${GREEN}[OK]${NC} Container ricostruiti"
            read -p "Premi invio per continuare..."
            ;;
        
        5)
            echo ""
            echo -e "${BLUE}=== FIX PERMESSI FILE ===${NC}"
            echo ""
            docker compose exec -T app chown -R www-data:www-data /var/www
            docker compose exec -T app chmod -R 755 /var/www
            docker compose exec -T app chmod -R 777 /var/www/storage
            docker compose exec -T app chmod -R 777 /var/www/bootstrap/cache
            echo ""
            echo -e "${GREEN}[OK]${NC} Permessi sistemati"
            read -p "Premi invio per continuare..."
            ;;
        
        6)
            echo ""
            echo -e "${RED}!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!"
            echo "ATTENZIONE: QUESTA OPERAZIONE CANCELLERÀ TUTTI I DATI!"
            echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!${NC}"
            echo ""
            read -p "Sei sicuro di voler procedere? [y/N] " -n 1 -r
            echo ""
            if [[ ! $REPLY =~ ^[Yy]$ ]]; then
                continue
            fi

            echo ""
            echo "Reset completo in corso..."
            docker compose down -v
            docker system prune -af
            docker compose build --no-cache
            docker compose up -d
            sleep 30
            docker compose exec -T app php artisan migrate:fresh --seed
            docker compose exec -T app php artisan storage:link
            echo ""
            echo -e "${GREEN}[OK]${NC} Reset completo eseguito"
            read -p "Premi invio per continuare..."
            ;;
        
        7)
            echo ""
            echo -e "${BLUE}=== BACKUP DATABASE ===${NC}"
            echo ""
            
            # Crea directory backups se non esiste
            mkdir -p backups
            
            BACKUP_NAME="backup_$(date +%Y-%m-%d_%H%M%S).sql"
            echo "Creazione backup: $BACKUP_NAME"
            
            if docker compose exec -T postgres pg_dump -U supernova supernova_management > "backups/$BACKUP_NAME"; then
                echo ""
                echo -e "${GREEN}[OK]${NC} Backup salvato in: backups/$BACKUP_NAME"
            else
                echo -e "${RED}[ERRORE]${NC} Backup fallito"
            fi
            read -p "Premi invio per continuare..."
            ;;
        
        8)
            echo ""
            echo -e "${BLUE}=== RIPRISTINO DATABASE ===${NC}"
            echo ""
            echo "File di backup disponibili:"
            ls -la backups/*.sql 2>/dev/null || echo "Nessun backup trovato"
            echo ""
            read -p "Nome del file da ripristinare (solo nome file): " backup_file
            
            if [ -f "backups/$backup_file" ]; then
                echo "Ripristino in corso..."
                docker compose exec -T postgres psql -U supernova -c "DROP DATABASE IF EXISTS supernova_management;"
                docker compose exec -T postgres psql -U supernova -c "CREATE DATABASE supernova_management;"
                if docker compose exec -T postgres psql -U supernova supernova_management < "backups/$backup_file"; then
                    echo -e "${GREEN}[OK]${NC} Database ripristinato"
                else
                    echo -e "${RED}[ERRORE]${NC} Ripristino fallito"
                fi
            else
                echo -e "${RED}[ERRORE]${NC} File non trovato!"
            fi
            read -p "Premi invio per continuare..."
            ;;
        
        0)
            echo ""
            echo "Fix completato!"
            exit 0
            ;;
        
        *)
            echo -e "${RED}Opzione non valida${NC}"
            sleep 2
            ;;
    esac
done