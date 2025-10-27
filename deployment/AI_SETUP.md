# 🤖 AI Setup Guide - Ollama per Supernova Management

Guida completa per configurare l'AI locale con Ollama per Supernova Management.

## 📋 Indice

- [Perché Ollama?](#perché-ollama)
- [Requisiti Hardware](#requisiti-hardware)
- [Installazione](#installazione)
- [Modelli Disponibili](#modelli-disponibili)
- [Configurazione](#configurazione)
- [Testing](#testing)
- [Performance Tuning](#performance-tuning)
- [Troubleshooting](#troubleshooting)

---

## 🎯 Perché Ollama?

### Vantaggi

✅ **Privacy Totale**: Tutto rimane nei tuoi server
✅ **Zero Costi API**: Nessun abbonamento mensile
✅ **Bassa Latenza**: Risposta immediata
✅ **Offline-First**: Funziona senza internet
✅ **Open Source**: Modelli aperti e customizzabili

### Casi d'Uso in Supernova

- 📝 Analisi automatica contratti
- 🏷️ Categorizzazione componenti elettronici
- 💬 Assistente per preventivi
- 📊 Analisi documenti e BOM
- 🔍 Ricerca semantica intelligente

---

## 💻 Requisiti Hardware

### Minimo (per modelli 3B-7B)

- **RAM**: 6-8GB disponibili
- **CPU**: 4+ cores
- **Disk**: 10-15GB per modello

### Consigliato (per modelli 7B-13B)

- **RAM**: 12-16GB disponibili
- **CPU**: 6+ cores con AVX2
- **Disk**: 20-30GB per più modelli

### GPU (opzionale ma consigliato)

- **NVIDIA**: GTX 1660+ (6GB VRAM)
- **AMD**: RX 6600+ (8GB VRAM)
- Accelerazione 5-10x più veloce

---

## 📥 Installazione

### Metodo 1: Automatico (Durante install-supernova.sh)

Lo script di installazione chiede automaticamente se vuoi installare Ollama:

```bash
./install-supernova.sh
# → Vuoi installare Ollama? [Y/n]: y
# → Scegli modello [1-4]: 1 (qwen2.5:7b)
```

### Metodo 2: Manuale

```bash
# Download e install script ufficiale
curl -fsSL https://ollama.com/install.sh | sh

# Verifica installazione
ollama --version
# ollama version 0.1.47

# Avvia servizio
sudo systemctl enable ollama
sudo systemctl start ollama

# Test
ollama list
```

### Metodo 3: Docker (alternativo)

```bash
# Run Ollama in Docker
docker run -d \
  --name ollama \
  --restart unless-stopped \
  -v ollama:/root/.ollama \
  -p 11434:11434 \
  ollama/ollama:latest

# Download modello
docker exec ollama ollama pull qwen2.5:7b
```

---

## 🧠 Modelli Disponibili

### Raccomandati per Supernova

#### 1. 🥇 Qwen2.5:7B (Consigliato)

```bash
ollama pull qwen2.5:7b
```

**Caratteristiche:**
- **RAM**: ~6GB
- **Dimensione**: 4.7GB
- **Velocità**: ⚡⚡⚡ (8-12 tok/s CPU)
- **Qualità**: ⭐⭐⭐⭐⭐
- **Lingue**: Eccellente italiano + inglese

**Ideale per:**
- ✅ Analisi contratti complessi
- ✅ Categorizzazione tecnica
- ✅ Generazione preventivi
- ✅ Q&A tecnico

#### 2. 🥈 Phi3:Mini

```bash
ollama pull phi3:mini
```

**Caratteristiche:**
- **RAM**: ~3GB
- **Dimensione**: 2.3GB
- **Velocità**: ⚡⚡⚡⚡⚡ (15-20 tok/s CPU)
- **Qualità**: ⭐⭐⭐
- **Lingue**: Buono italiano

**Ideale per:**
- ✅ Categorizzazione veloce
- ✅ Estrazione dati semplici
- ✅ Tag suggestions
- ✅ Dev/Test environment

#### 3. 🥉 Gemma2:9B

```bash
ollama pull gemma2:9b
```

**Caratteristiche:**
- **RAM**: ~8GB
- **Dimensione**: 5.4GB
- **Velocità**: ⚡⚡ (5-8 tok/s CPU)
- **Qualità**: ⭐⭐⭐⭐⭐
- **Lingue**: Ottimo italiano

**Ideale per:**
- ✅ Analisi dettagliate
- ✅ Documentazione tecnica
- ✅ Quando hai RAM disponibile

#### 4. Llama3.2:3B (Ultra-leggero)

```bash
ollama pull llama3.2:3b
```

**Caratteristiche:**
- **RAM**: ~2GB
- **Dimensione**: 2GB
- **Velocità**: ⚡⚡⚡⚡ (12-18 tok/s CPU)
- **Qualità**: ⭐⭐⭐
- **Lingue**: Discreto italiano

**Ideale per:**
- ✅ Setup con poca RAM
- ✅ Operazioni batch veloci
- ✅ Testing

### Confronto Velocità

**CPU Intel i5/i7 (senza GPU):**

| Modello | Tokens/sec | Tempo 100 tokens |
|---------|------------|------------------|
| llama3.2:3b | 15-20 | ~5-7s |
| phi3:mini | 15-18 | ~5-7s |
| qwen2.5:7b | 8-12 | ~8-12s |
| gemma2:9b | 5-8 | ~12-20s |

**Con GPU NVIDIA GTX 1660:**

| Modello | Tokens/sec | Tempo 100 tokens |
|---------|------------|------------------|
| llama3.2:3b | 80-100 | <2s |
| phi3:mini | 70-90 | <2s |
| qwen2.5:7b | 40-60 | ~2-3s |
| gemma2:9b | 30-45 | ~2-4s |

---

## ⚙️ Configurazione

### 1. Configurazione Ollama Service

```bash
# Edit service file
sudo systemctl edit ollama.service
```

Aggiungi:

```ini
[Service]
Environment="OLLAMA_HOST=0.0.0.0:11434"
Environment="OLLAMA_NUM_PARALLEL=2"
Environment="OLLAMA_MAX_LOADED_MODELS=1"
Environment="OLLAMA_KEEP_ALIVE=5m"
```

Restart:

```bash
sudo systemctl daemon-reload
sudo systemctl restart ollama
```

### 2. Configurazione Supernova (.env)

```env
# AI Configuration
AI_PROVIDER=ollama
OLLAMA_API_URL=http://localhost:11434
OLLAMA_MODEL=qwen2.5:7b

# Per setup con Ollama su container separato
# OLLAMA_API_URL=http://ollama.local:11434

# Ollama Performance Settings
OLLAMA_TIMEOUT=60
OLLAMA_MAX_TOKENS=2048
```

### 3. Multi-Model Setup

Se hai RAM abbondante, puoi caricare più modelli:

```bash
# Download multipli
ollama pull qwen2.5:7b     # Per analisi complesse
ollama pull phi3:mini      # Per categorizzazione veloce

# In .env puoi cambiare al volo
OLLAMA_MODEL=qwen2.5:7b    # Per contratti
# OLLAMA_MODEL=phi3:mini   # Per categorizzazione
```

---

## 🧪 Testing

### Test Base

```bash
# Test API
curl http://localhost:11434/api/tags

# Test generazione
curl http://localhost:11434/api/generate -d '{
  "model": "qwen2.5:7b",
  "prompt": "Ciao, come stai?",
  "stream": false
}'
```

### Test da Supernova

```bash
# Dentro container Docker
docker compose exec app php artisan tinker

# In tinker:
use App\Services\OllamaService;
$ollama = app(OllamaService::class);
$result = $ollama->categorizeComponent('Resistore 10k 0805 1%');
dd($result);
```

### Benchmark Performance

```bash
# Script di benchmark
cat > /tmp/ollama-bench.sh << 'EOF'
#!/bin/bash
echo "Benchmark Ollama - $(date)"
echo "Model: $1"
echo "---"

time curl -s http://localhost:11434/api/generate -d "{
  \"model\": \"$1\",
  \"prompt\": \"Analizza questo componente elettronico: Resistore SMD 0805 10kΩ ±1% 1/8W. Fornisci categoria, applicazioni e specifiche tecniche.\",
  \"stream\": false
}" | jq -r '.response'
EOF

chmod +x /tmp/ollama-bench.sh

# Run benchmark
./ollama-bench.sh qwen2.5:7b
```

---

## 🚀 Performance Tuning

### CPU Optimization

```bash
# Limita thread per evitare overhead
export OLLAMA_NUM_THREAD=4

# Per CPU AMD con AVX2
export OLLAMA_ACCELERATE=1
```

### Memory Management

```bash
# Limita cache context per risparmiare RAM
export OLLAMA_MAX_LOADED_MODELS=1
export OLLAMA_KEEP_ALIVE=3m  # Unload dopo 3 min inattività
```

### GPU Setup (NVIDIA)

```bash
# Installa NVIDIA Container Toolkit
distribution=$(. /etc/os-release;echo $ID$VERSION_ID)
curl -fsSL https://nvidia.github.io/libnvidia-container/gpgkey | sudo gpg --dearmor -o /usr/share/keyrings/nvidia-container-toolkit-keyring.gpg
curl -s -L https://nvidia.github.io/libnvidia-container/$distribution/libnvidia-container.list | \
  sed 's#deb https://#deb [signed-by=/usr/share/keyrings/nvidia-container-toolkit-keyring.gpg] https://#g' | \
  sudo tee /etc/apt/sources.list.d/nvidia-container-toolkit.list

sudo apt update
sudo apt install -y nvidia-container-toolkit
sudo systemctl restart docker

# Verifica GPU
nvidia-smi

# Ollama userà automaticamente GPU se disponibile
```

### Batch Processing

Per operazioni batch (categorizzazione massiva):

```php
// In Supernova
use App\Services\OllamaService;

$ollama = app(OllamaService::class);

// Categorizza 100 componenti
$components = Component::whereNull('ai_category')->limit(100)->get();

foreach ($components as $component) {
    $category = $ollama->categorizeComponent($component->description);
    $component->update(['ai_category' => $category]);

    // Sleep per non sovraccaricare
    usleep(500000); // 0.5s
}
```

---

## 🐛 Troubleshooting

### Ollama non risponde

```bash
# Verifica servizio
sudo systemctl status ollama

# Log
sudo journalctl -u ollama -n 50 -f

# Restart
sudo systemctl restart ollama

# Test diretto
curl http://localhost:11434/api/tags
```

### Out of Memory

```bash
# Usa modello più piccolo
ollama pull phi3:mini
ollama pull llama3.2:3b

# Configura keep_alive più corto
export OLLAMA_KEEP_ALIVE=1m
sudo systemctl restart ollama
```

### Lentezza estrema

**Cause comuni:**
- ❌ CPU vecchia senza AVX2
- ❌ Troppi processi in background
- ❌ Swap attivo

**Soluzioni:**

```bash
# 1. Verifica CPU features
lscpu | grep -i avx

# 2. Riduci parallelism
export OLLAMA_NUM_PARALLEL=1

# 3. Usa modello più leggero
ollama pull phi3:mini

# 4. Controlla RAM
free -h

# 5. Disabilita swap (se hai RAM sufficiente)
sudo swapoff -a
```

### Errori "model not found"

```bash
# Lista modelli scaricati
ollama list

# Riscarica modello
ollama pull qwen2.5:7b

# Verifica path
ls -la ~/.ollama/models/
```

### Docker communication issues

```bash
# Se Ollama è su host e Docker in container
# Usa host.docker.internal

# In .env dentro container
OLLAMA_API_URL=http://host.docker.internal:11434

# Oppure usa IP dell'host
OLLAMA_API_URL=http://172.17.0.1:11434
```

---

## 📊 Monitoring

### Prometheus Metrics (opzionale)

Ollama espone metriche su `/metrics`:

```bash
curl http://localhost:11434/metrics
```

### Script di monitoring semplice

```bash
#!/bin/bash
# /usr/local/bin/ollama-monitor.sh

while true; do
  clear
  echo "=== Ollama Monitor ==="
  echo "Time: $(date)"
  echo

  # Status
  systemctl is-active ollama && echo "✓ Service: Running" || echo "✗ Service: Stopped"

  # Models loaded
  echo
  echo "Loaded models:"
  curl -s http://localhost:11434/api/tags | jq -r '.models[].name'

  # Memory usage
  echo
  echo "Memory:"
  ps aux | grep ollama | grep -v grep | awk '{print "CPU: "$3"% | RAM: "$4"%"}'

  sleep 5
done
```

---

## 💡 Best Practices

### 1. Scelta Modello

- **Produzione**: `qwen2.5:7b` - Miglior compromesso
- **Dev/Test**: `phi3:mini` - Veloce per iterare
- **Alta qualità**: `gemma2:9b` - Se hai RAM
- **Risorse limitate**: `llama3.2:3b` - Minimo viable

### 2. Resource Management

```bash
# Monitora uso risorse
docker stats ollama  # Se in Docker
# oppure
ps aux | grep ollama

# Limita se necessario
systemctl edit ollama.service
# Aggiungi:
[Service]
MemoryLimit=8G
CPUQuota=400%  # 4 cores
```

### 3. Update Regolare

```bash
# Update Ollama
curl -fsSL https://ollama.com/install.sh | sh

# Update modelli
ollama pull qwen2.5:7b
```

### 4. Backup Modelli

```bash
# Modelli sono in ~/.ollama/models/
tar czf ollama-models-backup.tar.gz ~/.ollama/models/

# Restore
tar xzf ollama-models-backup.tar.gz -C ~/
```

---

## 🔗 Risorse Utili

- 📚 [Ollama Documentation](https://github.com/ollama/ollama/blob/main/docs/README.md)
- 🎯 [Model Library](https://ollama.com/library)
- 💬 [Ollama Discord](https://discord.gg/ollama)
- 🐛 [GitHub Issues](https://github.com/ollama/ollama/issues)

---

## 📝 Prossimi Sviluppi

- [ ] Fine-tuning per dati Supernova-specific
- [ ] Embedding per ricerca semantica
- [ ] Multi-modal (immagini PCB)
- [ ] RAG con documentazione tecnica

---

**Made with 🤖 by Supernova Industries**
