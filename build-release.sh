#!/bin/bash

# Build script for WooCommerce Subscription Date Manager Pro
# Version 1.0.2

echo "Building release package..."

# Create build directory
mkdir -p build
rm -rf build/woo-subscription-date-manager

# Copy plugin files
cp -r . build/woo-subscription-date-manager

# Remove development files
cd build/woo-subscription-date-manager
rm -rf .git
rm -rf .gitignore
rm -rf build-release.sh
rm -rf node_modules
rm -rf .vscode
rm -rf .idea
rm -f *.log
rm -f *.tmp

# Create ZIP file
cd ..
zip -r woo-subscription-date-manager-v1.0.2.zip woo-subscription-date-manager

echo "Release package created: build/woo-subscription-date-manager-v1.0.2.zip"
echo "Plugin ready for distribution!"