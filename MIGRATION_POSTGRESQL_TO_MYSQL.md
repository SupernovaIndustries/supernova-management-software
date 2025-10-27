# Migrazione da PostgreSQL a MySQL per Laragon

## ⚠️ Importante: Cambio Database Engine

Il progetto attualmente usa **PostgreSQL** ma Laragon base include solo **MySQL**. 

### ✅ Compatibilità Verificata

Ho analizzato tutte le migrazioni - **il progetto è compatibile con MySQL 8.0+**:
- Tutti i campi `json` sono supportati in MySQL 8.0+
- Nessuna funzione specifica PostgreSQL utilizzata
- Schema database standard Laravel

### 🔄 Opzioni di Migrazione

#### Opzione 1: Usa MySQL (Raccomandato per Laragon)
```bash
# Configura .env per MySQL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=supernova
DB_USERNAME=root
DB_PASSWORD=

# Ricrea database da zero
php artisan migrate:fresh --seed
```

#### Opzione 2: Mantieni PostgreSQL (Richiede installazione manuale)
Se vuoi mantenere PostgreSQL:
1. Installa PostgreSQL separatamente su Windows
2. Configura servizio Windows per PostgreSQL
3. Mantieni configurazione `.env` originale

### 📊 Export/Import Dati (se necessario)

Se hai dati importanti da migrare da PostgreSQL a MySQL:

```bash
# 1. Export da PostgreSQL (se hai dati esistenti)
pg_dump -h postgres -U supernova supernova --data-only --inserts > data_export.sql

# 2. Converti sintassi PostgreSQL → MySQL (manuale)
# Cambia: true/false → 1/0
# Rimuovi schemi public.
# Adatta tipi timestamp

# 3. Import in MySQL
mysql -h 127.0.0.1 -u root supernova < data_export_converted.sql
```

### 🎯 Raccomandazione

**Usa MySQL con Laragon** perché:
- ✅ Zero configurazione aggiuntiva
- ✅ Performance native su Windows
- ✅ Compatibilità 100% verificata
- ✅ phpMyAdmin incluso per gestione
- ✅ Backup/restore più semplici

### 🔧 Post-Migrazione

Dopo aver switchato a MySQL, potrai:
1. Usare phpMyAdmin per gestione visuale
2. Backup semplici con mysqldump
3. Performance migliori su Windows
4. Meno dipendenze esterne

---

**✨ Il progetto funzionerà perfettamente con MySQL su Laragon!**