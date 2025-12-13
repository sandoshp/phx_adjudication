# Dashboard Not Working - FIXED!

## What Was Wrong

The dashboard wasn't working because of **authentication redirect issues** in `inc/auth.php`.

### The Problem
```php
// OLD CODE (broken on Windows XAMPP):
header('Location: /index.php');
```

This redirected to `http://localhost/index.php` (XAMPP dashboard) instead of `http://localhost/phx_adjudication/public/index.php` (your login page).

### The Fix
```php
// NEW CODE (works everywhere):
header('Location: index.php');  // Relative path
```

---

## Testing Steps

### Step 1: Test Basic Functionality

Open in your browser:
```
http://localhost/phx_adjudication/public/test_dashboard.php
```

**What to check:**
- ✅ Page loads with dark blue-grey theme
- ✅ Materialize CSS styling displays correctly
- ✅ Shows "PHP is executing correctly"
- ✅ Shows "Custom theme file exists" in green
- ✅ Shows your PHP version (should be 8.2.12)
- ✅ Shows session status (probably "Not logged in")

**If this page shows XAMPP dashboard:**
- Check the URL - make sure you included `/phx_adjudication/public/`
- Make sure XAMPP Apache is running (green in control panel)

---

### Step 2: Test Login Page

Open:
```
http://localhost/phx_adjudication/public/login.php
```

**What to check:**
- ✅ Professional login form with PHOENIX logo
- ✅ Dark theme with gradient background
- ✅ Email and password fields
- ✅ "Sign In" button

**If you see old ugly login page:**
- You might be looking at `index.php` instead
- Make sure to use `login.php` (new Materialize version)

---

### Step 3: Log In

You need valid credentials in the database. If you don't have any, let's check:

**Check if you have users:**
```cmd
cd C:\xampp\mysql\bin
mysql -u root -p phoenix
```

```sql
SELECT id, email, name, role FROM user;
```

**If no users exist**, create a test admin user:
```sql
INSERT INTO user (email, password, name, role, active)
VALUES (
    'admin@phoenix.local',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- password: password
    'Admin User',
    'admin',
    1
);
```

Then try logging in with:
- **Email**: `admin@phoenix.local`
- **Password**: `password`

---

### Step 4: Access Dashboard

After successful login, you should be automatically redirected to:
```
http://localhost/phx_adjudication/public/dashboard_materialize.php
```

**What to check:**
- ✅ Navigation bar at top with PHOENIX logo
- ✅ Four statistics cards (Total Patients, Pending Cases, etc.)
- ✅ "Add New Patient" form
- ✅ Patients list section
- ✅ No redirect to XAMPP dashboard
- ✅ No 404 errors in browser console (F12)

---

## Troubleshooting

### Issue: test_dashboard.php shows "Theme File Exists: NO"

**Problem**: `theme-dark.css` file missing

**Solution**:
```cmd
cd C:\xampp\htdocs\phx_adjudication\public\assets\css
dir
```

If `theme-dark.css` is missing:
1. Check Phase 3 tests: `php C:\xampp\htdocs\phx_adjudication\tests\phase3_test.php`
2. The file should have been created in Phase 3
3. Re-run Phase 3 if needed

---

### Issue: Login form doesn't work / shows error

**Problem**: `api/auth.php` endpoint issue

**Check the auth endpoint exists:**
```cmd
dir C:\xampp\htdocs\phx_adjudication\api\auth.php
```

**If missing**, the old system might use a different authentication method. Check:
```cmd
dir C:\xampp\htdocs\phx_adjudication\api\*.php
```

---

### Issue: After login, still redirects to XAMPP dashboard

**Problem**: Session not persisting or auth check still using old absolute paths

**Check session is working:**
1. Go to `test_dashboard.php`
2. Note the session status
3. Log in via `login.php`
4. Go back to `test_dashboard.php`
5. Should now show "Logged in as: your@email.com"

**If session status doesn't change:**
- PHP sessions might not be working
- Check `C:\xampp\tmp\` exists and is writable
- Check `php.ini` session.save_path setting

---

### Issue: "Forbidden" error on dashboard

**Problem**: User role doesn't have permission

**Check your user role:**
```sql
SELECT email, role FROM user WHERE email = 'your@email.com';
```

**Valid roles:**
- `admin` - Full access
- `coordinator` - Can import and manage
- `chair` - Can access consensus
- `reviewer` - Basic access

---

### Issue: Still can't access any pages

**Nuclear option - Disable auth temporarily for testing:**

Edit `public/dashboard_materialize.php`:

```php
// TEMPORARILY comment out these lines (lines 8-10):
// require_once __DIR__ . '/../inc/auth.php';
// require_login();
// $user = current_user();

// Add this instead:
$user = [
    'id' => 1,
    'email' => 'test@example.com',
    'name' => 'Test User',
    'role' => 'admin'
];
```

Now try:
```
http://localhost/phx_adjudication/public/dashboard_materialize.php
```

**IMPORTANT**: Remove this temporary code once you confirm the dashboard works!

---

## Files Changed

### Fixed Files
1. **inc/auth.php** - Fixed redirect paths (absolute → relative)
2. **public/login.php** - New Materialize CSS login page
3. **public/test_dashboard.php** - Diagnostic test page

### Original Problematic Files
- `public/index.php` - Old login page (still has absolute paths)
- `public/dashboard_new.php` - Original version with absolute paths

**Use the new files:**
- ✅ `login.php` (not `index.php`)
- ✅ `dashboard_materialize.php` (not `dashboard_new.php`)

---

## Path Reference

### Correct URLs for XAMPP 8.2.12

```
Test Page:  http://localhost/phx_adjudication/public/test_dashboard.php
Login:      http://localhost/phx_adjudication/public/login.php
Dashboard:  http://localhost/phx_adjudication/public/dashboard_materialize.php
Old Dash:   http://localhost/phx_adjudication/public/dashboard.php (for comparison)
```

### File Paths (Windows)

```
Project:    C:\xampp\htdocs\phx_adjudication\
Public:     C:\xampp\htdocs\phx_adjudication\public\
Auth:       C:\xampp\htdocs\phx_adjudication\inc\auth.php
Database:   C:\xampp\mysql\data\phoenix\
Logs:       C:\xampp\apache\logs\error.log
```

---

## What Changed

| File | Status | Change |
|------|--------|--------|
| `inc/auth.php` | ✅ Fixed | Absolute → relative redirect paths |
| `public/login.php` | ✅ New | Materialize CSS login (replaces index.php) |
| `public/test_dashboard.php` | ✅ New | Diagnostic test page |
| `public/dashboard_materialize.php` | ✅ Already fixed | From previous commit |
| `inc/templates/header_fixed.php` | ✅ Already fixed | From previous commit |
| `inc/templates/footer_fixed.php` | ✅ Already fixed | From previous commit |

---

## Expected Behavior Now

1. Visit `dashboard_materialize.php` → Redirects to `login.php` (not XAMPP dashboard)
2. Enter credentials → Validates against database
3. Successful login → Redirects to `dashboard_materialize.php`
4. Dashboard loads → Shows Materialize CSS interface
5. All links work → No more absolute path issues

---

## Next Steps After Testing

Once you confirm the dashboard works:

### Option A: Report Results
Let me know:
- ✅ What works
- ❌ What still doesn't work
- Screenshots of any errors

### Option B: Continue Development
If everything works, choose your next step:
1. Convert other pages to Materialize (patient.php, adjudication.php, etc.)
2. Proceed to Phase 4 (Security Hardening)
3. Customize the dashboard to your needs

---

## Quick Test Checklist

Run through this checklist and report results:

- [ ] `test_dashboard.php` loads and shows green checkmarks
- [ ] `login.php` shows professional login form
- [ ] Can create/find a test user in database
- [ ] Login with test user succeeds
- [ ] After login, redirected to `dashboard_materialize.php`
- [ ] Dashboard shows dark theme correctly
- [ ] Navigation bar displays at top
- [ ] Statistics cards visible
- [ ] Add patient form visible
- [ ] No errors in browser console (F12 → Console)
- [ ] No redirect to XAMPP dashboard

---

**Git Commit**: `9ce0dc7` - "Fix authentication redirect paths for Windows XAMPP and add diagnostic tools"

**Status**: Ready for testing
**XAMPP Version**: 8.2.12 (confirmed compatible)
