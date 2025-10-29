#!/bin/bash
# Fix Git tracking for files that should be ignored
# This script removes tracked files that should be in .gitignore

echo "Fixing Git tracking for ignored files..."

cd /opt/wharftales

# Remove tracked files that should be ignored
git rm -r --cached data/ 2>/dev/null
git rm -r --cached logs/ 2>/dev/null
git rm -r --cached ssl/ 2>/dev/null
git rm -r --cached volumes/ 2>/dev/null
git rm --cached .env 2>/dev/null
git rm --cached docker-compose.yml 2>/dev/null
git rm -r --cached nginx/sites/*.conf 2>/dev/null
git rm --cached *.log 2>/dev/null
git rm --cached *.tmp 2>/dev/null
git rm --cached *.backup 2>/dev/null
git rm --cached *.bak 2>/dev/null

echo "Done! Files removed from Git tracking but kept on disk."
echo "These files will now be properly ignored according to .gitignore"
echo ""
echo "You may need to commit these changes:"
echo "  git commit -m 'Remove tracked files that should be ignored'"
