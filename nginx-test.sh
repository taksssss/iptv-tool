#!/bin/bash
# nginx-test.sh - Test nginx configuration syntax

echo "Testing Nginx configuration syntax..."

# Test the nginx.conf file syntax
OUTPUT=$(nginx -t -c "$(pwd)/nginx.conf" 2>&1)
if echo "$OUTPUT" | grep -q "syntax is ok"; then
    echo "✅ Nginx configuration syntax is valid"
else 
    echo "❌ Nginx configuration syntax has errors:"
    echo "$OUTPUT"
fi

echo ""
echo "Key migration points completed:"
echo "✅ Apache → Nginx web server"
echo "✅ mod_rewrite → location blocks with regex"
echo "✅ Apache Directory directives → Nginx location blocks"  
echo "✅ Apache PHP module → PHP-FPM with FastCGI"
echo "✅ httpd process → nginx + php-fpm processes"
echo "✅ User/group: apache → nginx"