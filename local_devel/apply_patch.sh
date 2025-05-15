#!/bin/sh

# Hotfix of incompatible packages pestphp vs termwind

# Check if required tools are installed
command -v cksum >/dev/null 2>&1 || { echo "Error: cksum is required but not installed."; exit 1; }
command -v patch >/dev/null 2>&1 || { echo "Error: patch is required but not installed."; exit 1; }

PATCH_TARGET_FILE="./vendor/nunomaduro/termwind/src/HtmlRenderer.php"
PATCH_DIR="./local_devel/patch"

CURRENT_CRC=$(cksum "$PATCH_TARGET_FILE" | awk '{print toupper($1)}')

# Try to find a matching patch file based on the current CRC
PATCH_FILE=$(find "$PATCH_DIR" -type f -name "termwind_HtmlRenderer_${CURRENT_CRC}.patch" 2>/dev/null)

if [ -n "$PATCH_FILE" ]; then
    echo "Applying patch for CRC ${CURRENT_CRC}..."
    patch "$PATCH_TARGET_FILE" < "$PATCH_FILE"
fi
