# 🚀 Supernova Management - Deployment Guide

Guida completa per il deployment di Supernova Management su Proxmox con container LXC.

## 📋 Indice

- [Architettura](#-architettura)
- [Requisiti](#-requisiti)
- [Quick Start](#-quick-start)
- [Installazione Dettagliata](#-installazione-dettagliata)
- [Configurazione Networking](#-configurazione-networking)
- [Configurazione Claude Code](#-configurazione-claude-code)
- [Setup AI (Ollama)](#-setup-ai-ollama)
- [Backup & Restore](#-backup--restore)
- [Troubleshooting](#-troubleshooting)

---

## 🏗️ Architettura

### Opzione A: Setup All-in-One (Consigliato per ≤32GB RAM)

```
┌─────────────────────────────────────────────────────────┐
│             PROXMOX HOST (64GB RAM)                     │
│                                                         │
│  ┌────────────────────┐  ┌────────────────────┐       │
│  │  LXC CT200         │  │  LXC CT201         │       │
│  │  Supernova + Ollama│  │  Nextcloud         │       │
│  │  12GB RAM, 6 cores │  │  12GB RAM, 4 cores │       │
│  │  50GB disk         │  │  100GB disk        │       │
│  └─────────┬──────────┘  └─────────┬──────────┘       │
│            │                       │                   │
│            └───────┬───────────────┘                   │
│                    │                                   │
│         ┌──────────▼──────────────┐                    │
│         │  vmbr1 (Private LAN)    │                    │
│         │  10.0.100.0/24          │                    │
│         └─────────────────────────┘                    │
└─────────────────────────────────────────────────────────┘
```

### Opzione B: Setup Separato (Per >32GB RAM disponibili)

```
┌─────────────────────────────────────────────────────────┐
│             PROXMOX HOST (64GB RAM)                     │
│                                                         │
│  ┌───────────┐  ┌───────────┐  ┌───────────┐          │
│  │ Supernova │  │ Nextcloud │  │  Ollama   │          │
│  │  CT200    │  │  CT201    │  │  CT202    │          │
│  │  10GB RAM │  │  10GB RAM │  │  8GB RAM  │          │
│  │  4 cores  │  │  4 cores  │  │  4 cores  │          │
│  └─────┬─────┘  └─────┬─────┘  └─────┬─────┘          │
│        │              │              │                 │
│        └──────────────┼──────────────┘                 │
│                       │                                │
│            ┌──────────▼──────────────┐                 │
│            │  vmbr1 (Private LAN)    │                 │
│            │  10.0.100.0/24          │                 │
│            └─────────────────────────┘                 │
└─────────────────────────────────────────────────────────┘
```

---

## 💻 Requisiti

### Hardware Minimo
- **CPU**: 8 cores (16 threads consigliato)
- **RAM**: 32GB disponibili (post Proxmox)
- **Disk**: 200GB SSD

### Software
- Proxmox VE 8.0+
- Ubuntu 24.04 LTS template
- Git

### Network
- Accesso internet per download dipendenze
- IP statico o DHCP reservation (consigliato)

---

## ⚡ Quick Start

### 1. Clona Repository su Proxmox Host

```bash
# Su Proxmox host
cd /root
git clone https://github.com/SupernovaIndustries/supernova-management-software.git
cd supernova-management-software/deployment
```

### 2. Crea Container LXC

```bash
# Rendi eseguibili gli script
chmod +x proxmox/*.sh
chmod +x *.sh

# Crea container (modalità interattiva)
./proxmox/create-lxc.sh
```

**Configurazione consigliata:**
- **CT ID**: 200
- **Hostname**: supernova-mgmt
- **CPU**: 6 cores (con Ollama) o 4 cores (senza)
- **RAM**: 12GB (con Ollama) o 10GB (senza)
- **Disk**: 50GB
- **Network**: DHCP o IP statico

### 3. Installa Supernova Management

```bash
# Copia script nel container
pct push 200 install-supernova.sh /root/install-supernova.sh
pct exec 200 -- chmod +x /root/install-supernova.sh

# Esegui installazione
pct exec 200 -- /root/install-supernova.sh
```

Lo script chiederà:
- ✅ Installare Ollama? (Sì/No)
- ✅ Modello AI da usare
- ✅ Installare Tailscale? (Sì/No)
- ✅ URL repository Git

### 4. Accedi all'Applicazione

```bash
# Ottieni IP del container
pct exec 200 -- hostname -I

# Apri browser
# http://<IP_CONTAINER>/admin
```

Crea il primo utente amministratore.

---

## 📚 Installazione Dettagliata

### Passo 1: Preparazione Proxmox Host

```bash
# Aggiorna Proxmox
apt update && apt upgrade -y

# Scarica template Ubuntu 24.04
pveam update
pveam download local ubuntu-24.04-standard_24.04-2_amd64.tar.zst

# Verifica template scaricato
pveam list local
```

### Passo 2: Configurazione Container

Il script `create-lxc.sh` crea automaticamente un container con:

- ✅ Features necessarie per Docker (`nesting=1`, `keyctl=1`)
- ✅ Unprivileged container (più sicuro)
- ✅ Auto-start al boot
- ✅ DNS configurato
- ✅ Resource limits appropriati

**Personalizzazione parametri:**

```bash
# Esempio con parametri custom
CTID=200 \
HOSTNAME=supernova \
CORES=6 \
MEMORY=12288 \
DISK_SIZE=50 \
IP_ADDRESS=192.168.1.100/24 \
GATEWAY=192.168.1.1 \
./proxmox/create-lxc.sh
```

### Passo 3: Installazione Automatica

Lo script `install-supernova.sh` installa:

1. **Dipendenze Sistema**
   - Docker & Docker Compose
   - Git, curl, wget
   - Utilità di sistema

2. **Ollama** (opzionale)
   - Download e setup
   - Modello AI selezionato
   - Servizio systemd

3. **Tailscale** (opzionale)
   - VPN mesh per accesso remoto
   - Integrazione con rete privata

4. **Supernova Management**
   - Clone repository
   - Build Docker images
   - Setup database
   - Migrations & Seeders

5. **Configurazione**
   - File .env
   - Password sicure generate
   - Permessi corretti
   - Ottimizzazione Laravel

### Passo 4: Verifica Installazione

```bash
# Entra nel container
pct enter 200

# Verifica servizi Docker
docker compose ps

# Output atteso:
# NAME                   STATUS      PORTS
# supernova_app          running
# supernova_nginx        running     0.0.0.0:80->80/tcp
# supernova_postgres     running     127.0.0.1:5432->5432/tcp
# supernova_redis        running     127.0.0.1:6379->6379/tcp
# supernova_meilisearch  running     127.0.0.1:7700->7700/tcp

# Verifica Ollama (se installato)
ollama list

# Test endpoint
curl http://localhost
```

---

## 🌐 Configurazione Networking

### Setup Rete Privata tra Container

Per far comunicare Supernova, Nextcloud e Ollama:

```bash
# Su Proxmox host
cd /root/supernova-management-software/deployment
./proxmox/setup-network.sh
```

Questo script:
1. ✅ Crea bridge privato `vmbr1` (10.0.100.0/24)
2. ✅ Aggiunge interfaccia `eth1` ai container
3. ✅ Configura routing e DNS
4. ✅ Test connettività

**Indirizzi IP Privati:**
- Supernova (CT200): `10.0.100.10`
- Nextcloud (CT201): `10.0.100.20`
- Ollama (CT202): `10.0.100.30`

**Configurazione Applicazione:**

Nel file `.env` di Supernova:

```env
# Nextcloud Integration
NEXTCLOUD_URL=http://nextcloud.local
NEXTCLOUD_USERNAME=admin
NEXTCLOUD_PASSWORD=your_password

# Ollama AI
OLLAMA_API_URL=http://ollama.local:11434
OLLAMA_MODEL=qwen2.5:7b
```

**Test connettività:**

```bash
# Dentro container Supernova
curl http://nextcloud.local
curl http://ollama.local:11434/api/tags
```

---

## 🔐 Configurazione Claude Code

Per usare Claude Code sui container:

### 1. Installa SSH nei Container

```bash
# Per ogni container
pct exec 200 -- /opt/supernova-management/deployment/configure-ssh-claude.sh
pct exec 201 -- /opt/supernova-management/deployment/configure-ssh-claude.sh
```

Questo crea:
- ✅ Utente `claude` con sudo
- ✅ SSH configurato in modo sicuro
- ✅ Chiavi SSH generate
- ✅ Firewall configurato

### 2. Aggiungi Chiave SSH

**Dal tuo computer locale:**

```bash
# Copia chiave pubblica al container
cat ~/.ssh/id_ed25519_supernova.pub | \
  ssh root@<IP_CONTAINER> 'cat >> /home/claude/.ssh/authorized_keys'
```

### 3. Test Connessione

```bash
# Test SSH
ssh claude@<IP_CONTAINER>

# Se usa Tailscale
ssh claude@<TAILSCALE_IP>
```

### 4. Configura Claude Code

In Claude Code, aggiungi SSH host:

```json
{
  "Host": "supernova-prod",
  "HostName": "<IP_CONTAINER>",
  "User": "claude",
  "IdentityFile": "~/.ssh/id_ed25519_supernova",
  "ForwardAgent": "yes"
}
```

---

## 🤖 Setup AI (Ollama)

Vedi [AI_SETUP.md](./AI_SETUP.md) per guida completa.

### Modelli Consigliati per Supernova

| Modello | RAM | Velocità | Qualità | Uso |
|---------|-----|----------|---------|-----|
| **qwen2.5:7b** | 6GB | ⚡⚡⚡ | ⭐⭐⭐⭐⭐ | Produzione (consigliato) |
| **phi3:mini** | 3GB | ⚡⚡⚡⚡⚡ | ⭐⭐⭐ | Dev/Test leggero |
| **gemma2:9b** | 8GB | ⚡⚡ | ⭐⭐⭐⭐⭐ | Alta qualità |
| **llama3.2:3b** | 2GB | ⚡⚡⚡⚡ | ⭐⭐⭐ | Risorse limitate |

### Quick Install

```bash
# Installa Ollama
curl -fsSL https://ollama.com/install.sh | sh

# Download modello
ollama pull qwen2.5:7b

# Test
ollama run qwen2.5:7b "Ciao, come stai?"
```

### Integrazione con Supernova

Nel file `.env`:

```env
AI_PROVIDER=ollama
OLLAMA_API_URL=http://localhost:11434
OLLAMA_MODEL=qwen2.5:7b
```

---

## 💾 Backup & Restore

### Backup Automatico

```bash
# Backup manuale
/opt/supernova-management/deployment/backup.sh backup

# Lista backup
/opt/supernova-management/deployment/backup.sh list

# Pulizia backup vecchi (>30 giorni)
/opt/supernova-management/deployment/backup.sh clean
```

**Backup automatico giornaliero** configurato con systemd timer (3 AM).

### Restore

```bash
# Lista backup disponibili
./backup.sh list

# Restore da backup specifico
./backup.sh restore /opt/supernova-backups/supernova_backup_20250127_030000.tar.gz
```

⚠️ **ATTENZIONE**: Il restore ferma i servizi e sovrascrive tutti i dati!

### Contenuto Backup

- ✅ Database PostgreSQL (dump completo)
- ✅ Redis data
- ✅ File configurazione (.env)
- ✅ Storage Laravel (uploads, cache)
- ✅ Dati applicazione
- ✅ Metadata (versione, data, ecc.)

---

## 🔧 Servizi Systemd

### Setup Auto-Start

```bash
# Dentro il container
/opt/supernova-management/deployment/setup-systemd-services.sh
```

Questo configura:

1. **supernova.service** - Servizio principale Docker Compose
2. **supernova-healthcheck.timer** - Monitoraggio ogni 5 minuti
3. **supernova-backup.timer** - Backup giornaliero alle 3 AM

### Comandi Utili

```bash
# Gestione servizio
systemctl start supernova
systemctl stop supernova
systemctl restart supernova
systemctl status supernova

# Log in tempo reale
journalctl -u supernova -f

# Stato timer
systemctl list-timers supernova-*
```

### Comando Shortcut

Lo script di installazione crea il comando `supernova`:

```bash
supernova start     # Avvia servizi
supernova stop      # Ferma servizi
supernova restart   # Riavvia servizi
supernova logs      # Visualizza log
supernova update    # Aggiorna da Git
supernova backup    # Backup manuale
```

---

## 🐛 Troubleshooting

### Container non si avvia

```bash
# Verifica log container
pct log 200

# Entra in modalità recovery
pct enter 200

# Verifica risorse
free -h
df -h
```

### Docker non parte

```bash
# Verifica Docker daemon
systemctl status docker

# Restart Docker
systemctl restart docker

# Log Docker
journalctl -u docker -n 50
```

### Servizio web non risponde

```bash
# Verifica container Docker
docker compose ps

# Log Nginx
docker compose logs nginx

# Log applicazione
docker compose logs app

# Restart servizi
docker compose restart
```

### Database connection failed

```bash
# Verifica PostgreSQL
docker compose exec postgres pg_isready

# Log database
docker compose logs postgres

# Test connessione
docker compose exec postgres psql -U supernova -d supernova
```

### Ollama non risponde

```bash
# Verifica servizio
systemctl status ollama

# Restart Ollama
systemctl restart ollama

# Test API
curl http://localhost:11434/api/tags

# Log Ollama
journalctl -u ollama -n 50
```

### Performance problemi

```bash
# Verifica risorse container
docker stats

# Ottimizza cache Laravel
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache

# Restart Redis
docker compose restart redis
```

### Network Issues

```bash
# Test connettività
ping 8.8.8.8

# Verifica DNS
cat /etc/resolv.conf

# Test rete privata
ping nextcloud.local
ping ollama.local

# Verifica routing
ip route show
```

---

## 📞 Supporto

Per problemi o domande:

1. Controlla [TROUBLESHOOTING.md](./TROUBLESHOOTING.md)
2. Verifica log: `/opt/supernova-management/storage/logs/`
3. Apri issue su GitHub

---

## 📝 Changelog

### v1.0.0 - 2025-01-27
- ✨ Release iniziale
- ✅ Setup automatico Proxmox LXC
- ✅ Integrazione Ollama
- ✅ Networking privato tra container
- ✅ Backup automatici
- ✅ Supporto Claude Code via SSH
- ✅ Systemd services

---

## 📄 License

Proprietario - Supernova Industries

---

**Made with ❤️ by Supernova Industries**
