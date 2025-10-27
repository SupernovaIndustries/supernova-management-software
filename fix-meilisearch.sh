#!/bin/bash

echo "🔧 Fixing MeiliSearch..."

# Create MeiliSearch data directory
sudo mkdir -p /var/lib/meilisearch
sudo chown www-data:www-data /var/lib/meilisearch

# Update MeiliSearch service
sudo tee /etc/systemd/system/meilisearch.service > /dev/null <<EOF
[Unit]
Description=MeiliSearch
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/lib/meilisearch
ExecStart=/usr/local/bin/meilisearch --http-addr 0.0.0.0:7700 --master-key=masterKey --db-path=/var/lib/meilisearch
Restart=on-failure
RestartSec=1

[Install]
WantedBy=multi-user.target
EOF

# Reload and restart
sudo systemctl daemon-reload
sudo systemctl enable meilisearch
sudo systemctl start meilisearch

echo "✅ MeiliSearch fixed!"
systemctl status meilisearch