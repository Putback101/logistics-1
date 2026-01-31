document.addEventListener('DOMContentLoaded', () => {
    const passwordInput = document.getElementById('password');
    const passwordToggle = document.getElementById('passwordToggle');
    if (passwordToggle && passwordInput) {
        passwordToggle.addEventListener('click', () => {
            const isPassword = passwordInput.type === 'password';
            passwordInput.type = isPassword ? 'text' : 'password';
            passwordToggle.classList.toggle('fa-eye', isPassword);
            passwordToggle.classList.toggle('fa-eye-slash', !isPassword);
        });
    }

    const forgotPasswordModal = document.getElementById('forgotPasswordModal');
    const resetSuccessModal = document.getElementById('resetSuccessModal');
    const contactAdminModal = document.getElementById('contactAdminModal');
    const allModals = [forgotPasswordModal, resetSuccessModal, contactAdminModal].filter(m => m);

    const openModal = (modal) => {
        if (modal) {
            modal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
        }
    };

    const closeModal = (modal) => {
        if (modal) {
            modal.classList.remove('is-open');
            if (document.querySelectorAll('.modal-backdrop.is-open').length === 0) {
                document.body.style.overflow = '';
            }
        }
    };

    // Close Modals on close button click
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const modalId = e.target.getAttribute('data-close-modal');
            const modal = document.getElementById(modalId);
            closeModal(modal);
        });
    });

    // Close Modals on outside click
    allModals.forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal(modal);
            }
        });
    });

    // --- Link/Button Handlers (Updated to use 'if' checks) ---

    // Open Forgot Password Modal
    const forgotPasswordLink = document.getElementById('forgotPasswordLink');
    if (forgotPasswordLink) {
        forgotPasswordLink.addEventListener('click', (e) => {
            e.preventDefault();
            openModal(forgotPasswordModal);
        });
    }

    // Open Contact Admin Modal
    const contactAdminLink = document.getElementById('contactAdminLink');
    if (contactAdminLink) {
        contactAdminLink.addEventListener('click', (e) => {
            e.preventDefault();
            openModal(contactAdminModal);
        });
    }

    // Simulate Reset Link Submission -> Open Success Modal & Show Toast
    const sendResetLink = document.getElementById('sendResetLink');
    if (sendResetLink) {
        sendResetLink.addEventListener('click', (e) => {
            e.preventDefault();
            // --- Placeholder Logic for Reset ---
            closeModal(forgotPasswordModal);

            const resetEmailInput = document.getElementById('resetEmail');
            // Fallback value for email if input or value is not available
            const emailToDisplay = resetEmailInput && resetEmailInput.value ? resetEmailInput.value : "an email address";

            // Update the success message with the email
            if (resetSuccessModal) {
                const messageParagraph = resetSuccessModal.querySelector('p');
                if (messageParagraph) {
                    messageParagraph.innerHTML =
                        `We've sent a password reset link to **${emailToDisplay}**. Please check your inbox and follow the instructions.`;
                }
                openModal(resetSuccessModal);
            }

            // Show the success toast notification
            showToast('Password reset email sent successfully');
        });
    }

    // Try a different email link (Success modal -> Forgot Password modal)
    const tryDifferentEmailLink = document.getElementById('tryDifferentEmailLink');
    if (tryDifferentEmailLink) {
        tryDifferentEmailLink.addEventListener('click', (e) => {
            e.preventDefault();
            closeModal(resetSuccessModal);
            openModal(forgotPasswordModal);
        });
    }

    // --- Contact Form Char Count ---
    const messageTextarea = document.getElementById('message');
    const charCountSpan = document.querySelector('.char-count');
    if (messageTextarea && charCountSpan) {
        messageTextarea.addEventListener('input', () => {
            const currentLength = messageTextarea.value.length;
            const maxLength = messageTextarea.getAttribute('maxlength');
            charCountSpan.textContent = `${currentLength}/${maxLength}`;
        });
    }

    // --- Success Toast Notification Logic ---
    const successToast = document.getElementById('successToast');

    function showToast(message) {
        if (successToast) {
            // successToast.querySelector('span').textContent = message; // Uncomment to show custom message
            successToast.classList.add('show');
            setTimeout(() => {
                successToast.classList.remove('show');
            }, 5000); // Hide after 5 seconds
        }
    }

    // Simulate Login Form submission
    const loginForm = document.querySelector('.login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', (e) => {
            

            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');

            const email = emailInput ? emailInput.value.trim() : '';
            const password = passwordInput ? passwordInput.value.trim() : '';

            // --- Simple Client-Side Validation Logic ---
            // In a real application, you would send these credentials to your server (API)

            // Example: Allow any non-empty input for development purposes
            if (email.length > 0 && password.length > 0) {
                const dashboardFile = 'index.php';
                window.location.href = dashboardFile;
            } else {
                showToast('Please enter both email and password.');
            }
        });
    }

    // Simulate Contact Admin Form submission
    const submitContact = document.getElementById('submitContact');
    if (submitContact) {
        submitContact.addEventListener('click', (e) => {
            e.preventDefault();
            // *** INSERT YOUR CONTACT ADMIN API CALL HERE ***

            // Assuming submission success:
            if (document.querySelector('.contact-form').checkValidity()) {
                closeModal(contactAdminModal);
                showToast('Request submitted successfully!');
            } else {
                // If form is invalid, trigger browser validation hints
                document.querySelector('.contact-form').reportValidity();
            }
        });
    }
});