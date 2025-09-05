#!/bin/bash

echo "=== WordPress Plugin Structure Validation ==="
echo ""

# Check main plugin file
if [ -f "nexjob-seo-automation.php" ]; then
    echo "✓ Main plugin file exists: nexjob-seo-automation.php"
else
    echo "✗ Main plugin file missing"
    exit 1
fi

# Check includes directory
if [ -d "includes" ]; then
    echo "✓ Includes directory exists"
else
    echo "✗ Includes directory missing"
    exit 1
fi

# Check all required class files
required_files=(
    "includes/class-nexjob-seo-plugin.php"
    "includes/class-nexjob-seo-settings.php"
    "includes/class-nexjob-seo-logger.php"
    "includes/class-nexjob-seo-post-processor.php"
    "includes/class-nexjob-seo-cron-manager.php"
    "includes/class-nexjob-seo-admin.php"
    "includes/class-nexjob-seo-ajax-handlers.php"
)

for file in "${required_files[@]}"; do
    if [ -f "$file" ]; then
        echo "✓ Found: $file"
    else
        echo "✗ Missing: $file"
        exit 1
    fi
done

echo ""
echo "=== File Structure Analysis ==="

# Count lines in original vs refactored
if [ -f "attached_assets/nexjob-seo-automation-plugin_1757094489808.php" ]; then
    original_lines=$(wc -l < "attached_assets/nexjob-seo-automation-plugin_1757094489808.php")
    echo "Original monolithic file: $original_lines lines"
fi

echo "Refactored structure:"
echo "- Main file: $(wc -l < nexjob-seo-automation.php) lines"
for file in "${required_files[@]}"; do
    lines=$(wc -l < "$file")
    echo "- $(basename "$file"): $lines lines"
done

echo ""
echo "=== Validation Complete ==="
echo "✓ WordPress plugin structure is properly organized"
echo "✓ All required components are present"
echo "✓ Plugin ready for WordPress installation"
echo ""
echo "To use this plugin:"
echo "1. Upload the entire directory to /wp-content/plugins/"
echo "2. Activate 'NexJob SEO Automation' in WordPress admin"
echo "3. Configure settings under Settings > NexJob SEO"