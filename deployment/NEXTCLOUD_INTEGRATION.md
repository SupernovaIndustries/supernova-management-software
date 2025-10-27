# ☁️ Nextcloud Integration Guide

Guida completa per integrare Nextcloud con Supernova Management su Proxmox con **storage condiviso**.

## 📋 Indice

- [Architettura](#-architettura)
- [Installazione Nextcloud](#-installazione-nextcloud)
- [Configurazione Storage Condiviso](#-configurazione-storage-condiviso)
- [Integrazione con Supernova](#-integrazione-con-supernova)
- [Network Setup](#-network-setup)
- [Tailscale Integration](#-tailscale-integration)
- [Troubleshooting](#-troubleshooting)

---

## 🏗️ Architettura

### Storage Strategy

```
┌──────────────────── DISCO CONDIVISO ────────────────────────┐
│ /mnt/shared-storage                                          │
│                                                              │
│ ├─ nextcloud/                 ← Dati Nextcloud              │
│ │  ├─ files/                                                │
│ │  ├─ appdata/                                              │
│ │  └─ ...                                                   │
│                                                              │
│ ├─ supernova-temp/            ← Storage temporaneo Supernova│
│ │  ├─ public/                 (cache, loghi, temp files)    │
│ │  └─ temp/                                                 │
│                                                              │
│ └─ <i tuoi dati esistenti>    ← Intoccati!                  │
│    ├─ Documents/                                             │
│    ├─ Photos/                                                │
│    └─ ...                                                    │
└──────────────────────────────────────────────────────────────┘
```

### File Mapping

**Storage Temporaneo (Disco Condiviso):**
- ✅ Cache immagini
- ✅ File in elaborazione
- ✅ Upload temporanei
- ✅ Loghi pubblici
- ✅ Asset di sistema

**Storage Permanente (Nextcloud via API):**
- ✅ Fatture (ricevute/emesse)
- ✅ Preventivi PDF
- ✅ Contratti
- ✅ Documenti progetto (Gerber, BOM, Firmware)
- ✅ Certificazioni
- ✅ F24 e documenti fiscali

### Network Layout

```
┌─────────────── PROXMOX HOST ────────────────┐
│                                              │
│  ┌──────────────┐    ┌──────────────┐       │
│  │ CT200        │    │ CT201        │       │
│  │ Supernova    │    │ Nextcloud    │       │
│  │              │    │              │       │
│  │ eth0: WAN    │    │ eth0: WAN    │       │
│  │ eth1: LAN    │    │ eth1: LAN    │       │
│  │ 10.0.100.10  │◄──►│ 10.0.100.20  │       │
│  └──────┬───────┘    └──────┬───────┘       │
│         │                   │                │
│         │   Shared Disk     │                │
│         └────────┬──────────┘                │
│                  │                           │
│         /mnt/shared-storage                  │
│            (bind mount)                      │
└──────────────────────────────────────────────┘
```

---

## 🚀 Installazione Nextcloud

### Passo 1: Crea Container LXC

**Su Proxmox host:**

```bash
cd /root/supernova-management-software/deployment
chmod +x proxmox/*.sh

# Crea container Nextcloud
./proxmox/create-nextcloud-lxc.sh
```

**Configurazione consigliata:**

| Parametro | Valore | Note |
|-----------|--------|------|
| CT ID | 201 | Dopo Supernova (200) |
| Hostname | nextcloud | |
| CPU cores | 4 | |
| RAM | 12GB (12288 MB) | |
| Disk sistema | 40GB | Solo OS, non dati |
| Network | DHCP o statico | |
| **Disco dati** | `/dev/sdb` | **Disco condiviso!** |

**⚠️ IMPORTANTE:** Quando chiede "Vuoi aggiungere un disco per i dati?":
- Rispondi **Sì**
- Specifica il device del tuo disco (es. `/dev/sdb`)
- Il disco verrà montato come `/mnt/shared-storage` NEL container
- **NON verrà formattato**, solo montato!

### Passo 2: Installa Nextcloud

```bash
# Copia script nel container
pct push 201 nextcloud/install-nextcloud.sh /root/install-nextcloud.sh
pct exec 201 -- chmod +x /root/install-nextcloud.sh

# Esegui installazione (interattiva)
pct exec 201 -- /root/install-nextcloud.sh
```

**Durante installazione ti chiederà:**

1. **Path storage** → Conferma `/mnt/shared-storage`
2. **Mount disco** → Se già montato da Proxmox, skip
3. **Dominio** → `nextcloud.local` (o custom)
4. **SSL** → No per ora (lo aggiungiamo dopo)
5. **Tailscale** → Sì (consigliato)

Lo script fa automaticamente:
- ✅ Installa Docker + Docker Compose
- ✅ Crea struttura directory su disco condiviso
- ✅ Setup PostgreSQL + Redis
- ✅ Configura Nextcloud
- ✅ Genera password sicure
- ✅ Installa Tailscale (se scelto)

**Tempo:** ~10-15 minuti

### Passo 3: Verifica Installazione

```bash
# Entra nel container
pct enter 201

# Verifica servizi Docker
docker compose ps

# Verifica storage
df -h /mnt/shared-storage
ls -la /mnt/shared-storage/

# Output atteso:
# drwxr-xr-x nextcloud/
# drwxrwxr-x supernova-temp/
# <i tuoi dati esistenti>
```

### Passo 4: Primo Accesso

```bash
# Ottieni IP del container
pct exec 201 -- hostname -I

# Apri browser
# http://<IP_CONTAINER>
```

**Credenziali:** Le trovi nell'output dello script o in `/opt/nextcloud/.env`

---

## 🔗 Configurazione Storage Condiviso

### Per Supernova Management

**Nel container Supernova (CT 200):**

#### Opzione A: Mount Bind (Consigliata)

```bash
# Su Proxmox host
# Aggiungi bind mount del disco al container Supernova
pct set 200 -mp0 /dev/sdb,mp=/mnt/shared-storage

# Restart container
pct restart 200

# Configura storage
pct exec 200 -- /opt/supernova-management/deployment/configure-shared-storage.sh
```

#### Opzione B: NFS Share

Se preferisci NFS tra container:

```bash
# Nel container Nextcloud (server NFS)
apt install nfs-kernel-server
echo "/mnt/shared-storage/supernova-temp 10.0.100.10(rw,sync,no_subtree_check)" >> /etc/exports
exportfs -ra

# Nel container Supernova (client NFS)
apt install nfs-common
mkdir -p /mnt/shared-storage
mount -t nfs nextcloud.local:/mnt/shared-storage/supernova-temp /mnt/shared-storage
```

### Configurazione Automatica

Lo script `configure-shared-storage.sh` fa:

1. ✅ Rileva configurazione storage da Nextcloud
2. ✅ Crea link simbolici in Laravel storage
3. ✅ Aggiorna `.env` con path storage condiviso
4. ✅ Configura permessi

**Risultato `.env`:**

```env
# Storage temporaneo su disco condiviso
SHARED_STORAGE_PATH=/mnt/shared-storage/supernova-temp

# Nextcloud per file permanenti
NEXTCLOUD_URL=http://nextcloud.local
NEXTCLOUD_USERNAME=admin
NEXTCLOUD_PASSWORD=<password-generata>
NEXTCLOUD_BASE_PATH=/remote.php/dav/files/admin
```

---

## 🌐 Network Setup

### Rete Privata tra Container

Per far comunicare Supernova ↔ Nextcloud:

```bash
# Su Proxmox host
cd /root/supernova-management-software/deployment
./proxmox/setup-network.sh
```

Questo crea:
- ✅ Bridge privato `vmbr1` (10.0.100.0/24)
- ✅ Interfaccia `eth1` su entrambi i container
- ✅ DNS locale (`nextcloud.local`, `supernova.local`)
- ✅ Routing tra container

**Test connettività:**

```bash
# Da Supernova → Nextcloud
pct exec 200 -- curl http://nextcloud.local

# Da Nextcloud → Supernova
pct exec 201 -- curl http://supernova.local
```

---

## 🔐 Tailscale Integration

### Setup Tailscale

**In entrambi i container:**

```bash
# Installato automaticamente dagli script
# Attivazione:
tailscale up

# Ottieni IP Tailscale
tailscale ip -4
```

**Accesso da remoto:**

```bash
# SSH via Tailscale
ssh claude@<TAILSCALE_IP_NEXTCLOUD>
ssh claude@<TAILSCALE_IP_SUPERNOVA>

# Nextcloud via browser
https://<TAILSCALE_IP_NEXTCLOUD>

# Supernova via browser
http://<TAILSCALE_IP_SUPERNOVA>
```

### Trusted Domains in Nextcloud

```bash
# Nel container Nextcloud
nextcloud occ config:system:set trusted_domains 3 --value="<TAILSCALE_IP>"
```

---

## 🧪 Testing Integration

### Test 1: Storage Condiviso

```bash
# Nel container Supernova
pct exec 200 -- bash -c "echo 'test' > /mnt/shared-storage/supernova-temp/test.txt"

# Nel container Nextcloud (dovrebbe vedere lo stesso file)
pct exec 201 -- cat /mnt/shared-storage/supernova-temp/test.txt
# Output: test
```

### Test 2: Nextcloud API

```bash
# Nel container Supernova
pct exec 200 -- docker compose exec -T app php artisan tinker

# In tinker:
>>> $nc = app(\App\Services\NextcloudService::class);
>>> $nc->testConnection();
# Output: true

>>> $nc->uploadDocument('/tmp/test.pdf', 'Test/test.pdf');
# Output: true
```

### Test 3: Network Privato

```bash
# Ping tra container
pct exec 200 -- ping -c 3 nextcloud.local
pct exec 201 -- ping -c 3 supernova.local

# HTTP
pct exec 200 -- curl -I http://nextcloud.local
pct exec 201 -- curl -I http://supernova.local
```

---

## 📊 Monitoring

### Spazio Disco

```bash
# Nel container che ha il disco montato
df -h /mnt/shared-storage

# Uso per cartella
du -sh /mnt/shared-storage/nextcloud
du -sh /mnt/shared-storage/supernova-temp
```

### Nextcloud Status

```bash
# Nel container Nextcloud
nextcloud occ status

# Log
nextcloud logs nextcloud
nextcloud logs postgres
```

### Supernova Status

```bash
# Nel container Supernova
supernova logs
docker compose ps
```

---

## 🐛 Troubleshooting

### Disco non montato

```bash
# Verifica mount
mount | grep shared-storage

# Monta manualmente
mount /dev/sdb /mnt/shared-storage

# Rendi permanente in /etc/fstab
echo "/dev/sdb /mnt/shared-storage ext4 defaults 0 2" >> /etc/fstab
```

### Permessi storage

```bash
# Nextcloud
chown -R www-data:www-data /mnt/shared-storage/nextcloud
chmod -R 770 /mnt/shared-storage/nextcloud

# Supernova
chown -R www-data:www-data /mnt/shared-storage/supernova-temp
chmod -R 775 /mnt/shared-storage/supernova-temp
```

### Nextcloud API non raggiungibile

```bash
# Verifica network
ping nextcloud.local

# Verifica /etc/hosts
cat /etc/hosts | grep nextcloud

# Aggiungi manualmente se manca
echo "10.0.100.20 nextcloud nextcloud.local" >> /etc/hosts
```

### Container non comunica

```bash
# Verifica eth1 esiste
ip addr show eth1

# Verifica routing
ip route show

# Re-esegui setup network
cd /root/supernova-management-software/deployment
./proxmox/setup-network.sh
```

---

## 🔧 Comandi Utili

### Nextcloud

```bash
nextcloud start          # Avvia
nextcloud stop           # Ferma
nextcloud restart        # Riavvia
nextcloud logs [service] # Log
nextcloud update         # Aggiorna
nextcloud occ <cmd>      # Comandi occ
```

**Esempi occ:**

```bash
# Scan files
nextcloud occ files:scan --all

# Maintenance mode
nextcloud occ maintenance:mode --on
nextcloud occ maintenance:mode --off

# Users
nextcloud occ user:list
nextcloud occ user:add username
```

### Storage

```bash
# Spazio usato
du -sh /mnt/shared-storage/*

# Find file grandi
find /mnt/shared-storage -type f -size +100M

# Cleanup temp Supernova
rm -rf /mnt/shared-storage/supernova-temp/temp/*
```

---

## 📝 Best Practices

### Backup

```bash
# Backup Nextcloud (dal container)
nextcloud occ maintenance:mode --on
tar czf nextcloud-data-backup.tar.gz /mnt/shared-storage/nextcloud
docker compose exec postgres pg_dump -U nextcloud nextcloud > nextcloud-db.sql
nextcloud occ maintenance:mode --off

# Backup Supernova continua con backup.sh normale
/opt/supernova-management/deployment/backup.sh backup
```

### Security

- ✅ Usa SSL in produzione (Let's Encrypt)
- ✅ Firewall: solo porte necessarie
- ✅ Password forti (generate automaticamente)
- ✅ 2FA su Nextcloud
- ✅ Backup regolari
- ✅ Aggiornamenti regolari

### Performance

```bash
# Nextcloud optimization
nextcloud occ db:add-missing-indices
nextcloud occ db:convert-filecache-bigint
nextcloud occ files:scan --all

# Redis cache
nextcloud occ config:system:set memcache.local --value="\OC\Memcache\Redis"
```

---

## 🔄 Update Workflow

### Update Nextcloud

```bash
pct exec 201 -- nextcloud update
```

### Update Supernova

```bash
pct exec 200 -- supernova update
```

### Update Docker Images

```bash
# Nextcloud
pct exec 201 -- bash -c "cd /opt/nextcloud && docker compose pull && docker compose up -d"

# Supernova
pct exec 200 -- bash -c "cd /opt/supernova-management && docker compose pull && docker compose up -d"
```

---

## 📚 Riferimenti

- [Nextcloud Admin Manual](https://docs.nextcloud.com/server/latest/admin_manual/)
- [Docker Compose Nextcloud](https://github.com/nextcloud/docker)
- [Tailscale Docs](https://tailscale.com/kb/)

---

**Made with ☁️ by Supernova Industries**
