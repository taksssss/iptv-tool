# Apache to Nginx Migration Guide

This repository has been migrated from Apache to Nginx. Here are the key changes:

## Changes Made

### Web Server Stack
- **Before**: Apache httpd + mod_php
- **After**: Nginx + PHP-FPM

### Performance Benefits
- ✅ Lower memory usage
- ✅ Better concurrent connection handling  
- ✅ Improved static file serving
- ✅ More efficient reverse proxy capabilities

### Configuration Files
- `Dockerfile`: Updated to use `php:8.2-fpm-alpine` base image with Nginx
- `docker-entrypoint.sh`: Reconfigured to start both nginx and php-fpm
- `nginx.conf`: New nginx configuration with equivalent functionality

### URL Rewriting 
All Apache mod_rewrite rules have been converted to equivalent Nginx location blocks:

| Route | Function |
|-------|----------|
| `/tv.m3u` | M3U playlist endpoint |
| `/tv.txt` | TXT format endpoint |  
| `/t.xml` | XML format endpoint |
| `/t.xml.gz` | Compressed XML endpoint |

### Access Controls
Directory access controls have been preserved:
- Block access to `/data` directory
- Allow access to `/data/icon` subdirectory

## For Users

No changes required for API usage - all endpoints work the same way. The migration is completely transparent to end users.

## For Developers

If you're building custom images or modifying the configuration:

1. Use `nginx.conf` for web server configuration
2. PHP settings are now in PHP-FPM configuration
3. User/group changed from `apache` to `nginx`
4. Two processes now run: `nginx` and `php-fpm`

## Testing

Run the included test script to verify configuration:
```bash
./nginx-test.sh
```