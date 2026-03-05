#!/bin/bash
# Run this ONCE in cPanel Terminal to fix uncommitted changes, then use cPanel UI or push-to-deploy.
# Usage: cd /home/npgcozw/public_html/live && bash deploy.sh

cd /home/npgcozw/public_html/live || exit 1
git checkout master
git pull origin master
git reset --hard HEAD
git clean -fd
echo "Done. Now click 'Deploy HEAD Commit' in cPanel Git interface."
