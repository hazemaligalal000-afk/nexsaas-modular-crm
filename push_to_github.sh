#!/bin/bash

# GitHub Push Script with Personal Access Token
# Usage: ./push_to_github.sh

echo "=== GitHub Push Helper ==="
echo ""

# Check if git is initialized
if [ ! -d .git ]; then
    echo "Error: Not a git repository. Run 'git init' first."
    exit 1
fi

# Get current remote URL
CURRENT_REMOTE=$(git remote get-url origin 2>/dev/null)

if [ -z "$CURRENT_REMOTE" ]; then
    echo "No remote 'origin' found."
    read -p "Enter your GitHub repository URL (e.g., https://github.com/username/repo.git): " REPO_URL
    git remote add origin "$REPO_URL"
    echo "Remote 'origin' added: $REPO_URL"
else
    echo "Current remote: $CURRENT_REMOTE"
fi

# Prompt for Personal Access Token
echo ""
echo "Enter your GitHub Personal Access Token:"
echo "(The token will not be displayed as you type)"
read -s GITHUB_TOKEN

if [ -z "$GITHUB_TOKEN" ]; then
    echo "Error: No token provided."
    exit 1
fi

# Extract username and repo from remote URL
REMOTE_URL=$(git remote get-url origin)
if [[ $REMOTE_URL =~ github\.com[:/]([^/]+)/(.+)(\.git)?$ ]]; then
    USERNAME="${BASH_REMATCH[1]}"
    REPO="${BASH_REMATCH[2]%.git}"
else
    echo "Error: Could not parse GitHub URL"
    exit 1
fi

echo ""
echo "Repository: $USERNAME/$REPO"
echo ""

# Stage all changes
echo "Staging all changes..."
git add .

# Commit
read -p "Enter commit message (or press Enter for default): " COMMIT_MSG
if [ -z "$COMMIT_MSG" ]; then
    COMMIT_MSG="Update project files"
fi

git commit -m "$COMMIT_MSG"

# Get current branch
BRANCH=$(git branch --show-current)
if [ -z "$BRANCH" ]; then
    BRANCH="main"
fi

echo ""
echo "Pushing to branch: $BRANCH"

# Push using token authentication
git push https://${GITHUB_TOKEN}@github.com/${USERNAME}/${REPO}.git $BRANCH

if [ $? -eq 0 ]; then
    echo ""
    echo "✓ Successfully pushed to GitHub!"
else
    echo ""
    echo "✗ Push failed. Please check your token and permissions."
    exit 1
fi
