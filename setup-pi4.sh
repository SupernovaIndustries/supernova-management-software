#!/bin/bash

echo "=== Setup Supernova su Raspberry Pi 4 ==="

# Check se è ARM64
if [[ $(uname -m) != "aarch64" ]]; then
    echo "Questo script è per Raspberry Pi 4 (ARM64)"
    exit 1
fi

# Aggiorna sistema
sudo apt update && sudo apt upgrade -y

# Installa Docker se non presente
if ! command -v docker &> /dev/null; then
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    sudo usermod -aG docker $USER
    echo "Riavvia e rilancia lo script"
    exit 0
fi

# Installa Docker Compose
sudo apt install docker-compose-plugin -y

# Configura swap se < 2GB
SWAP_SIZE=$(free -m | awk '/^Swap:/ {print $2}')
if [ "$SWAP_SIZE" -lt 1024 ]; then
    echo "Configurando swap file da 2GB..."
    sudo fallocate -l 2G /swapfile
    sudo chmod 600 /swapfile
    sudo mkswap /swapfile
    sudo swapon /swapfile
    echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
fi

# Ottimizza per SSD se presente
if lsblk | grep -q "sda"; then
    echo "SSD rilevato, ottimizzando..."
    echo 'SUBSYSTEM=="block", ATTRS{idVendor}=="*", ATTRS{idProduct}=="*", KERNEL=="sd*", ACTION=="add", RUN+="/bin/echo noop > /sys/block/%k/queue/scheduler"' | sudo tee /etc/udev/rules.d/60-ssd-scheduler.rules
fi

echo "Setup completato! Usa docker-compose.pi.yml per avviare"
echo "Comando: docker-compose -f docker-compose.pi.yml up -d"