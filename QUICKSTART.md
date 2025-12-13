# Quick Start Guide - PHOENIX Adjudication Improvements

## Getting Started

### Prerequisites
- PHP 8.0 or higher
- MySQL 8.0 or higher
- Composer
- Git
- Access to database

### Step 1: Backup Current System

```bash
# Backup database
mysqldump -u sandosh -p phoenix > backups/phoenix_backup_$(date +%Y%m%d_%H%M%S).sql

# Backup code (if not already in git)
tar -czf backups/code_backup_$(date +%Y%m%d_%H%M%S).tar.gz \
    --exclude='.git' \
    --exclude='backups' \
    --exclude='vendor' \
    .
```

### Step 2: Set Up Environment

```bash
# Create necessary directories
mkdir -p tests/{unit,integration,fixtures}
mkdir -p docs/{api,user,technical}
mkdir -p backups/migrations
mkdir -p migrations
mkdir -p scripts
mkdir -p data
mkdir -p inc/templates
mkdir -p public/assets/css

# Copy environment template
cp .env.example .env

# Edit .env with your credentials
nano .env
```

### Step 3: Install Dependencies

```bash
# Install Composer if not present
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Install PHP dependencies
composer install
```

### Step 4: Secure Configuration

```bash
# Move credentials from inc/config.php to .env
# Edit inc/config.php to load from environment variables
nano inc/config.php
```

Update `inc/config.php`:
```php
<?php
// Load environment variables from .env file
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}

return [
    'db_host'      => getenv('DB_HOST') ?: 'localhost',
    'db_name'      => getenv('DB_NAME') ?: 'phoenix',
    'db_user'      => getenv('DB_USER') ?: 'phoenix_user',
    'db_pass'      => getenv('DB_PASS') ?: '',
    'db_charset'   => 'utf8mb4',
    'session_name' => 'phoenix_session',
    'debug'        => getenv('APP_ENV') !== 'production',
    'version'      => '1.0.0'
];
```

### Step 5: Run Phase 1 - Database Enhancement

```bash
# Review migration script
cat migrations/001_schema_enhancements.sql

# Apply migration
mysql -u your_user -p phoenix < migrations/001_schema_enhancements.sql

# Run tests
php tests/phase1_test.php
```

If tests pass:
```bash
# Create post-migration backup
mysqldump -u your_user -p phoenix > backups/post_phase1_$(date +%Y%m%d).sql
```

If tests fail:
```bash
# Rollback
mysql -u your_user -p phoenix < migrations/001_rollback.sql

# Investigate issues
# Fix and retry
```

### Step 6: Run Phase 2 - CTCAE v6.0 Import

```bash
# Place CTCAE Excel file
cp "/path/to/CTCAE v6.0 Final Clean-Tracked-Mapping_w_OS_July2025.xlsx" data/

# Run import script
php scripts/import_ctcae_v6.php

# Run tests
php tests/phase2_test.php
```

### Step 7: Run Phase 3 - Materialize CSS

```bash
# Create template files (see IMPROVEMENT_STRATEGY.md Phase 3)
# Then test in browser
open http://localhost/public/dashboard_materialize.php

# Run automated tests
php tests/phase3_test.php
```

## Phase Progression

After completing each phase:

1. ✅ Run automated tests
2. ✅ Run manual testing checklist
3. ✅ Create database backup
4. ✅ Document any issues/deviations
5. ✅ Commit changes to git
6. ✅ Move to next phase

## Git Workflow

```bash
# For each phase, create a feature branch
git checkout -b feature/phase-1-database-enhancement

# Make changes, test, commit
git add .
git commit -m "Phase 1: Database schema enhancements with CTCAE v6 support"

# Push to remote
git push origin feature/phase-1-database-enhancement

# Create pull request for review
# After approval, merge to main branch
```

## Monitoring Progress

Track your progress:

```bash
# Check which phases are complete
ls -l backups/post_phase*.sql

# View migration history
mysql -u your_user -p phoenix -e "SELECT * FROM system_config WHERE config_key='schema_version';"

# Check test results
for i in {1..12}; do
    if [ -f tests/phase${i}_test.php ]; then
        echo "Phase $i: Testing..."
        php tests/phase${i}_test.php && echo "✓ PASSED" || echo "✗ FAILED"
    fi
done
```

## Common Issues

### Issue: Database connection fails
**Solution:** Check .env credentials, ensure MySQL is running

### Issue: Composer install fails
**Solution:** Check PHP version (`php -v`), ensure >= 8.0

### Issue: Migration fails midway
**Solution:** Run rollback script, investigate error, fix, retry

### Issue: CTCAE import fails
**Solution:**
- Check Excel file path is correct
- Verify column headers match expected format
- Check for special characters in data

### Issue: Materialize CSS not loading
**Solution:**
- Check CDN accessibility
- Verify template paths are correct
- Check browser console for errors

## Testing Each Phase

### Automated Testing
```bash
php tests/phase1_test.php
php tests/phase2_test.php
php tests/phase3_test.php
# ... etc
```

### Manual Testing
See IMPROVEMENT_STRATEGY.md for detailed checklists per phase.

### Regression Testing
After each phase, verify:
- Login still works
- Existing patients load
- Existing adjudications display
- All previous functionality intact

## Rollback Procedures

### Code Rollback
```bash
git log --oneline
git revert <commit-hash>
```

### Database Rollback
```bash
# Find backup
ls -lh backups/

# Restore
mysql -u your_user -p phoenix < backups/phoenix_backup_YYYYMMDD_HHMMSS.sql
```

## Getting Help

1. Check IMPROVEMENT_STRATEGY.md for detailed documentation
2. Review test output for specific errors
3. Check MySQL error logs: `/var/log/mysql/error.log`
4. Check PHP error logs: `/var/log/php/error.log`
5. Review git commit history for changes

## Success Indicators

You're ready to move to the next phase when:
- ✅ All automated tests pass
- ✅ Manual testing checklist complete
- ✅ No error messages in logs
- ✅ Database backup created
- ✅ Changes committed to git
- ✅ Existing functionality still works

## Next Steps

Once all 12 phases are complete:

1. Full regression test suite
2. User acceptance testing
3. Performance testing
4. Security audit
5. Deploy to staging environment
6. Final production deployment

## Estimated Timeline

- **Per phase:** 1-4 days
- **Total (sequential):** ~8-9 weeks
- **With parallel work:** ~6-7 weeks

Good luck! 🚀
