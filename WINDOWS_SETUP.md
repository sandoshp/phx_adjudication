# Phase 2 Setup for Windows XAMPP

## Install Composer on Windows

### Option A: Download Composer Installer (Recommended)

1. **Download Composer-Setup.exe**
   - Go to: https://getcomposer.org/download/
   - Click "Composer-Setup.exe" under Windows Installer
   - Run the installer

2. **During Installation:**
   - It will detect your PHP installation (XAMPP)
   - Select your XAMPP PHP executable (e.g., `C:\xampp\php\php.exe`)
   - Complete the installation

3. **Verify Installation:**
   ```cmd
   composer --version
   ```

### Option B: Manual Installation

1. **Download composer.phar**
   ```cmd
   cd C:\xampp\htdocs\phx_adjudication
   php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
   php composer-setup.php
   php -r "unlink('composer-setup.php');"
   ```

2. **Use composer.phar**
   ```cmd
   php composer.phar install
   ```

## Install Dependencies

Once Composer is installed:

```cmd
# Navigate to your project directory
cd C:\xampp\htdocs\phx_adjudication

# Install dependencies
composer install

# Or if using composer.phar:
php composer.phar install
```

## Verify Installation

```cmd
# Check if vendor directory was created
dir vendor

# Check if PHPSpreadsheet is installed
dir vendor\phpoffice\phpspreadsheet
```

## Run CTCAE Import (After Dependencies Installed)

```cmd
# Place your Excel file in data\ folder first
# Then run:

# Dry run
php scripts\import_ctcae_v6.php --dry-run --verbose

# Actual import
php scripts\import_ctcae_v6.php
```

## Common Windows Issues

### Issue 1: "php is not recognized"
Add PHP to your PATH:
1. Open System Properties → Advanced → Environment Variables
2. Edit PATH variable
3. Add: `C:\xampp\php`
4. Restart Command Prompt

### Issue 2: Extension errors
Enable required extensions in `C:\xampp\php\php.ini`:
```ini
extension=zip
extension=gd
extension=mbstring
extension=pdo_mysql
```

Restart Apache after changes.

### Issue 3: Memory limit
In `php.ini`, increase:
```ini
memory_limit = 512M
```

## Alternative: Copy vendor from Server

If Composer installation is problematic, you can copy the vendor folder from the server:

```cmd
# On your server (Linux):
tar -czf vendor.tar.gz vendor/

# Download vendor.tar.gz to Windows
# Then extract to your project directory
```

## Next Steps After Installation

1. Place CTCAE v6.0 Excel file in `data\` folder
2. Run: `php scripts\import_ctcae_v6.php --dry-run`
3. Run: `php scripts\import_ctcae_v6.php`
4. Test: `php tests\phase2_test.php`
