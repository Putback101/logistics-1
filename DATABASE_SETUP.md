# Database Configuration Checklist

## ✅ Pre-Deployment Verification

### 1. Users Table Structure
Verify that your `users` table has a `role` column:

```sql
-- Check table structure
DESCRIBE users;

-- Should have columns like:
-- id, email, password, fullname, role, created_at, etc.
```

**Required Columns**:
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `email` (VARCHAR, UNIQUE)
- `password` (VARCHAR)
- `fullname` (VARCHAR)
- `role` (VARCHAR, DEFAULT 'staff')  ← **IMPORTANT**
- `created_at` (TIMESTAMP)

### 2. Add Role Column (if missing)
If your `users` table doesn't have a `role` column:

```sql
ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'staff' AFTER fullname;
```

---

## 🔐 Set User Roles

### Update Single User:
```sql
-- Make a user an admin
UPDATE users SET role = 'admin' WHERE email = 'admin@example.com';

-- Make a user a manager
UPDATE users SET role = 'manager' WHERE email = 'manager@example.com';

-- Make a user procurement staff
UPDATE users SET role = 'procurement_staff' WHERE id = 5;

-- Make a user project staff
UPDATE users SET role = 'project_staff' WHERE id = 6;
```

### Bulk Update Users:
```sql
-- Set all users to staff (default)
UPDATE users SET role = 'staff';

-- Set specific email domain as managers
UPDATE users SET role = 'manager' WHERE email LIKE '%manager%';
```

### Verify Roles Set:
```sql
-- View all users and their roles
SELECT id, email, fullname, role FROM users;

-- Count by role
SELECT role, COUNT(*) FROM users GROUP BY role;
```

---

## 📋 Valid Role Values

The system recognizes these roles:

1. **admin** - Full access to everything
2. **manager** - Can create/edit, cannot delete
3. **procurement** - Procurement module access
4. **procurement_staff** - Procurement view-only
5. **project** - Projects module access
6. **project_staff** - Projects view-only
7. **warehouse** - Warehouse module access
8. **warehouse_staff** - Warehouse view-only
9. **mro** - Maintenance module access
10. **mro_staff** - Maintenance view-only
11. **staff** - General staff (limited access)

---

## 🗄️ Sample Users Setup

```sql
-- Clear existing roles (optional)
UPDATE users SET role = 'staff';

-- Set up test users
UPDATE users SET role = 'admin' WHERE fullname LIKE '%Admin%' OR email LIKE '%admin%';
UPDATE users SET role = 'manager' WHERE fullname LIKE '%Manager%' OR email LIKE '%manager%';
UPDATE users SET role = 'procurement' WHERE fullname LIKE '%Procurement%' OR email LIKE '%procurement%';
UPDATE users SET role = 'procurement_staff' WHERE id IN (4, 5);
UPDATE users SET role = 'project' WHERE fullname LIKE '%Project%' OR email LIKE '%project%';
UPDATE users SET role = 'project_staff' WHERE id IN (7, 8);

-- Verify setup
SELECT id, email, fullname, role FROM users ORDER BY role;
```

---

## 🔍 Verification Steps

After setting up roles, verify they work:

### 1. Check Database:
```sql
SELECT DISTINCT role FROM users;
-- Should show your roles (admin, manager, procurement_staff, etc.)
```

### 2. Test Login:
1. Open application in browser
2. Login as admin user
3. Verify buttons appear in Procurement/Projects modules
4. Login as staff user
5. Verify buttons don't appear (view-only)

### 3. Check Browser Session:
1. Open browser DevTools (F12)
2. Go to Console tab
3. Logout and login
4. Check session is created correctly

### 4. Verify in Code:
In any PHP file:
```php
echo $_SESSION['user']['role']; // Should output user's role
```

---

## ⚠️ Common Issues & Fixes

### Issue: "Undefined index: role" error
**Cause**: Role column missing from users table
**Fix**: 
```sql
ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'staff';
```

### Issue: All users see same buttons regardless of role
**Cause**: Role not being set correctly in database
**Fix**:
```sql
-- Check actual roles in database
SELECT id, email, role FROM users;

-- Update if needed
UPDATE users SET role = 'procurement_staff' WHERE id = 3;
```

### Issue: User doesn't have permission button
**Cause**: Role not recognized by permissions.php
**Fix**:
```php
// In config/permissions.php, add role:
'newrole' => [
    'procurement' => ['view', 'add'],
    'projects' => ['view'],
]
```

### Issue: Session role shows as NULL
**Cause**: Role column exists but user record is NULL
**Fix**:
```sql
-- Set default role for all NULL values
UPDATE users SET role = 'staff' WHERE role IS NULL;
```

---

## 📊 Test Data Generator

### Create test user accounts:
```sql
-- Test Admin
INSERT INTO users (email, password, fullname, role) 
VALUES ('admin@test.com', MD5('password'), 'Test Admin', 'admin');

-- Test Manager
INSERT INTO users (email, password, fullname, role) 
VALUES ('manager@test.com', MD5('password'), 'Test Manager', 'manager');

-- Test Staff
INSERT INTO users (email, password, fullname, role) 
VALUES ('staff@test.com', MD5('password'), 'Test Staff', 'procurement_staff');
```

---

## 🎯 Pre-Launch Checklist

- [ ] Users table has `role` column
- [ ] All users have valid role values
- [ ] At least one admin user exists
- [ ] At least one test user per role
- [ ] config/permissions.php file exists
- [ ] Permissions match your role structure
- [ ] Test login as different users
- [ ] Verify buttons show/hide correctly
- [ ] Modal forms appear correctly
- [ ] Forms submit without errors

---

## 📝 Troubleshooting Queries

### Find users without roles:
```sql
SELECT id, email, fullname FROM users WHERE role IS NULL;
```

### Find all admins:
```sql
SELECT email, fullname FROM users WHERE role = 'admin';
```

### Count permissions by role:
```sql
-- This is done in PHP, but you can verify in database:
SELECT role, COUNT(*) as user_count FROM users GROUP BY role;
```

### Reset all roles to default:
```sql
UPDATE users SET role = 'staff' WHERE role IS NULL OR role = '';
```

---

## 🔐 Security Notes

1. **Verify User ID vs Email**:
   - Session should use user ID for security
   - Role retrieved by user ID, not email

2. **Session Timeout**:
   - Implement session timeout
   - Users should logout/login periodically

3. **Role Change**:
   - When changing user role, session must update
   - User should re-login to see new permissions

4. **Database Backup**:
   - Always backup before making bulk changes
   - Test queries on staging environment first

---

## 📞 Database Support

For issues related to:
- **Role setup**: Check users table and role values
- **Permissions not working**: Verify config/permissions.php
- **Session errors**: Check PHP session configuration
- **Login issues**: Verify password hashing method

---

**Database Version**: Compatible with MySQL 5.7+
**Charset**: UTF-8 MB4 (recommended)
**Collation**: utf8mb4_general_ci

