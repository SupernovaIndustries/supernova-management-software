#!/bin/bash

# Stop any running nginx instances
brew services stop nginx 2>/dev/null
killall nginx 2>/dev/null

# Clean up old pid file
rm -f /opt/homebrew/var/run/nginx.pid

# Create temp directories
mkdir -p /tmp/nginx/{client_body_temp,fastcgi_temp,proxy_temp,scgi_temp,uwsgi_temp}

# Start nginx
/opt/homebrew/opt/nginx/bin/nginx -g 'daemon off;'