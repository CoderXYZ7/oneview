#!/bin/bash
# Start PHP Server with increased upload limits (100M)
echo "Starting OneView Server on http://localhost:8000"
echo "Upload Limit: 100M"

php -S localhost:8000 -t public \
    -d upload_max_filesize=100M \
    -d post_max_size=100M \
    -d memory_limit=128M
