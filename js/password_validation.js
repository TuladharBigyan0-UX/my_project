document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("signupForm");
    const password = document.getElementById("password");
    const confirmPassword = document.getElementById("confirm_password");
    const contact = form.querySelector('[name="contact"]');

    const passwordError = document.getElementById("passwordError");
    const confirmError = document.getElementById("confirmPasswordError");
    const contactError = document.getElementById("contactError");

    // ✅ Show / Hide password
    document.querySelectorAll(".toggle-btn").forEach(btn => {
        btn.addEventListener("click", function () {
            const input = document.getElementById(this.dataset.target);
            if (input.type === "password") {
                input.type = "text";
                this.textContent = "Hide";
            } else {
                input.type = "password";
                this.textContent = "Show";
            }
        });
    });

    // ✅ Form submission validation
    form.addEventListener("submit", function (e) {
        let valid = true;

        // Password strength
        const pwd = password.value;
        const pwdRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W]).{8,}$/;

        if (!pwdRegex.test(pwd)) {
            passwordError.textContent = "Password must be at least 8 characters with uppercase, lowercase, number & special character.";
            valid = false;
        } else {
            passwordError.textContent = "";
        }

        // Confirm password match
        if (pwd !== confirmPassword.value) {
            confirmError.textContent = "Passwords do not match.";
            valid = false;
        } else {
            confirmError.textContent = "";
        }

        // Contact number validation
        const contactVal = contact.value;
        if (!/^9[6-8][0-9]{8}$/.test(contactVal)) {
            contactError.textContent = "Contact must start with 9, second digit 6-8, and be 10 digits.";
            valid = false;
        } else {
            contactError.textContent = "";
        }

        if (!valid) {
            e.preventDefault(); // stop submission
        }
    });
});
