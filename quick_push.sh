#!/bin/bash

# Quick GitHub Push
echo "Staging all changes..."
git add .

echo ""
read -p "Commit message (or press Enter for 'Update project'): " MSG
MSG=${MSG:-"Update project"}

git commit -m "$MSG"

echo ""
echo "Now run this command and paste your token when prompted:"
echo ""
echo "git push https://github.com/hazemaligalal000-afk/nexsaas-modular-crm.git main"
echo ""
echo "When prompted for username: hazemaligalal000-afk"
echo "When prompted for password: paste your GitHub token"
