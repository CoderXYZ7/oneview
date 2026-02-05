#!/bin/bash

# Define paths
PRIVATE_DIR="./private"
UPLOADS_DIR="$PRIVATE_DIR/uploads"
DATA_FILE="$PRIVATE_DIR/data.json"

echo "Setting up OneView permissions..."

# Create directories if they don't exist
mkdir -p "$UPLOADS_DIR"

# Ensure data.json exists
if [ ! -f "$DATA_FILE" ]; then
    echo "[]" > "$DATA_FILE"
    echo "Created data.json"
fi

# Set permissions
# In a real production environment, you'd want to set ownership to www-data:www-data
# and permissions to 770 or 750.
# For this development/standalone setup, we will make them world-writable to ensure PHP can write.

chmod 777 "$PRIVATE_DIR"
chmod 777 "$UPLOADS_DIR"
chmod 666 "$DATA_FILE"

# Setup Log File
LOG_FILE="$PRIVATE_DIR/app.log"
touch "$LOG_FILE"
chmod 666 "$LOG_FILE"
echo "Created app.log"

echo "Permissions set:"
ls -ld "$PRIVATE_DIR"
ls -ld "$UPLOADS_DIR"
ls -l "$DATA_FILE"
ls -l "$LOG_FILE"

echo "Setup complete."
