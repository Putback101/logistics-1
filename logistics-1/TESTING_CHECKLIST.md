# Quick Testing Checklist

## ✅ Feature Verification

### 1. Hamburger Menu
- [ ] Hamburger icon visible in top-left corner (green background)
- [ ] Click hamburger → sidebar opens
- [ ] Click hamburger again → sidebar closes
- [ ] Click outside sidebar → sidebar closes
- [ ] Menu items are clickable

### 2. Modal Forms
- [ ] Procurement module has "Add Procurement Request" button
- [ ] Click button → modal appears with form
- [ ] Modal has title, form fields, and buttons
- [ ] Can close modal by clicking X button
- [ ] Can close modal by clicking outside
- [ ] Can cancel by clicking Cancel button
- [ ] Form can be submitted

### 3. Suppliers Tab
- [ ] Suppliers tab visible in Procurement module
- [ ] "Add Supplier" button visible (if user has permission)
- [ ] Click button → modal appears with supplier form
- [ ] Modal title is "Add Supplier"
- [ ] Can submit supplier form

### 4. Projects Module
- [ ] "Create Project" button visible (if user has permission)
- [ ] Click button → modal appears with project form
- [ ] Modal title is "Create Project"
- [ ] Can submit project form

### 5. Profile Dropdown
- [ ] Click profile icon/name in top-right
- [ ] Dropdown menu appears
- [ ] "Settings" link visible
- [ ] Click Settings → redirects to user_edit.php
- [ ] "Logout" link visible

### 6. Search Bar
- [ ] Search input visible in topbar
- [ ] Type search query
- [ ] Press Enter
- [ ] Redirects to dashboard with search parameter

### 7. Role-Based Permissions

#### Admin Role:
- [ ] Can see "Add" buttons in all modules
- [ ] Can see Edit buttons for all items
- [ ] Can see Delete buttons for all items
- [ ] Can approve items (if available)

#### Manager Role:
- [ ] Can see "Add" buttons in most modules
- [ ] Can see Edit buttons for items
- [ ] Cannot see Delete buttons
- [ ] Can approve items

#### Staff Role (procurement_staff):
- [ ] Cannot see "Add Procurement Request" button
- [ ] Can only see View/Open buttons
- [ ] Cannot see Edit buttons
- [ ] Cannot see Delete buttons

### 8. Modal Form Submission
- [ ] Fill out form completely
- [ ] Click submit button
- [ ] Modal closes
- [ ] Item appears in list
- [ ] No error messages

---

## 🔍 How to Test Different Roles

### Test as Admin:
1. Login with admin user
2. Go to Procurement module
3. Verify all buttons visible (Add, Edit, Delete)
4. Try adding an item via modal
5. Verify item appears in table
6. Try editing an item
7. Try deleting an item

### Test as Manager:
1. Login with manager user
2. Go to Procurement module
3. Verify "Add" and "Edit" buttons visible
4. Verify NO "Delete" button
5. Try adding an item
6. Try editing an item
7. Verify cannot delete

### Test as Procurement Staff:
1. Login with procurement_staff user
2. Go to Procurement module
3. Verify NO "Add" button
4. Verify NO "Edit" buttons
5. Verify NO "Delete" buttons
6. Can only view items

---

## 🎯 Expected Results by User Role

### ADMIN User:
```
Procurement Module:
✓ Button: "Add Procurement Request" → Modal appears
✓ Table: Edit button visible → Click to edit
✓ Table: Delete button visible → Click to delete
✓ Button: "Add Supplier" → Modal appears
✓ Can create, edit, delete items
```

### MANAGER User:
```
Procurement Module:
✓ Button: "Add Procurement Request" → Modal appears
✓ Table: Edit button visible → Click to edit
✗ Table: Delete button NOT visible
✓ Button: "Add Supplier" → Modal appears
✓ Can create and edit items, cannot delete
```

### PROCUREMENT_STAFF User:
```
Procurement Module:
✗ Button: "Add Procurement Request" NOT visible
✗ Table: Edit button NOT visible
✗ Table: Delete button NOT visible
✓ Can only view/read items
✓ No action buttons except View
```

---

## 🐛 Common Issues & Fixes

### Issue: Modal not appearing
**Solution:**
1. Check browser console for JavaScript errors (F12)
2. Verify form div exists with correct ID
3. Check that script.js is loaded
4. Verify CSS modal styles are present

### Issue: Buttons not showing/hiding
**Solution:**
1. Verify user role is correct in database
2. Check config/permissions.php for definitions
3. Clear browser cache and reload
4. Check if hasPermission() function is working

### Issue: Hamburger menu not visible
**Solution:**
1. Check CSS has `.hamburger { display: block; }`
2. Verify hamburger button exists in HTML
3. Check z-index is high enough (should be 1001)
4. Clear CSS cache

### Issue: Form submitting to wrong page
**Solution:**
1. Check form action attribute
2. Verify controller file path
3. Check controller handles POST request
4. Review error logs in browser console

---

## 📊 Test Data Requirements

### Required User Accounts:
1. **Admin User**
   - role: 'admin'
   - Can test full functionality

2. **Manager User**
   - role: 'manager'
   - Can test limited CRUD

3. **Procurement Staff**
   - role: 'procurement_staff'
   - Can test view-only access

### Required Sample Data:
- At least 1-2 procurement items
- At least 1-2 suppliers
- At least 1-2 projects
- Different status values (Pending, Approved, etc.)

---

## 📝 Sign-Off Checklist

- [ ] All modals working correctly
- [ ] All role permissions enforced
- [ ] Hamburger menu functional
- [ ] Profile dropdown working
- [ ] Search bar functional
- [ ] No console errors
- [ ] No PHP errors in logs
- [ ] Forms submit successfully
- [ ] Items appear in tables after submission
- [ ] Different user roles see different options

---

**Testing Date**: ________________
**Tested By**: ____________________
**Status**: ☐ PASS  ☐ FAIL
**Notes**: 
