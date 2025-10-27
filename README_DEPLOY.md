# 🚀 Supernova Management Software

<div align="center">

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?logo=laravel)
![Filament](https://img.shields.io/badge/Filament-3.2-F59E0B?logo=filament)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php)
![License](https://img.shields.io/badge/license-Proprietary-red.svg)

**Sistema ERP completo per gestione aziendale con AI integrata**

[📖 Documentazione](#-documentazione) •
[⚡ Quick Start](#-quick-start) •
[🏗️ Architettura](#️-architettura) •
[🤖 AI Features](#-ai-features)

</div>

---

## 📋 Panoramica

Supernova Management è un ERP completo costruito con Laravel 11 e Filament 3, progettato per:

### Core Features

- 📦 **Gestione Magazzino** - Componenti elettronici, stock, movimenti
- 👥 **CRM Clienti** - Anagrafiche, contatti, storico ordini
- 📊 **Gestione Progetti** - Planning, milestone, task tracking
- 💰 **Preventivi & Fatturazione** - Sistema completo italiano (PA/B2B/B2C)
- 📄 **Gestione Documenti** - Integrazione Nextcloud nativa
- 🔧 **BOM Management** - Bill of Materials, costi, analisi
- 🤖 **AI Assistant** - Ollama integrato per automazione

### Tech Stack

- **Backend**: Laravel 11 + PHP 8.3
- **Frontend**: Filament 3 + Livewire + Alpine.js + Tailwind CSS
- **Database**: PostgreSQL 16
- **Cache/Queue**: Redis 7
- **Search**: Meilisearch
- **AI**: Ollama (locale, privacy-first)
- **Storage**: Nextcloud
- **Containerization**: Docker + Docker Compose

---

## ⚡ Quick Start

### Per Proxmox (Produzione)

```bash
# 1. Clone repository su Proxmox host
git clone https://github.com/SupernovaIndustries/supernova-management-software.git
cd supernova-management-software/deployment

# 2. Crea container LXC
chmod +x proxmox/*.sh
./proxmox/create-lxc.sh

# 3. Installa applicazione nel container
pct push 200 install-supernova.sh /root/install-supernova.sh
pct exec 200 -- chmod +x /root/install-supernova.sh
pct exec 200 -- /root/install-supernova.sh

# 4. Accedi
# http://<IP_CONTAINER>/admin
```

**👉 Guida completa:** [deployment/README.md](deployment/README.md)

### Per Sviluppo Locale

```bash
# 1. Clone repository
git clone https://github.com/SupernovaIndustries/supernova-management-software.git
cd supernova-management-software

# 2. Install dipendenze
composer install
npm install

# 3. Setup environment
cp .env.example .env
php artisan key:generate

# 4. Start Docker
docker compose up -d

# 5. Setup database
docker compose exec app php artisan migrate --seed

# 6. Build assets
npm run dev

# Accedi a http://localhost
```

**👉 Guida sviluppo:** [CLAUDE.md](CLAUDE.md)

---

## 🏗️ Architettura

### Deployment su Proxmox

```
┌─────────────────────────── PROXMOX HOST ───────────────────────────┐
│                                                                     │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐ │
│  │  LXC Container   │  │  LXC Container   │  │  LXC Container   │ │
│  │                  │  │                  │  │                  │ │
│  │  Supernova Mgmt  │  │  Nextcloud       │  │  Ollama AI       │ │
│  │  + Ollama        │  │                  │  │  (opzionale)     │ │
│  │                  │  │                  │  │                  │ │
│  │  12GB RAM        │  │  12GB RAM        │  │  8GB RAM         │ │
│  │  6 cores         │  │  4 cores         │  │  4 cores         │ │
│  │  50GB disk       │  │  100GB+ disk     │  │  30GB disk       │ │
│  │                  │  │                  │  │                  │ │
│  │  eth0: WAN       │  │  eth0: WAN       │  │  eth0: WAN       │ │
│  │  eth1: 10.0.100. │  │  eth1: 10.0.100. │  │  eth1: 10.0.100. │ │
│  └────────┬─────────┘  └────────┬─────────┘  └────────┬─────────┘ │
│           │                     │                     │            │
│           └─────────────────────┼─────────────────────┘            │
│                                 │                                  │
│                      ┌──────────▼──────────┐                       │
│                      │  vmbr1 Private LAN  │                       │
│                      │   10.0.100.0/24     │                       │
│                      └─────────────────────┘                       │
│                                                                     │
│           ┌─────────────────────────────────────────┐              │
│           │  vmbr0 Public/WAN                       │              │
│           │  Internet access                        │              │
│           └─────────────────────────────────────────┘              │
└─────────────────────────────────────────────────────────────────────┘
```

### Container Stack (Docker Compose)

```
┌────────────────── Supernova Container ──────────────────┐
│                                                          │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐             │
│  │  Nginx   │→ │  PHP-FPM │  │  Queue   │             │
│  │  (Web)   │  │  (App)   │  │  Worker  │             │
│  └──────────┘  └──────────┘  └──────────┘             │
│       ↓              ↓              ↓                   │
│  ┌──────────────────────────────────────┐              │
│  │  Supernova Network (Bridge)          │              │
│  └──────────────────────────────────────┘              │
│       ↓              ↓              ↓                   │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐             │
│  │ Postgres │  │  Redis   │  │Meilisrch │             │
│  │  (DB)    │  │ (Cache)  │  │ (Search) │             │
│  └──────────┘  └──────────┘  └──────────┘             │
│                                                          │
└──────────────────────────────────────────────────────────┘
```

---

## 🤖 AI Features

### Ollama Integration

Supernova integra **Ollama** per AI locale, privacy-first:

#### Funzionalità AI

- ✅ **Analisi Contratti** - Estrazione automatica clausole, termini, alert
- ✅ **Categorizzazione Componenti** - Auto-tag basato su descrizione
- ✅ **Generazione Preventivi** - Assistant per draft veloci
- ✅ **Q&A Tecnico** - Assistente per documentazione
- ✅ **Ricerca Semantica** - Search intelligente nei documenti

#### Modelli Consigliati

| Modello | RAM | Velocità | Qualità | Uso |
|---------|-----|----------|---------|-----|
| **qwen2.5:7b** | 6GB | ⚡⚡⚡ | ⭐⭐⭐⭐⭐ | **Produzione** (consigliato) |
| phi3:mini | 3GB | ⚡⚡⚡⚡⚡ | ⭐⭐⭐ | Dev/Test veloce |
| gemma2:9b | 8GB | ⚡⚡ | ⭐⭐⭐⭐⭐ | Alta qualità |
| llama3.2:3b | 2GB | ⚡⚡⚡⚡ | ⭐⭐⭐ | Risorse limitate |

**👉 Guida completa AI:** [deployment/AI_SETUP.md](deployment/AI_SETUP.md)

---

## 📚 Documentazione

### Setup & Deployment

- 📖 [**Deployment Proxmox**](deployment/README.md) - Guida completa deployment produzione
- 🤖 [**AI Setup (Ollama)**](deployment/AI_SETUP.md) - Configurazione AI locale
- 🐳 [**Docker Compose**](deployment/docker-compose.production.yml) - Stack produzione ottimizzato
- 🔐 [**SSH & Claude Code**](deployment/configure-ssh-claude.sh) - Setup accesso remoto

### Guide Sviluppo

- 🛠️ [**CLAUDE.md**](CLAUDE.md) - Guida per Claude Code (comandi, pattern, best practices)
- 📊 [**Database Schema**](DATABASE_SCHEMA.md) - Schema completo DB
- ☁️ [**Nextcloud Integration**](NEXTCLOUD-IMPLEMENTATION-SUMMARY.md) - Storage cloud
- 📄 [**Document System**](SISTEMA_DOCUMENTI_REPORT.md) - Sistema gestione documenti

### Features Specifiche

- 💰 [**Invoice System**](INVOICE_SYSTEM_SUMMARY.md) - Sistema fatturazione completo
- 📝 [**Contract AI**](AI_CONTRACT_ANALYSIS_README.md) - Analisi contratti con AI
- 🏷️ [**AI Categorization**](AI_CATEGORY_SYSTEM.md) - Sistema categorizzazione automatica
- 📦 [**Component Management**](DATASHEET_SCRAPER_IMPLEMENTATION.md) - Import da suppliers

---

## 🔧 Configurazione

### Requisiti Sistema

**Minimo (Sviluppo):**
- PHP 8.3+
- PostgreSQL 15+
- Redis 7+
- Node.js 18+
- 4GB RAM
- 20GB disk

**Consigliato (Produzione):**
- Container LXC su Proxmox
- 10-12GB RAM
- 4-6 CPU cores
- 50GB SSD
- Ollama con modello 7B

### Environment Variables

```env
# Application
APP_NAME="Supernova Management"
APP_ENV=production
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=supernova
DB_USERNAME=supernova
DB_PASSWORD=<generate-secure-password>

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=<generate-secure-password>
REDIS_PORT=6379

# Nextcloud
NEXTCLOUD_URL=https://your-nextcloud.com
NEXTCLOUD_USERNAME=admin
NEXTCLOUD_PASSWORD=<your-password>

# AI (Ollama)
OLLAMA_API_URL=http://localhost:11434
OLLAMA_MODEL=qwen2.5:7b

# Search
MEILISEARCH_HOST=http://meilisearch:7700
MEILISEARCH_KEY=<generate-secure-key>
```

---

## 📦 Deployment Scripts

### Proxmox

| Script | Descrizione |
|--------|-------------|
| `create-lxc.sh` | Crea container LXC ottimizzato |
| `install-supernova.sh` | Installazione automatica completa |
| `setup-network.sh` | Configura rete privata tra container |
| `configure-ssh-claude.sh` | Setup SSH per Claude Code |

### Management

| Script | Descrizione |
|--------|-------------|
| `setup-systemd-services.sh` | Configura auto-start con systemd |
| `backup.sh` | Backup/restore completo |
| `healthcheck.sh` | Monitoring servizi |

### Comandi Rapidi

```bash
# Nel container
supernova start     # Avvia servizi
supernova stop      # Ferma servizi
supernova restart   # Riavvia
supernova logs      # Visualizza log
supernova update    # Aggiorna da Git
supernova backup    # Backup manuale
```

---

## 🔐 Security

### Features Sicurezza

- ✅ Password hashing con Bcrypt
- ✅ CSRF protection
- ✅ XSS protection
- ✅ SQL injection prevention (Eloquent ORM)
- ✅ Rate limiting su API
- ✅ Two-Factor Authentication (2FA) ready
- ✅ Role-based access control (Filament Policies)
- ✅ Secure file uploads validation
- ✅ Encrypted .env files
- ✅ AI locale (no data leak esterni)

### Best Practices

- 🔐 Mai committare `.env` su Git
- 🔐 Password forti generate automaticamente
- 🔐 SSH key-based authentication
- 🔐 Firewall configurato (UFW)
- 🔐 Container unprivileged su Proxmox
- 🔐 Regular updates & patches

---

## 💾 Backup & Restore

### Backup Automatico

Systemd timer esegue backup giornaliero alle 3 AM:

```bash
# Backup manuale
./deployment/backup.sh backup

# Lista backup
./deployment/backup.sh list

# Restore
./deployment/backup.sh restore /path/to/backup.tar.gz
```

### Contenuto Backup

- ✅ Database PostgreSQL (dump completo)
- ✅ Redis data
- ✅ File .env
- ✅ Storage Laravel (uploads, logs)
- ✅ Dati Nextcloud
- ✅ Configurazioni Docker

**Retention**: 30 giorni (configurabile)

---

## 🐛 Troubleshooting

### Container non parte

```bash
pct status 200
pct start 200
pct enter 200
```

### Servizi Docker down

```bash
docker compose ps
docker compose logs app
docker compose restart
```

### Database issues

```bash
docker compose exec postgres pg_isready
docker compose logs postgres
```

### Ollama non risponde

```bash
systemctl status ollama
journalctl -u ollama -n 50
ollama list
```

**👉 Guida completa:** [deployment/README.md#troubleshooting](deployment/README.md#troubleshooting)

---

## 🚧 Roadmap

### v1.1 (Q1 2025)

- [ ] Multi-tenant support
- [ ] Mobile app (PWA completa)
- [ ] API pubblica REST/GraphQL
- [ ] Integrazione e-commerce
- [ ] Advanced analytics dashboard

### v1.2 (Q2 2025)

- [ ] Fine-tuning AI su dati Supernova
- [ ] RAG per documentazione tecnica
- [ ] Multi-modal AI (analisi immagini PCB)
- [ ] Workflow automation avanzato

---

## 🤝 Contributing

Questo è un progetto proprietario di **Supernova Industries**.

Per contributi interni:
1. Fork del branch `develop`
2. Crea feature branch (`feature/amazing-feature`)
3. Commit seguendo [Conventional Commits](https://www.conventionalcommits.org/)
4. Push e apri Pull Request
5. Code review richiesta

---

## 📞 Support

- 📧 Email: support@supernova.industries
- 📱 Telegram: @SupernovaSupport
- 🐛 Issues: [GitHub Issues](https://github.com/SupernovaIndustries/supernova-management-software/issues)

---

## 📄 License

**Proprietary** - © 2025 Supernova Industries

Tutti i diritti riservati. Uso non autorizzato è proibito.

---

## 🎯 Team

Sviluppato da **Supernova Industries** con ❤️ e ☕

- **Lead Developer**: [@SupernovaIndustries](https://github.com/SupernovaIndustries)
- **AI Integration**: Ollama + Claude Code
- **Stack**: Laravel + Filament + PostgreSQL
- **Infrastructure**: Proxmox + Docker + Nextcloud

---

<div align="center">

**[⬆ back to top](#-supernova-management-software)**

Made with 🚀 by Supernova Industries

</div>
