document.addEventListener('DOMContentLoaded', () => {
    // --- Utils ---
    const getEl = (id) => document.getElementById(id);
    const queryEl = (sel) => document.querySelector(sel);
    const queryAll = (sel) => document.querySelectorAll(sel);

    // --- Components ---
    const passwordInput = getEl('password');
    const passwordToggle = getEl('passwordToggle');

    // Modals
    const forgotPasswordModal = getEl('forgotPasswordModal');
    const resetSuccessModal = getEl('resetSuccessModal');
    const contactAdminModal = getEl('contactAdminModal');

    const allModals = [forgotPasswordModal, resetSuccessModal, contactAdminModal].filter(m => m);

    // --- Modal Logic ---
    const openModal = (modal) => {
        if (!modal) return;
        modal.classList.add('is-open');
        document.body.style.overflow = 'hidden';
    };

    const closeModal = (modal) => {
        if (!modal) return;
        modal.classList.remove('is-open');
        // Only re-enable scroll if no other modals are open
        if (queryAll('.modal-backdrop.is-open').length === 0) {
            document.body.style.overflow = '';
        }
    };

    // Close buttons
    queryAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const modalId = e.currentTarget.getAttribute('data-close-modal');
            closeModal(getEl(modalId));
        });
    });

    // Outside click close
    allModals.forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal(modal);
        });
    });

    // ESC close
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const openModalEl = queryEl('.modal-backdrop.is-open');
            if (openModalEl) closeModal(openModalEl);
        }
    });

    // --- Links & Actions ---

    // Password Toggle
    if (passwordToggle && passwordInput) {
        passwordToggle.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            passwordToggle.classList.toggle('fa-eye', isPassword);
            passwordToggle.classList.toggle('fa-eye-slash', !isPassword);
        });
    }

    // Modal Triggers
    const bindModalTrigger = (elementId, modalEl) => {
        const el = getEl(elementId);
        if (el && modalEl) {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                openModal(modalEl);
            });
        }
    };

    bindModalTrigger('forgotPasswordLink', forgotPasswordModal);
    bindModalTrigger('contactAdminLink', contactAdminModal);

    // Reset Flow
    const sendResetLink = getEl('sendResetLink');
    if (sendResetLink) {
        sendResetLink.addEventListener('click', (e) => {
            e.preventDefault();
            closeModal(forgotPasswordModal);

            const resetEmailInput = getEl('resetEmail');
            const emailToDisplay = resetEmailInput?.value || "your email address";

            // Update success message
            if (resetSuccessModal) {
                const msg = resetSuccessModal.querySelector('p');
                if (msg) msg.innerHTML = `We've sent a password reset link to **${emailToDisplay}**. Please check your inbox.`;
                openModal(resetSuccessModal);
            }
            showToast('Password reset email sent successfully');
        });
    }

    // "Try different email" flow
    const tryDifferentEmailLink = getEl('tryDifferentEmailLink');
    if (tryDifferentEmailLink) {
        tryDifferentEmailLink.addEventListener('click', (e) => {
            e.preventDefault();
            closeModal(resetSuccessModal);
            openModal(forgotPasswordModal);
        });
    }

    // Contact Form Char Count
    const messageTextarea = getEl('message');
    const charCountSpan = queryEl('.char-count');
    if (messageTextarea && charCountSpan) {
        messageTextarea.addEventListener('input', () => {
            const len = messageTextarea.value.length;
            const max = messageTextarea.getAttribute('maxlength');
            charCountSpan.textContent = `${len}/${max}`;
        });
    }

    // --- Feedback ---
    const successToast = getEl('successToast');
    const showToast = (message) => {
        if (!successToast) return;
        // successToast.querySelector('span').textContent = message; 
        successToast.classList.add('show');
        setTimeout(() => successToast.classList.remove('show'), 5000);
    };

    const showError = (message) => {
        let errorDiv = queryEl('.login-error-message');
        const loginForm = queryEl('.login-form');

        if (!errorDiv && loginForm) {
            errorDiv = document.createElement('div');
            errorDiv.className = 'login-error-message';
            // Styling is now handled partly here, but could be moved to CSS
            errorDiv.style.cssText = `
                background: rgba(255, 82, 82, 0.15);
                border: 1px solid rgba(255, 82, 82, 0.3);
                color: #ff5252;
                padding: 12px 15px;
                border-radius: 8px;
                margin-bottom: 15px;
                font-size: 0.9rem;
                display: flex;
                align-items: center;
                gap: 10px;
            `;
            loginForm.insertBefore(errorDiv, loginForm.firstChild);
        }

        if (errorDiv) {
            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i><span>${message}</span>`;
            errorDiv.style.display = 'flex';
            setTimeout(() => { errorDiv.style.display = 'none'; }, 5000);
        }
    };    // --- Forms ---
    // Login submit is handled by PHP (auth/login.php). Do not intercept here.

    const submitContact = getEl('submitContact');
    if (submitContact) {
        submitContact.addEventListener('click', (e) => {
            e.preventDefault();
            const form = queryEl('.contact-form');
            if (form && form.checkValidity()) {
                closeModal(contactAdminModal);
                showToast('Request submitted successfully!');
                form.reset();
            } else if (form) {
                form.reportValidity();
            }
        });
    }
});

