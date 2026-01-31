// Set up the global CSS variables in JS for dynamic use (optional, but good practice)
document.documentElement.style.setProperty('--color-neon-accent', '#00e676');

const sidebar = document.getElementById('sidebar');
const hamburger = document.getElementById('hamburger');
const navLinks = document.querySelectorAll('.nav-link');
const profileMenu = document.getElementById('profileMenu');

// Initial check for screen size and set collapsed state
const checkCollapseState = () => {
    sidebar.classList.add('collapsed');
    sidebar.classList.remove('open');
};
checkCollapseState();
window.addEventListener('resize', checkCollapseState);

// Sidebar is now always collapsed by default - only toggle with hamburger button
// No hover expansion

// Toggle sidebar on mobile/tablet (Hamburger menu)
hamburger.addEventListener('click', () => {
    if (window.innerWidth < 1024) {
        sidebar.classList.toggle('open');
        sidebar.classList.remove('collapsed');
    } else {
        sidebar.classList.toggle('open');
        sidebar.classList.toggle('collapsed');
    }
});

// Handle profile dropdown toggle (for better touch support)
profileMenu.addEventListener('click', (e) => {
    e.stopPropagation(); // Prevent the click from immediately bubbling up and closing the menu
    profileMenu.classList.toggle('open');
});

// Close profile dropdown when clicking outside
document.addEventListener('click', (e) => {
    const profileDropdown = document.querySelector('.profile-dropdown');
    if (profileDropdown && !profileMenu.contains(e.target)) {
        profileDropdown.style.display = 'none';
    }
});

// Search functionality
const searchInput = document.querySelector('.search-input');
if (searchInput) {
    searchInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            const query = searchInput.value.trim();
            if (query) {
                window.location.href = '/views/dashboard.php?search=' + encodeURIComponent(query);
            }
        }
    });
}

// Modal functions
function openModal(title, formHTML) {
    const modal = document.getElementById('addModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    
    modalTitle.textContent = title;
    modalBody.innerHTML = formHTML;
    modal.classList.add('show');
}

function closeModal() {
    const modal = document.getElementById('addModal');
    modal.classList.remove('show');
    document.getElementById('modalBody').innerHTML = '';
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

// Function to open add procurement modal
function openAddProcurementModal() {
    const formHTML = document.getElementById('addProcurementForm')?.innerHTML || '';
    if (formHTML) {
        openModal('Add Procurement Request', formHTML);
    }
}

// Function to open add supplier modal
function openAddSupplierModal() {
    const formHTML = document.getElementById('addSupplierForm')?.innerHTML || '';
    if (formHTML) {
        openModal('Add Supplier', formHTML);
    }
}

// Function to open add project modal
function openAddProjectModal() {
    const formHTML = document.getElementById('addProjectForm')?.innerHTML || '';
    if (formHTML) {
        openModal('Create Project', formHTML);
    }
}

// Function to open add asset modal
function openAddAssetModal() {
    const formHTML = document.getElementById('addAssetForm')?.innerHTML || '';
    if (formHTML) {
        openModal('Add Asset', formHTML);
    }
}

// --- Logout Functionality ---
const logoutButton = document.querySelector('.logout-btn');

if (logoutButton) {
    logoutButton.addEventListener('click', () => {
        window.location.href = '/logistics-1/auth/logout.php';
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
        if (window.innerWidth <= 1024) {
            sidebar.classList.remove('open');
        }
    });
});

// 3. Load Default Dashboard on Start
// Check if we are on the dashboard page
if (contentArea) {
    contentArea.innerHTML = views.dashboard();
}