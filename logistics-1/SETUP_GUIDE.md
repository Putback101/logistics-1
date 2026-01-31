# Logistics Management System - Setup & Features Guide

## 🚀 Features Implemented

### 1. **Role-Based Access Control (RBAC)**
A comprehensive permission system has been implemented to control what each user role can do across different modules.

#### Defined Roles:
- **admin**: Full access to all modules (view, add, edit, delete, approve)
- **manager**: Can view, add, edit across most modules; can approve; cannot delete
- **procurement**: Can view, add, edit procurement items; view other modules
- **procurement_staff**: Can only view procurement and related modules
- **project**: Can view, add, edit projects; can manage team
- **project_staff**: Can only view projects
- **warehouse**: Can view, add, edit warehouse/inventory items
- **warehouse_staff**: Can only view warehouse items
- **mro**: Can view, add, edit maintenance items
- **mro_staff**: Can only view maintenance items
- **staff**: Limited view-only access

#### Permission Matrix:
```
Permissions per role are defined in: config/permissions.php

Each role has access to:
- Procurement (view, add, edit, delete, approve)
- Projects (view, add, edit, delete, manage_team)
- Assets (view, add, edit, delete)
- MRO (view, add, edit, delete)
- Warehousing (view, add, edit, delete)
- Users (admin/manager only)
- Audit Logs (admin/manager/staff)
```

### 2. **Modal Popup Forms for Adding Items**
Instead of cluttering the page with inline forms, adding items now uses a modal popup system.

#### Implemented Modals:
- **Procurement**: "Add Procurement Request" button opens modal with form
- **Suppliers**: "Add Supplier" button opens modal with form
- **Projects**: "Create Project" button opens modal with form

#### How It Works:
1. User clicks "Add [Item]" button
2. Form is injected into modal and displayed
3. User fills out form and submits
4. Modal closes after submission
5. Page refreshes to show new item

#### Files Modified:
- `views/procurement.php` - Add Procurement and Supplier modals
- `views/projects.php` - Add Project modal
- `assets/js/script.js` - Modal control functions
- `assets/css/design.css` - Modal styling

### 3. **Hamburger Navigation Menu**
The sidebar now has a visible hamburger icon for mobile/tablet navigation.

#### Features:
- ✅ Hamburger icon visible (green accent color, fixed top-left)
- ✅ Sidebar always starts collapsed by default
- ✅ Hamburger click toggles sidebar open/close
- ✅ No hover expansion (hamburger only)
- ✅ Auto-closes on smaller screens after navigation

#### Files:
- `views/layout/sidebar.php` - Hamburger button
- `assets/css/design.css` - Hamburger styling
- `assets/js/script.js` - Hamburger click handler

### 4. **User Profile Settings**
The profile dropdown in the top bar is now functional.

#### Features:
- ✅ Profile dropdown toggles on click
- ✅ Settings link in dropdown (links to user_edit.php)
- ✅ Proper user information display
- ✅ Logout functionality

#### Files:
- `views/layout/topbar.php` - Profile dropdown HTML
- `assets/js/script.js` - Profile dropdown toggle

### 5. **Search Functionality**
The search bar in the top navigation is now functional.

#### Features:
- ✅ Enter key triggers search
- ✅ Redirects to dashboard with search parameter
- ✅ Search query encoding for special characters

#### Files:
- `assets/js/script.js` - Search event handler

---

## 📋 How to Use

### For Administrators:
1. Login with admin credentials
2. Navigate to any module (Procurement, Projects, Assets, etc.)
3. Click "Add [Item]" button to open modal
4. Fill in the form and submit
5. You can add, edit, and delete items across all modules

### For Managers:
1. Login with manager credentials
2. Access most modules with ability to add and edit
3. Cannot delete items (admin only)
4. Can approve procurement requests

### For Staff Members:
1. Login with staff credentials (procurement_staff, project_staff, etc.)
2. Can only view items in your assigned module
3. Cannot add, edit, or delete items
4. Limited to read-only access

### Adding Items (All Users with Add Permission):
1. Navigate to the module (Procurement, Projects, etc.)
2. Click the "Add [Item]" button
3. Modal popup appears with form
4. Fill in required fields
5. Click "Add [Item]" to submit
6. Modal closes, list refreshes

---

## 🔐 Permission System Details

### How to Check Permissions:
All permission checks are centralized in `config/permissions.php`

```php
// Get user role
$userRole = $_SESSION['user']['role'] ?? 'staff';

// Check if user can perform action
$canAdd = hasPermission($userRole, 'procurement', 'add');
$canEdit = hasPermission($userRole, 'procurement', 'edit');
$canDelete = hasPermission($userRole, 'procurement', 'delete');

// Use in view to show/hide buttons
<?php if ($canAdd): ?>
    <button onclick="openAddProcurementModal()">Add Item</button>
<?php endif; ?>
```

### Adding New Permissions:
Edit `config/permissions.php` to add new modules or modify existing permissions:

```php
'newrole' => [
    'newmodule' => ['view', 'add', 'edit', 'delete'],
    'procurement' => ['view', 'add'],
]
```

---

## 🛠️ Technical Implementation

### Files Modified/Created:

#### New Files:
1. **config/permissions.php** (125 lines)
   - Role-permission matrix
   - `hasPermission()` function
   - `getModulePermissions()` function
   - `requirePermission()` function

#### Updated Files:
1. **views/procurement.php**
   - Added permission checks
   - Converted add form to modal
   - Added supplier modal
   - Permission-based button visibility

2. **views/projects.php**
   - Added permission checks
   - Converted create form to modal
   - Permission-based button visibility

3. **assets/js/script.js**
   - `openModal(title, formHTML)` function
   - `closeModal()` function
   - `openAddProcurementModal()` function
   - `openAddSupplierModal()` function
   - `openAddProjectModal()` function
   - Modal click-outside listener
   - Removed hover expansion on sidebar

4. **assets/css/design.css**
   - Modal CSS (.modal, .modal.show, .modal-content, etc.)
   - Hamburger visibility (display: block)

5. **views/layout/topbar.php**
   - Generic modal HTML template
   - Settings link functional
   - Profile dropdown working

6. **models/Dashboard.php**
   - Updated getRecentActivities() with fallback query
   - COALESCE timestamp handling

---

## 🧪 Testing the System

### Test Scenarios:

1. **Test Admin Permissions:**
   - Login as admin
   - Go to Procurement module
   - Should see "Add Procurement Request" button
   - Click button → modal appears
   - Fill form and submit
   - Item should be added to table
   - Should see Edit and Delete buttons for all items

2. **Test Manager Permissions:**
   - Login as manager
   - Go to Procurement module
   - Should see "Add Procurement Request" button
   - Can add items
   - Should see Edit buttons
   - Should NOT see Delete button

3. **Test Staff Permissions:**
   - Login as procurement_staff
   - Go to Procurement module
   - Should NOT see "Add Procurement Request" button
   - Should only see View/Open buttons
   - No Edit or Delete buttons

4. **Test Modal Popup:**
   - Click any "Add [Item]" button
   - Modal should appear with form
   - Try closing by clicking outside modal or Cancel button
   - Modal should close without submitting

---

## 🔄 Extending to Other Modules

To apply this same pattern to other modules (Assets, Maintenance, Fleet, etc.):

1. **Add permissions to config/permissions.php** (if not already there)

2. **Update the module view file** (e.g., `views/assets.php`):
```php
// Add at top
require_once __DIR__ . "/../config/permissions.php";

// Get permissions
$userRole = $_SESSION['user']['role'] ?? 'staff';
$canAdd = hasPermission($userRole, 'assets', 'add');
$canEdit = hasPermission($userRole, 'assets', 'edit');
$canDelete = hasPermission($userRole, 'assets', 'delete');

// In view, replace form with button
<?php if ($canAdd): ?>
<button class="btn btn-primary" onclick="openAddAssetModal()">
    <i class="bi bi-plus-circle"></i> Add Asset
</button>
<?php endif; ?>

// Add hidden form at bottom
<?php if ($canAdd): ?>
<div id="addAssetForm" style="display: none;">
    <!-- Form HTML here -->
</div>
<?php endif; ?>
```

3. **Add modal function to assets/js/script.js**:
```javascript
function openAddAssetModal() {
    const formHTML = document.getElementById('addAssetForm')?.innerHTML || '';
    if (formHTML) {
        openModal('Add Asset', formHTML);
    }
}
```

4. **Add permission checks to action buttons**:
```php
<?php if ($canEdit): ?>
    <a href="asset_edit.php?id=<?= $asset['id'] ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-pencil"></i>
    </a>
<?php endif; ?>
```

---

## 📝 User Roles in Database

Make sure your users table has a `role` column with one of these values:
- admin
- manager
- procurement
- procurement_staff
- project
- project_staff
- warehouse
- warehouse_staff
- mro
- mro_staff
- staff

### Update User Role:
```sql
UPDATE users SET role = 'procurement_staff' WHERE id = 5;
```

---

## 🎨 UI Components

### Modal Structure:
```html
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5 id="modalTitle">Modal Title</h5>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Form content injected here -->
        </div>
        <div class="modal-footer">
            <!-- Buttons included in injected form -->
        </div>
    </div>
</div>
```

### Button Styles:
- Primary action: `<button class="btn btn-primary">Action</button>`
- Secondary action: `<button class="btn btn-secondary">Cancel</button>`
- Danger action: `<button class="btn btn-outline-danger">Delete</button>`

---

## 🐛 Troubleshooting

### Modal not appearing:
- Check that the form div exists with correct ID
- Verify JavaScript files are loaded (check console for errors)
- Ensure CSS is not hiding the modal

### Buttons not showing/hiding:
- Check that `hasPermission()` is returning correct values
- Verify user role is set correctly in session
- Check `config/permissions.php` for correct permission definitions

### Hamburger menu not working:
- Check that hamburger has `display: block` in CSS
- Verify `#hamburger` element exists in sidebar.php
- Check JavaScript console for errors

---

## 📚 API Reference

### Permission Functions:

```php
// Check if user has permission for action
hasPermission($role, $module, $action) : bool

// Get all permissions for a module
getModulePermissions($role, $module) : array

// Require permission or show error
requirePermission($role, $module, $action) : void
```

### Modal Functions (JavaScript):

```javascript
// Open modal with form
openModal(title, formHTML) : void

// Close modal
closeModal() : void

// Open specific module modals
openAddProcurementModal() : void
openAddSupplierModal() : void
openAddProjectModal() : void
```

---

## 💡 Best Practices

1. **Always check permissions before showing buttons**
   ```php
   <?php if ($canDelete): ?>
       <!-- Show delete button -->
   <?php endif; ?>
   ```

2. **Use consistent naming for modal functions**
   - `openAdd[Module]Modal()`
   - `openEdit[Module]Modal()`

3. **Include hidden form divs for all modal forms**
   ```html
   <div id="add[Module]Form" style="display: none;">
       <!-- Form HTML -->
   </div>
   ```

4. **Always include Cancel button in forms**
   ```html
   <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
   ```

5. **Test each role's permissions**
   - Don't assume permissions work
   - Test as different user roles
   - Verify buttons show/hide correctly

---

## 📞 Support

For issues or questions about the role-based permission system:

1. Check `config/permissions.php` for permission definitions
2. Verify user role is set in database
3. Check browser console for JavaScript errors
4. Review the implementation in the specific module view file

---

**Last Updated**: 2026-01-31
**System Version**: 1.0 (RBAC + Modal Implementation)
