 // Theme Toggle
        function toggleTheme() {
            const body = document.body;
            const icon = document.getElementById('themeToggleBtn');
            
            body.classList.toggle('dark-mode');
            
            if (body.classList.contains('dark-mode')) {
                icon.innerHTML = '<i class="fas fa-sun"></i> Light Mode';
                localStorage.setItem('theme', 'dark');
            } else {
                icon.innerHTML = '<i class="fas fa-moon"></i> Dark Mode';
                localStorage.setItem('theme', 'light');
            }
        }

        // Load saved theme
        document.addEventListener('DOMContentLoaded', function() {
            const savedTheme = localStorage.getItem('theme');
            const icon = document.getElementById('themeToggleBtn');
            
            if (savedTheme === 'dark') {
                document.body.classList.add('dark-mode');
                icon.innerHTML = '<i class="fas fa-sun"></i> Light Mode';
            } else {
                document.body.classList.remove('dark-mode');
                icon.innerHTML = '<i class="fas fa-moon"></i> Dark Mode';
            }
        });

        // Theme toggle button
        document.getElementById('themeToggleBtn').addEventListener('click', toggleTheme);

        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
            this.textContent = passwordInput.type === 'password' ? 'Show' : 'Hide';
        });

        document.getElementById('toggleLoginPassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('loginPassword');
            passwordInput.type = passwordInput.type === 'password' ? 'text' : 'password';
            this.textContent = passwordInput.type === 'password' ? 'Show' : 'Hide';
        });

        // Register form submission
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const email = document.getElementById('email').value.trim();
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            const confirmPassword = document.getElementById('confirmPassword').value.trim();
            const message = document.getElementById('message');

            if (username.length < 3) {
                message.className = 'message error';
                message.textContent = 'Username must be at least 3 characters';
                return;
            }
            if (!validateEmail(email)) {
                message.className = 'message error';
                message.textContent = 'Invalid email format';
                return;
            }
            if (password.length < 6) {
                message.className = 'message error';
                message.textContent = 'Password must be at least 6 characters';
                return;
            }
            if (password !== confirmPassword) {
                message.className = 'message error';
                message.textContent = 'Passwords do not match';
                return;
            }

            document.getElementById('confirmModal').style.display = 'block';
        });

        // Login form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const email = document.getElementById('loginEmail').value.trim();
            const password = document.getElementById('loginPassword').value.trim();
            const message = document.getElementById('message');

            if (!validateEmail(email)) {
                message.className = 'message error';
                message.textContent = 'Invalid email format';
                return;
            }
            if (password.length < 6) {
                message.className = 'message error';
                message.textContent = 'Password must be at least 6 characters';
                return;
            }

            message.className = 'message success';
            message.textContent = 'Logging in...';
            simulateProgressAndRedirect();
        });

        function validateEmail(email) {
            return /^[^ ]+@[^ ]+\.[a-z]{2,3}$/.test(email.toLowerCase());
        }

        function simulateProgressAndRedirect() {
            let width = 0;
            const progressBar = document.getElementById('progressBar');
            progressBar.style.width = '0';
            
            const interval = setInterval(() => {
                width += 20;
                progressBar.style.width = width + '%';
                if (width >= 100) {
                    clearInterval(interval);
                    setTimeout(() => {
                        window.location.href = "welcome.html";
                    }, 500);
                }
            }, 300);
        }

        // Modal buttons
        document.getElementById('confirmBtn').addEventListener('click', function() {
            document.getElementById('confirmModal').style.display = 'none';
            const message = document.getElementById('message');
            message.className = 'message success';
            message.textContent = 'Account created successfully!';
            simulateProgressAndRedirect();
        });

        document.getElementById('cancelBtn').addEventListener('click', function() {
            document.getElementById('confirmModal').style.display = 'none';
        });

        // Close modal on outside click
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('confirmModal');
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });

        // Switch between forms
        document.getElementById('switchToLogin').addEventListener('click', function() {
            document.getElementById('signupForm').classList.add('hidden');
            document.getElementById('loginForm').classList.remove('hidden');
            document.getElementById('formTitle').innerHTML = '<i class="fas fa-sign-in-alt"></i> Login to Your Account';
            this.classList.add('hidden');
            document.getElementById('switchToRegister').classList.remove('hidden');
            document.getElementById('message').className = 'message';
            document.getElementById('message').textContent = '';
        });

        document.getElementById('switchToRegister').addEventListener('click', function() {
            document.getElementById('signupForm').classList.remove('hidden');
            document.getElementById('loginForm').classList.add('hidden');
            document.getElementById('formTitle').innerHTML = '<i class="fas fa-user-plus"></i> Create Account';
            document.getElementById('switchToLogin').classList.remove('hidden');
            this.classList.add('hidden');
            document.getElementById('message').className = 'message';
            document.getElementById('message').textContent = '';
        });

        // Enter key support
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                const activeForm = document.querySelector('form:not(.hidden)');
                if (activeForm) {
                    activeForm.dispatchEvent(new Event('submit'));
                }
            }
        });
