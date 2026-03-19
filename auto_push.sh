#!/bin/bash

# Automated GitHub Push Script
# This will stage, commit, and push all changes

set -e  # Exit on any error

echo "=========================================="
echo "  GitHub Auto Push Script"
echo "=========================================="
echo ""

# Stage all changes
echo "📦 Staging all changes..."
git add .

# Check if there are changes to commit
if git diff --cached --quiet; then
    echo "✓ No changes to commit. Everything is up to date!"
    exit 0
fi

# Show what will be committed
echo ""
echo "📝 Files to be committed:"
git diff --cached --name-status | head -20
TOTAL_FILES=$(git diff --cached --name-status | wc -l)
if [ $TOTAL_FILES -gt 20 ]; then
    echo "... and $((TOTAL_FILES - 20)) more files"
fi

# Commit
echo ""
COMMIT_MSG="Update project: $(date '+%Y-%m-%d %H:%M:%S')"
echo "💾 Committing with message: $COMMIT_MSG"
git commit -m "$COMMIT_MSG"

# Push with token
echo ""
echo "🚀 Pushing to GitHub..."
echo ""
echo "Please enter your GitHub Personal Access Token:"
echo "(Input will be hidden for security)"
read -s GITHUB_TOKEN

if [ -z "$GITHUB_TOKEN" ]; then
    echo ""
    echo "❌ Error: No token provided"
    exit 1
fi

echo ""
echo "Pushing to: hazemaligalal000-afk/nexsaas-modular-crm"

# Push using token
git push https://${GITHUB_TOKEN}@github.com/hazemaligalal000-afk/nexsaas-modular-crm.git main 2>&1

if [ $? -eq 0 ]; then
    echo ""
    echo "=========================================="
    echo "  ✅ Successfully pushed to GitHub!"
    echo "=========================================="
    echo ""
    echo "View your repository at:"
    echo "https://github.com/hazemaligalal000-afk/nexsaas-modular-crm"
else
    echo ""
    echo "❌ Push failed. Please check:"
    echo "  - Your token is valid and not expired"
    echo "  - You have write access to the repository"
    echo "  - Your internet connection is working"
    exit 1
fi
