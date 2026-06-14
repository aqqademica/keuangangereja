#!/bin/bash

# Target folder
DEPLOYPATH="/home/gere1931/public_html/egereja.my.id"

# Create destination folder if it doesn't exist
/bin/mkdir -p "$DEPLOYPATH"

# Check if rsync is available on the system
if command -v rsync >/dev/null 2>&1; then
    echo "Using rsync for deployment..."
    rsync -av --exclude='.git*' --exclude='.cpanel.yml' --exclude='schema.sql' --exclude='config/database.php' --exclude='deploy.sh' ./ "$DEPLOYPATH/"
else
    echo "rsync not found. Using cp fallback..."
    # 1. Backup production database config if it exists
    if [ -f "$DEPLOYPATH/config/database.php" ]; then
        /bin/cp "$DEPLOYPATH/config/database.php" "/home/gere1931/tmp_db_config_bak.php"
    fi

    # 2. Copy all files (including hidden dotfiles) to deploy path
    /bin/cp -R ./. "$DEPLOYPATH/"

    # 3. Restore production database config if it was backed up
    if [ -f "/home/gere1931/tmp_db_config_bak.php" ]; then
        /bin/mv "/home/gere1931/tmp_db_config_bak.php" "$DEPLOYPATH/config/database.php"
    fi

    # 4. Remove unnecessary development/git/script files from production
    /bin/rm -rf "$DEPLOYPATH/.git" "$DEPLOYPATH/.gitignore" "$DEPLOYPATH/.cpanel.yml" "$DEPLOYPATH/schema.sql" "$DEPLOYPATH/deploy.sh"
fi

echo "Deployment finished successfully!"
