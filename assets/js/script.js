// Set up the global CSS variables in JS for dynamic use.
document.documentElement.style.setProperty('--color-neon-accent', '#00e676');

const sidebar = document.getElementById('sidebar');
const hamburger = document.getElementById('hamburger');
const hamburgerInner = document.getElementById('hamburgerInner');
const sidebarBackdrop = document.getElementById('sidebarBackdrop');
const navLinks = document.querySelectorAll('.nav-link');
const profileMenu = document.getElementById('profileMenu');
const notificationBell = document.getElementById('notificationBell');
const notificationList = document.getElementById('notificationList');
const notificationCount = document.getElementById('notificationCount');
const markAllRead = document.getElementById('markAllRead');

const SIDEBAR_PREF_KEY = 'byahero_sidebar_expanded';
const SIDEBAR_KEEP_NEXT_KEY = 'byahero_sidebar_keep_next';
const BREAKPOINT_TABLET = 1024;
const BREAKPOINT_MOBILE = 768;

const sidebarState = {
    pinnedExpanded: false,
    keepOpenUntilLeave: false,
};

const navigationState = {
    fromSidebarLink: false,
};

function getAppBaseUrl() {
    const path = window.location.pathname.replace(/\\/g, '/');
    const segments = path.split('/').filter(Boolean);
    const cutIndex = segments.findIndex((seg) => ['views', 'auth', 'controllers', 'public'].includes(seg));
    if (cutIndex >= 0) {
        return '/' + segments.slice(0, cutIndex).join('/');
    }
    return segments.length > 0 ? '/' + segments.join('/') : '';
}

const appBase = getAppBaseUrl();

function loadSidebarState() {
    try {
        sidebarState.pinnedExpanded = localStorage.getItem(SIDEBAR_PREF_KEY) === '1';
        sidebarState.keepOpenUntilLeave = sessionStorage.getItem(SIDEBAR_KEEP_NEXT_KEY) === '1';
    } catch (e) {
        sidebarState.pinnedExpanded = false;
        sidebarState.keepOpenUntilLeave = false;
    }
}

function saveSidebarPreference(expanded) {
    sidebarState.pinnedExpanded = !!expanded;
    try {
        localStorage.setItem(SIDEBAR_PREF_KEY, expanded ? '1' : '0');
    } catch (e) {
        // ignore storage errors
    }
}

function setKeepOpenNext(enabled) {
    sidebarState.keepOpenUntilLeave = !!enabled;
    try {
        if (enabled) {
            sessionStorage.setItem(SIDEBAR_KEEP_NEXT_KEY, '1');
        } else {
            sessionStorage.removeItem(SIDEBAR_KEEP_NEXT_KEY);
        }
    } catch (e) {
        // ignore storage errors
    }
}

function removeBackdrop() {
    if (!sidebarBackdrop) return;
    sidebarBackdrop.classList.remove('visible');
    document.body.style.overflow = '';
}

function showBackdrop() {
    if (window.innerWidth > BREAKPOINT_MOBILE || !sidebarBackdrop) return;
    sidebarBackdrop.classList.add('visible');
    document.body.style.overflow = 'hidden';
}

function closeMobileSidebar() {
    if (!sidebar) return;
    sidebar.classList.remove('open');
    removeBackdrop();
    if (hamburger) hamburger.classList.remove('hidden');
}

function markSidebarNavigation() {
    navigationState.fromSidebarLink = true;

    if (window.innerWidth > BREAKPOINT_TABLET && sidebar) {
        const isExpanded = !sidebar.classList.contains('collapsed');
        setKeepOpenNext(isExpanded);
    }

    window.setTimeout(() => {
        navigationState.fromSidebarLink = false;
    }, 1200);
}

function applySidebarStateForViewport() {
    if (!sidebar) return;

    const width = window.innerWidth;
    const isDesktop = width > BREAKPOINT_TABLET;
    const isTablet = width <= BREAKPOINT_TABLET && width > BREAKPOINT_MOBILE;
    const isMobile = width <= BREAKPOINT_MOBILE;

    if (hamburger) {
        hamburger.classList.toggle('hidden', isDesktop);
    }

    if (isDesktop) {
        // Desktop: collapsed by default, expands on hover.
        // If sidebar link was clicked, stay expanded until mouse leaves.
        sidebar.classList.remove('open');
        sidebar.classList.toggle('collapsed', !sidebarState.keepOpenUntilLeave);
        removeBackdrop();
        return;
    }

    if (isTablet) {
        // Tablet: visible rail, burger controls expanded state.
        sidebar.classList.toggle('open', sidebarState.pinnedExpanded);
        sidebar.classList.toggle('collapsed', !sidebarState.pinnedExpanded);
        removeBackdrop();
        return;
    }

    // Mobile: off-canvas, closed by default.
    sidebar.classList.remove('collapsed');
    sidebar.classList.remove('open');
    removeBackdrop();
    if (hamburger) hamburger.classList.remove('hidden');
}

function toggleResponsiveSidebar() {
    if (!sidebar) return;

    const width = window.innerWidth;
    const isTablet = width <= BREAKPOINT_TABLET && width > BREAKPOINT_MOBILE;
    const isMobile = width <= BREAKPOINT_MOBILE;

    if (isTablet) {
        const isOpen = sidebar.classList.toggle('open');
        sidebar.classList.toggle('collapsed', !isOpen);
        saveSidebarPreference(isOpen);
        return;
    }

    if (isMobile) {
        const isOpen = sidebar.classList.toggle('open');
        if (isOpen) {
            showBackdrop();
            if (hamburger) hamburger.classList.add('hidden');
        } else {
            removeBackdrop();
            if (hamburger) hamburger.classList.remove('hidden');
        }
    }
}

if (sidebar) {
    loadSidebarState();
    applySidebarStateForViewport();
    window.addEventListener('resize', applySidebarStateForViewport);

    sidebar.addEventListener('mouseenter', () => {
        if (window.innerWidth > BREAKPOINT_TABLET) {
            sidebar.classList.remove('collapsed');
        }
    });

    sidebar.addEventListener('mouseleave', () => {
        if (window.innerWidth > BREAKPOINT_TABLET && !navigationState.fromSidebarLink) {
            sidebar.classList.add('collapsed');
            setKeepOpenNext(false);
        }
    });
}

if (hamburger) {
    hamburger.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleResponsiveSidebar();
    });
}

if (hamburgerInner) {
    hamburgerInner.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleResponsiveSidebar();
    });
}

if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener('click', closeMobileSidebar);
}

if (navLinks.length > 0) {
    navLinks.forEach((link) => {
        link.addEventListener('pointerdown', () => {
            if (window.innerWidth > BREAKPOINT_TABLET) {
                markSidebarNavigation();
            }
        });

        link.addEventListener('click', () => {
            const width = window.innerWidth;
            const isDesktop = width > BREAKPOINT_TABLET;
            const isTablet = width <= BREAKPOINT_TABLET && width > BREAKPOINT_MOBILE;
            const isMobile = width <= BREAKPOINT_MOBILE;

            if (isDesktop) {
                markSidebarNavigation();
            }

            if (isTablet && sidebar) {
                const isExpanded = sidebar.classList.contains('open') || !sidebar.classList.contains('collapsed');
                saveSidebarPreference(isExpanded);
            }

            if (isMobile) {
                closeMobileSidebar();
            }
        });
    });
}
// Handle profile dropdown toggle (for better touch support)
if (profileMenu) {
    profileMenu.addEventListener('click', (e) => {
        e.stopPropagation(); // Prevent the click from immediately bubbling up and closing the menu
        profileMenu.classList.toggle('open');
    });
}

if (notificationBell) {
    notificationBell.addEventListener('click', (e) => {
        e.stopPropagation();
        notificationBell.classList.toggle('open');
    });

    notificationBell.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            notificationBell.classList.toggle('open');
        }
    });
}

if (markAllRead) {
    markAllRead.addEventListener('click', (e) => {
        e.preventDefault();
        const unread = notificationList?.querySelectorAll('.notification-item.unread') || [];
        unread.forEach((item) => item.classList.remove('unread'));
        if (notificationCount) {
            notificationCount.textContent = '0';
            notificationCount.style.display = 'none';
        }
    });
}

// Close profile dropdown when clicking outside
document.addEventListener('click', (e) => {
    if (profileMenu) {
        const profileDropdown = document.querySelector('.profile-dropdown');
        if (profileDropdown && !profileMenu.contains(e.target)) {
            profileMenu.classList.remove('open');
        }
    }
    if (notificationBell && !notificationBell.contains(e.target)) {
        notificationBell.classList.remove('open');
    }
});

// Search functionality
const searchInput = document.querySelector('.search-input');
if (searchInput) {
    searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            const query = searchInput.value.trim();
            if (query) {
                window.location.href = appBase + '/views/dashboard.php?search=' + encodeURIComponent(query);
            }
        }
    });
}

// Modal functions
function openModal(title, formHTML) {
    const modal = document.getElementById('addModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    if (!modal || !modalTitle || !modalBody) return;

    modalTitle.innerHTML = '<i class="bi bi-plus-circle-fill" style="color: var(--color-accent); margin-right: 8px;"></i>' + title;
    modalBody.innerHTML = formHTML;
    modal.classList.add('show', 'is-open', 'active');
    modal.style.display = 'flex';
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('role', 'dialog');
    document.body.classList.add('modal-open');
}

function closeModal() {
    const modal = document.getElementById('addModal');
    if (!modal) return;
    modal.classList.remove('show', 'is-open', 'active');
    modal.style.display = 'none';
    const modalBody = document.getElementById('modalBody');
    if (modalBody) modalBody.innerHTML = '';
    document.body.classList.remove('modal-open');
}

// Close modal when clicking outside
const modal = document.getElementById('addModal');
if (modal) {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            closeModal();
        }
    });
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeModal();
    }
});

function getTemplateHtml(templateId) {
    const tpl = document.getElementById(templateId);
    return tpl ? tpl.innerHTML : '';
}

function openTemplateModal(templateId, title) {
    const formHTML = getTemplateHtml(templateId);
    if (formHTML) {
        openModal(title, formHTML);
        return true;
    }
    return false;
}

function initMaintenanceTemplate(modalBody) {
    const fleetSelect = modalBody?.querySelector('#fleetSelect');
    const assetSelect = modalBody?.querySelector('#assetSelect');
    if (!fleetSelect || !assetSelect) return;

    const sync = () => {
        const hasFleet = fleetSelect.value !== '';
        const hasAsset = assetSelect.value !== '';
        assetSelect.disabled = hasFleet;
        fleetSelect.disabled = hasAsset;
    };
    fleetSelect.addEventListener('change', sync);
    assetSelect.addEventListener('change', sync);
    sync();
}

function initReceivingTemplate(modalBody) {
    const poSelect = modalBody?.querySelector('.receiving-po-select');
    const tableBody = modalBody?.querySelector('.receiving-po-items-table tbody');
    const saveBtn = modalBody?.querySelector('.receiving-save-btn');
    const qcStatus = modalBody?.querySelector('.receiving-qc-status');
    const qcNotes = modalBody?.querySelector('.receiving-qc-notes');
    if (!poSelect || !tableBody || !saveBtn || !qcStatus || !qcNotes) return;

    const setTableMessage = (msg) => {
        tableBody.innerHTML = `<tr><td colspan="4" class="text-center text-muted">${msg}</td></tr>`;
        saveBtn.disabled = true;
    };

    const enforceQcNotes = () => {
        qcNotes.required = qcStatus.value === 'FAIL';
    };
    qcStatus.addEventListener('change', enforceQcNotes);
    enforceQcNotes();
    setTableMessage('Select a PO to load items');

    poSelect.addEventListener('change', async () => {
        const poId = poSelect.value;
        if (!poId) {
            setTableMessage('Select a PO to load items');
            return;
        }

        setTableMessage('Loading...');

        try {
            const res = await fetch(`../../controllers/get_po_items.php?po_id=${encodeURIComponent(poId)}`);
            const contentType = res.headers.get('content-type') || '';

            if (!res.ok || !contentType.includes('application/json')) {
                setTableMessage('Failed to load items');
                return;
            }

            const items = await res.json();
            if (!Array.isArray(items) || items.length === 0) {
                setTableMessage('All items already received');
                return;
            }

            tableBody.innerHTML = items.map((it, idx) => `
                <tr>
                  <td>
                    ${it.item_name}
                    <input type="hidden" name="items[${idx}][name]" value="${String(it.item_name).replaceAll('"', '&quot;')}">
                    <input type="hidden" name="items[${idx}][item_id]" value="${it.item_id ? String(it.item_id).replaceAll('"', '&quot;') : ''}">
                  </td>
                  <td>${it.po_qty}</td>
                  <td>${it.remaining_qty}</td>
                  <td>
                    <input
                      type="number"
                      name="items[${idx}][qty]"
                      class="form-control"
                      min="1"
                      max="${it.remaining_qty}"
                      value="${it.remaining_qty}"
                      required
                    >
                  </td>
                </tr>
            `).join('');

            saveBtn.disabled = false;
        } catch (e) {
            setTableMessage('Failed to load items');
        }
    });
}

function initializeModalTemplate(templateId) {
    const modalBody = document.getElementById('modalBody');
    if (!modalBody) return;

    if (templateId === 'addMaintenanceForm') {
        initMaintenanceTemplate(modalBody);
    }
    if (templateId === 'addReceivingForm') {
        initReceivingTemplate(modalBody);
    }
    if (templateId === 'addProcurementRequestForm') {
        initProcurementRequestTemplate(modalBody);
    }
}

function initProcurementRequestTemplate(modalBody) {
    const tbody = modalBody.querySelector('.procurement-lines-body');
    const addBtn = modalBody.querySelector('.request-line-add');
    if (!tbody || !addBtn) return;

    const MAX_LINES = 2;
    const suppliers = Array.from(
        tbody.querySelectorAll('select[name^="items[0][supplier]"] option')
    ).map((opt) => ({ value: opt.value, text: opt.textContent }));

    const masterItems = Array.from(
        tbody.querySelectorAll('select[name^="items[0][item_id]"] option')
    ).map((opt) => ({
        value: opt.value,
        text: opt.textContent,
        name: opt.getAttribute('data-name') || ''
    }));

    const syncLineItemName = (card) => {
        const sel = card.querySelector('.item-master-select');
        const hidden = card.querySelector('.item-master-name');
        if (!sel || !hidden) return;
        const selected = sel.options[sel.selectedIndex];
        const selectedName = selected ? (selected.getAttribute('data-name') || '') : '';
        if (selectedName) hidden.value = selectedName;
    };

    const updateLineState = () => {
        const cards = tbody.querySelectorAll('.procurement-line-card');
        cards.forEach((card, idx) => {
            const removeBtn = card.querySelector('.request-line-remove');
            const title = card.querySelector('h6');
            if (title) title.textContent = `Line ${idx + 1}`;
            if (removeBtn) removeBtn.disabled = cards.length === 1;

            card.querySelectorAll('input, select').forEach((field) => {
                const name = field.getAttribute('name') || '';
                field.setAttribute('name', name.replace(/items\[\d+\]/, `items[${idx}]`));
            });

            syncLineItemName(card);
        });

        const atLimit = cards.length >= MAX_LINES;
        addBtn.disabled = atLimit;
        addBtn.classList.toggle('disabled', atLimit);
        addBtn.setAttribute('aria-disabled', atLimit ? 'true' : 'false');
        addBtn.title = atLimit ? 'Maximum of 2 request lines' : '';
    };

    const buildSupplierOptions = () => suppliers
        .map((s) => `<option value="${String(s.value).replaceAll('"', '&quot;')}">${s.text}</option>`)
        .join('');

    const buildItemOptions = () => masterItems
        .map((i) => `<option value="${String(i.value).replaceAll('"', '&quot;')}" data-name="${String(i.name).replaceAll('"', '&quot;')}">${i.text}</option>`)
        .join('');

    const addLineCard = () => {
        const currentCount = tbody.querySelectorAll('.procurement-line-card').length;
        if (currentCount >= MAX_LINES) return;

        const idx = currentCount;
        const card = document.createElement('div');
        card.className = 'procurement-line-card';
        card.setAttribute('data-line-index', String(idx));
        card.innerHTML = `
          <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="mb-0 text-light">Line ${idx + 1}</h6>
            <button type="button" class="btn btn-sm btn-outline-danger request-line-remove"><i class="bi bi-trash"></i></button>
          </div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Item</label>
              <select name="items[${idx}][item_id]" class="form-select item-master-select">
                ${buildItemOptions()}
              </select>
              <input type="text" name="items[${idx}][item_name]" class="form-control mt-2 item-master-name" placeholder="or type item name" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Supplier</label>
              <select name="items[${idx}][supplier]" class="form-select" required>
                ${buildSupplierOptions()}
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Quantity</label>
              <input type="number" min="1" name="items[${idx}][quantity]" class="form-control" placeholder="Number of units" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Est. Amount</label>
              <input type="number" step="0.01" min="0" name="items[${idx}][estimated_amount]" class="form-control" value="0">
            </div>
          </div>
        `;
        tbody.appendChild(card);
        updateLineState();
    };

    addBtn.addEventListener('click', addLineCard);

    tbody.addEventListener('click', (e) => {
        const btn = e.target.closest('.request-line-remove');
        if (!btn) return;
        const cards = tbody.querySelectorAll('.procurement-line-card');
        if (cards.length <= 1) return;
        btn.closest('.procurement-line-card')?.remove();
        updateLineState();
    });

    tbody.addEventListener('change', (e) => {
        const sel = e.target.closest('.item-master-select');
        if (!sel) return;
        const card = sel.closest('.procurement-line-card');
        if (!card) return;
        syncLineItemName(card);
    });

    updateLineState();
}
function openBootstrapModalById(modalId) {
    const modalEl = document.getElementById(modalId);
    if (!modalEl) return false;
    if (window.bootstrap && window.bootstrap.Modal) {
        const bsModal = new window.bootstrap.Modal(modalEl);
        bsModal.show();
        return true;
    }
    modalEl.classList.add('show');
    modalEl.style.display = 'block';
    modalEl.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');
    return true;
}

// Function to open add supplier modal
function openAddSupplierModal() {
    openTemplateModal('addSupplierForm', 'Add Supplier');
}

// Function to open add project modal
function openAddProjectModal() {
    openTemplateModal('addProjectForm', 'Create Project');
}

// Function to open add asset modal
function openAddAssetModal() {
    openTemplateModal('addAssetForm', 'Add Asset');
}

// Function to open add procurement request modal
function openAddProcurementRequestModal() {
    openTemplateModal('addProcurementRequestForm', 'New Procurement Request');
}

// Function to open add fleet vehicle modal
function openAddFleetModal() {
    openTemplateModal('addFleetForm', 'Add Vehicle');
}

// Function to open add maintenance log modal
function openAddMaintenanceModal() {
    if (openTemplateModal('addMaintenanceForm', 'Log Maintenance / Repair')) {
        initializeModalTemplate('addMaintenanceForm');
    }
}

// Function to open add inventory item modal
function openAddInventoryModal() {
    openTemplateModal('addInventoryForm', 'Add Inventory Item');
}

// Generic trigger for plus buttons in modules.
document.addEventListener('click', (e) => {
    const trigger = e.target.closest('[data-modal-form], [data-bs-modal-id]');
    if (!trigger) return;

    e.preventDefault();
    const templateId = trigger.getAttribute('data-modal-form');
    const title = trigger.getAttribute('data-modal-title') || 'Add Item';
    const bsModalId = trigger.getAttribute('data-bs-modal-id');

    if (templateId && openTemplateModal(templateId, title)) {
        initializeModalTemplate(templateId);
        return;
    }
    if (bsModalId) {
        openBootstrapModalById(bsModalId);
    }
});

// Auto-open module modal from URL query params (used by dashboard quick actions).
(() => {
    const params = new URLSearchParams(window.location.search);
    const templateId = params.get('open_modal');
    const modalTitle = params.get('open_modal_title');
    const bsModalId = params.get('open_bs_modal');

    if (templateId) {
        // Prefer triggering the page's own plus button flow so behavior is identical.
        const trigger = document.querySelector(`[data-modal-form="${templateId}"]`);
        if (trigger) {
            trigger.click();
            return;
        }

        // Fallback if there is no trigger button rendered.
        if (openTemplateModal(templateId, modalTitle || 'Add Item')) {
            initializeModalTemplate(templateId);
            return;
        }
    }

    if (bsModalId) {
        openBootstrapModalById(bsModalId);
    }
})();

// --- Logout Functionality ---
const logoutButton = document.querySelector('.logout-btn');

if (logoutButton) {
    logoutButton.addEventListener('click', () => {
        const explicitUrl = logoutButton.getAttribute('data-logout-url');
        window.location.href = explicitUrl || (appBase + '/auth/logout.php');
    });
}

// --- DYNAMIC CONTENT LOADER ---

// 1. Define Content Generators
const views = {
    dashboard: () => `
        <h1>Dashboard Overview</h1>
        <p>Welcome back, Brian. Here is what's happening with your fleet today.</p>
        
        <div class="dashboard-grid">
            <div class="stat-card">
                <div class="card-header">
                    <i class="fas fa-taxi card-icon"></i>
                    <span class="card-title">Active Fleet</span>
                </div>
                
            </div>

            <div class="stat-card">
                <div class="card-header">
                    <i class="fas fa-users card-icon"></i>
                    <span class="card-title">Online Drivers</span>
                </div>
                
            </div>

            <div class="stat-card">
                <div class="card-header">
                    <i class="fas fa-exclamation-triangle card-icon"></i>
                    <span class="card-title">Maintenance Alerts</span>
                </div>
                
            </div>

            <div class="stat-card">
                <div class="card-header">
                    <i class="fas fa-wallet card-icon"></i>
                    <span class="card-title">Today's Earnings</span>
                </div>
                
            </div>
        </div>

       
    `,

    // A Generic Template generator for all subsystems
    genericModule: (title) => `
        <div class="module-header">
            <div>
                <h1>${title}</h1>
                <p>Manage your ${title.toLowerCase()} records and settings.</p>
            </div>
           
        </div>

       
    `
};

// 2. Navigation Handler
const contentArea = document.getElementById('contentArea');

// Select all dropdown items (the actual links)
const menuLinks = document.querySelectorAll('.dropdown-item a');

menuLinks.forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault(); // Stop page reload

        // Remove active class from all
        menuLinks.forEach(l => l.style.color = '');

        // Add active style to clicked
        e.target.style.color = 'var(--color-neon-accent)';

        // Get the text content and parent core
        const moduleTitle = e.target.textContent.trim();
        const parentDropdown = e.target.closest('.dropdown');
        const parentId = parentDropdown ? parentDropdown.id : '';

        // HR1-specific routing
        if (parentId === 'dropdown6' && window.HR1Views && contentArea) {
            // Show loading state
            contentArea.innerHTML = '<div style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin" style="font-size:2rem;color:var(--color-neon-accent);"></i><p style="margin-top:16px;">Loading...</p></div>';
            
            // Handle async rendering
            if (moduleTitle === 'Recruitment') {
                window.HR1Views.renderRecruitment(contentArea).catch(err => {
                    console.error('Error rendering Recruitment:', err);
                    contentArea.innerHTML = '<div class="hr1-empty-state">Error loading data. Please refresh the page.</div>';
                });
            } else if (moduleTitle === 'Application Management') {
                window.HR1Views.renderApplicationManagement(contentArea).catch(err => {
                    console.error('Error rendering Application Management:', err);
                    contentArea.innerHTML = '<div class="hr1-empty-state">Error loading data. Please refresh the page.</div>';
                });
            } else if (moduleTitle === 'New Hired On Board') {
                window.HR1Views.renderOnboarding(contentArea).catch(err => {
                    console.error('Error rendering Onboarding:', err);
                    contentArea.innerHTML = '<div class="hr1-empty-state">Error loading data. Please refresh the page.</div>';
                });
            } else if (moduleTitle === 'Performance Evaluation') {
                window.HR1Views.renderPerformance(contentArea).catch(err => {
                    console.error('Error rendering Performance:', err);
                    contentArea.innerHTML = '<div class="hr1-empty-state">Error loading data. Please refresh the page.</div>';
                });
            } else if (moduleTitle === 'Social Recognition') {
                window.HR1Views.renderRecognition(contentArea).catch(err => {
                    console.error('Error rendering Recognition:', err);
                    contentArea.innerHTML = '<div class="hr1-empty-state">Error loading data. Please refresh the page.</div>';
                });
            } else {
                contentArea.innerHTML = views.genericModule(moduleTitle);
            }
        } else if (contentArea) {
            // Default behavior for other cores
            contentArea.innerHTML = views.genericModule(moduleTitle);
        }

        // On mobile, close sidebar after click
        if (window.innerWidth <= 1024 && sidebar) {
            sidebar.classList.remove('open');
        }
    });
});

// 3. Load Default Dashboard on Start
// Check if we are on the dashboard page
if (contentArea) {
    contentArea.innerHTML = views.dashboard();
}











// --- Live "time ago" updater (dashboard + any page using .js-time-ago[data-ts]) ---
function initLiveTimeAgo() {
    const nodes = document.querySelectorAll('.js-time-ago[data-ts]');
    if (!nodes || !nodes.length) return;

    const formatAgo = (secondsInput) => {
        let seconds = Math.floor(Number(secondsInput) || 0);
        if (seconds < 0) seconds = 0;
        if (seconds < 2) return 'just now';
        if (seconds < 60) return `${seconds} second${seconds === 1 ? '' : 's'} ago`;
        if (seconds < 3600) {
            const m = Math.floor(seconds / 60);
            return `${m} minute${m === 1 ? '' : 's'} ago`;
        }
        if (seconds < 86400) {
            const h = Math.floor(seconds / 3600);
            return `${h} hour${h === 1 ? '' : 's'} ago`;
        }
        const d = Math.floor(seconds / 86400);
        return `${d} day${d === 1 ? '' : 's'} ago`;
    };

    const tick = () => {
        const now = Math.floor(Date.now() / 1000);
        nodes.forEach((el) => {
            const ts = Number(el.getAttribute('data-ts') || 0);
            if (!ts) return;
            el.textContent = formatAgo(now - ts);
        });
    };

    tick();

    // Avoid duplicate intervals if this initializer runs again.
    if (window.__timeAgoTimer) {
        window.clearInterval(window.__timeAgoTimer);
    }
    window.__timeAgoTimer = window.setInterval(tick, 1000);

    // Refresh immediately when tab becomes active again.
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) tick();
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLiveTimeAgo);
} else {
    initLiveTimeAgo();
}

// --- Dashboard pending tasks interactions (local persistence) ---
function initPendingTasks() {
    const items = document.querySelectorAll('.dashboard-page .pending-task-item[data-task-key]');
    if (!items.length) return;

    const storeKey = 'dashboard_pending_tasks_completed_v1';
    let doneMap = {};

    try {
        const raw = localStorage.getItem(storeKey);
        doneMap = raw ? JSON.parse(raw) : {};
    } catch (e) {
        doneMap = {};
    }

    const persist = () => {
        try {
            localStorage.setItem(storeKey, JSON.stringify(doneMap));
        } catch (e) {
            // ignore storage errors
        }
    };

    const renderItem = (item, checked) => {
        const checkbox = item.querySelector('.task-checkbox');
        if (!checkbox) return;

        item.classList.toggle('completed', !!checked);
        checkbox.classList.toggle('checked', !!checked);
        checkbox.setAttribute('aria-pressed', checked ? 'true' : 'false');
        checkbox.innerHTML = checked ? '<i class="bi bi-check-lg" aria-hidden="true"></i>' : '';
    };

    items.forEach((item) => {
        const key = item.getAttribute('data-task-key');
        if (!key) return;

        renderItem(item, !!doneMap[key]);

        const checkbox = item.querySelector('.task-checkbox');
        if (!checkbox) return;

        checkbox.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            const next = !doneMap[key];
            doneMap[key] = next;
            renderItem(item, next);
            persist();
        });

        item.addEventListener('click', (event) => {
            if (event.target.closest('.task-checkbox')) return;
            const url = item.getAttribute('data-task-url');
            if (url && url !== '#') {
                window.location.href = url;
            }
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPendingTasks);
} else {
    initPendingTasks();
}










