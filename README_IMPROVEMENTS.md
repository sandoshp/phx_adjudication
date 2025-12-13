# PHOENIX Adjudication System - Improvement Project

## 📋 Project Overview

This repository contains a comprehensive 12-phase improvement plan for the PHOENIX Pharmacogenomic Trial Adjudication System. The improvements maintain the PHP/MySQL/Materialize CSS stack while adding critical features for CTCAE v6.0 support, security hardening, and user experience enhancements.

## 🎯 Key Objectives

1. **Add CTCAE v6.0 Support** - Import and integrate CTCAE v6.0 alongside existing v5 data
2. **Migrate to Materialize CSS** - Replace custom CSS with professional framework
3. **Enhance Security** - CSRF protection, input validation, secure configuration
4. **Improve Workflow** - Blind adjudication, enhanced consensus, evidence management
5. **Ensure Compliance** - Comprehensive audit trail and regulatory exports
6. **Optimize Performance** - Database indexing, pagination, caching
7. **Polish UX** - Loading states, validation feedback, keyboard shortcuts

## 📁 Documentation Structure

```
.
├── IMPROVEMENT_STRATEGY.md    # Detailed 12-phase implementation plan
├── QUICKSTART.md              # Getting started guide
├── README_IMPROVEMENTS.md     # This file
├── .env.example               # Environment configuration template
├── composer.json              # PHP dependencies
├── .gitignore                 # Git ignore patterns
│
├── migrations/                # Database migration scripts
│   ├── 001_schema_enhancements.sql
│   └── 001_rollback.sql
│
├── tests/                     # Testing scripts for each phase
│   ├── phase1_test.php
│   ├── phase2_test.php
│   └── ...
│
├── scripts/                   # Utility scripts
│   ├── import_ctcae_v6.php
│   └── run_migrations.php
│
├── inc/
│   ├── templates/            # Reusable page templates
│   │   ├── header.php
│   │   └── footer.php
│   └── classes/              # PSR-4 autoloaded classes
│
└── public/
    └── assets/
        └── css/
            └── theme-dark.css # Materialize dark theme customization
```

## 🚀 Quick Start

### 1. Backup Current System

```bash
# Backup database
mysqldump -u your_user -p phoenix > backups/phoenix_backup_$(date +%Y%m%d).sql

# Backup code
tar -czf backups/code_backup_$(date +%Y%m%d).tar.gz .
```

### 2. Set Up Environment

```bash
# Copy environment template
cp .env.example .env

# Edit with your credentials
nano .env

# Install dependencies
composer install
```

### 3. Run Phase 1

```bash
# Apply database migration
mysql -u your_user -p phoenix < migrations/001_schema_enhancements.sql

# Run tests
php tests/phase1_test.php

# If successful, backup
mysqldump -u your_user -p phoenix > backups/post_phase1_$(date +%Y%m%d).sql
```

### 4. Continue with Remaining Phases

See **QUICKSTART.md** and **IMPROVEMENT_STRATEGY.md** for detailed instructions.

## 📊 Phase Overview

| Phase | Name | Duration | Risk | Status |
|-------|------|----------|------|--------|
| Pre | Environment Setup | 1 day | Low | ⬜ Pending |
| 1 | Database Enhancement | 3 days | Medium | ⬜ Pending |
| 2 | CTCAE v6.0 Import | 2 days | Low | ⬜ Pending |
| 3 | Materialize CSS | 3 days | Low | ⬜ Pending |
| 4 | Security Hardening | 3 days | High | ⬜ Pending |
| 5 | API Standardization | 2 days | Low | ⬜ Pending |
| 6 | Evidence Management | 4 days | Medium | ⬜ Pending |
| 7 | Blind Adjudication | 3 days | Medium | ⬜ Pending |
| 8 | Enhanced Consensus | 3 days | Low | ⬜ Pending |
| 9 | CTCAE Selector | 2 days | Low | ⬜ Pending |
| 10 | Audit & Compliance | 3 days | High | ⬜ Pending |
| 11 | Performance Optimization | 2 days | Medium | ⬜ Pending |
| 12 | UX Polish | 3 days | Low | ⬜ Pending |

**Total Estimated Time:** 34 days (7 weeks) + 20% buffer = **42 days (8-9 weeks)**

## ✅ Success Criteria per Phase

Each phase must meet all criteria before proceeding:

- ✅ All automated tests pass
- ✅ Manual testing checklist complete
- ✅ No regression bugs introduced
- ✅ Database backup created
- ✅ Changes committed to git
- ✅ Documentation updated

## 🔧 Technology Stack

### Current
- **Backend:** PHP 8.0+
- **Database:** MySQL 8.0+
- **Frontend:** Vanilla JavaScript, Custom CSS

### Target
- **Backend:** PHP 8.0+ (maintained)
- **Database:** MySQL 8.0+ (maintained)
- **Frontend:** Vanilla JavaScript, **Materialize CSS 1.0.0**
- **Dependencies:** PHPSpreadsheet, PHPUnit, PHPStan

## 📦 New Features by Phase

### Phase 1-2: Foundation
- ✨ CTCAE v6.0 database support
- ✨ Performance indexes
- ✨ Audit trail infrastructure
- ✨ System configuration management
- ✨ Adjudication versioning

### Phase 3-5: Framework & Security
- ✨ Materialize CSS integration
- ✨ Dark theme customization
- ✨ CSRF protection
- ✨ Input validation
- ✨ Secure configuration
- ✨ Standardized API responses

### Phase 6-9: Core Features
- ✨ Evidence upload (ICD, Labs, Notes)
- ✨ Timeline visualization
- ✨ Blind adjudication workflow
- ✨ Enhanced consensus UI
- ✨ CTCAE version selector

### Phase 10-12: Polish & Compliance
- ✨ Comprehensive audit logging
- ✨ Electronic signatures
- ✨ Regulatory exports (CIOMS, E2B)
- ✨ Pagination & search
- ✨ Loading states
- ✨ Keyboard shortcuts
- ✨ Dashboard analytics

## 🧪 Testing Strategy

### Automated Testing
Each phase includes a test script:
```bash
php tests/phase1_test.php
php tests/phase2_test.php
# ... etc
```

### Manual Testing
Detailed checklists in `IMPROVEMENT_STRATEGY.md`

### Regression Testing
After each phase:
1. Verify login works
2. Check existing patients load
3. Confirm adjudications display
4. Test all previous features

## 🔄 Rollback Procedures

### Code Rollback
```bash
git log --oneline
git revert <commit-hash>
```

### Database Rollback
```bash
# List backups
ls -lh backups/

# Restore
mysql -u your_user -p phoenix < backups/phoenix_backup_YYYYMMDD.sql

# Or use phase-specific rollback
mysql -u your_user -p phoenix < migrations/001_rollback.sql
```

## 📝 CTCAE v6.0 Integration

### File Location
Place the Excel file here:
```
data/CTCAE v6.0 Final Clean-Tracked-Mapping_w_OS_July2025.xlsx
```

### Import Process
```bash
# After Phase 1 migration complete
composer install
php scripts/import_ctcae_v6.php
php tests/phase2_test.php
```

### Data Structure
- CTCAE v5 entries: `ctcae_version = 'v5'`
- CTCAE v6 entries: `ctcae_version = 'v6'`
- Both versions coexist in `dictionary_event` table
- Unique by: (diagnosis, icd10, source, ctcae_version)

## 🛡️ Security Improvements

### Phase 4 Highlights
- Environment-based configuration (no hardcoded credentials)
- CSRF tokens on all forms
- Comprehensive input validation
- Rate limiting on authentication
- SQL injection prevention (prepared statements)
- XSS protection (output escaping)

## 📈 Performance Improvements

### Database Optimizations (Phase 1 & 11)
- Indexes on foreign keys
- Composite indexes for common queries
- Query optimization
- Connection pooling

### Frontend Optimizations (Phase 11-12)
- Asset minification
- Lazy loading
- Pagination
- Client-side caching

## 🎨 Design System

### Materialize CSS Components Used
- Navigation & Sidenav
- Cards
- Forms (inputs, selects, textareas)
- Buttons & FABs
- Modals & Dialogs
- Tables & Collections
- Chips & Badges
- Tooltips
- Tabs & Collapsibles

### Color Palette (Dark Theme)
- Primary Background: `#0f172a`
- Card Background: `#1e293b`
- Accent: `#3b82f6`
- Success: `#10b981`
- Warning: `#f59e0b`
- Danger: `#ef4444`

## 👥 Team Roles

### Required Skills per Phase

**Phase 1-2:** Database Administrator, Backend Developer
**Phase 3:** Frontend Developer, UI/UX Designer
**Phase 4-5:** Security Engineer, Backend Developer
**Phase 6-9:** Full-stack Developer, Clinical Domain Expert
**Phase 10:** Compliance Officer, Backend Developer
**Phase 11-12:** Performance Engineer, Frontend Developer

## 📞 Support & Troubleshooting

### Common Issues

1. **Database migration fails**
   - Check MySQL version (need 8.0+)
   - Review error log: `/var/log/mysql/error.log`
   - Use rollback script

2. **Composer install fails**
   - Check PHP version: `php -v` (need 8.0+)
   - Update Composer: `composer self-update`

3. **CTCAE import fails**
   - Verify file path
   - Check Excel file format
   - Review column headers

4. **Materialize CSS not loading**
   - Check CDN accessibility
   - Verify template paths
   - Check browser console

### Getting Help
- Review `IMPROVEMENT_STRATEGY.md` for detailed documentation
- Check test output for specific errors
- Review git commit history
- Consult MySQL/PHP error logs

## 📅 Recommended Timeline

### Conservative (Sequential)
- Weeks 1-2: Pre-phase + Phases 1-3
- Weeks 3-4: Phases 4-6
- Weeks 5-6: Phases 7-9
- Weeks 7-8: Phases 10-12
- Week 9: Final testing & deployment

### Aggressive (Parallel Work)
- Week 1: Pre-phase + Phase 1
- Weeks 2-3: Phases 2-5 (parallel track)
- Weeks 4-5: Phases 6-9
- Weeks 6-7: Phases 10-12
- Week 8: Final testing & deployment

## 🎉 Expected Outcomes

After completing all phases:

✅ Modern, professional UI with Materialize CSS
✅ CTCAE v6.0 support alongside v5
✅ Production-ready security posture
✅ Blind adjudication workflow
✅ Comprehensive audit trail
✅ Enhanced consensus process
✅ Evidence management system
✅ Regulatory compliance features
✅ Optimized performance
✅ Polished user experience

## 📄 License

Proprietary - PHOENIX Clinical Trial

## 🙏 Acknowledgments

This improvement plan was developed specifically for the PHOENIX Pharmacogenomic Trial Adjudication System, addressing clinical trial requirements while maintaining technical excellence.

---

**Ready to begin?** Start with `QUICKSTART.md` and `IMPROVEMENT_STRATEGY.md`

**Questions?** Review the detailed documentation in each file.

**Need help?** Each phase includes troubleshooting sections.
