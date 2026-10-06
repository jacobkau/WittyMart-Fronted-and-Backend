<!-- Footer -->
    <footer class="footer">
        <div class="footer-row">
            <div class="footer-card">
                <h2>WittyMart</h2>
                <p>Smart Shopping for Witty Minds!</p>
                <br>
                <p>© 2026 WittyMart. All rights reserved.</p>
                <?php if (!empty($isLoggedIn)): ?>
                    <p style="margin-top: 10px; font-size: 12px; color: #888;">
                        <i class="fas fa-user-check"></i> Logged in as <?php echo htmlspecialchars($userName ?? 'User'); ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="footer-card">
                <h2>Subscribe to Our Newsletter</h2>
                <form id="newsletter-form" onsubmit="subscribeNewsletter(event)">
                    <input type="email" id="newsletter-email" placeholder="Enter your email" required>
                    <button type="submit" id="newsletter-btn">Subscribe</button>
                </form>
                <div id="newsletter-message" style="margin-top: 8px; font-size: 13px; display: none;"></div>
            </div>
            <div class="footer-card">
                <h2>Contact Us</h2>
                <p>Email: <a href="mailto:wittyhighbrowtechnologies@gmail.com" style="color:#02c786;">wittyhighbrowtechnologies@gmail.com</a></p>
                <p>Phone: +254 768 374 497</p>
                <p>Location: Nairobi, Kenya</p>
            </div>
        </div>
        <div class="footer-row">
            <div class="footer-card">
                <h2>Quick Links</h2>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="shop.php">Shop</a></li>
                    <li><a href="cart.php">Cart</a></li>
                    <li><a href="about.php">About</a></li>
                    <li><a href="contact.php">Contact</a></li>
                    <?php if (empty($isLoggedIn)): ?>
                        <li><a href="login-register.php">Login / Register</a></li>
                    <?php else: ?>
                        <li><a href="home.php">My Account</a></li>
                        <li><a href="logout.php">Logout</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <!--
            <div class="footer-card">
                <h2>Follow Us</h2>
                <ul>
                    <li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li>
                    <li><a href="#"><i class="fab fa-twitter"></i> Twitter</a></li>
                    <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                    <li><a href="#"><i class="fab fa-youtube"></i> YouTube</a></li>
                </ul>
            </div>
            -->

            <div class="footer-card">
                <h2>Legal</h2>
                <ul>
                    <li><a href="terms.php#privacy">Privacy Policy</a></li>
                    <li><a href="terms.php#terms">Terms of Service</a></li>
                    <li><a href="terms.php#returns">Return Policy</a></li>
                    <?php if (!empty($isAdmin)): ?>
                        <li><a href="admin/dashboard.php"><i class="fas fa-crown"></i> Admin Panel</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div id="footer-bottom">
            <p>Built by Witty Highbrow Technologies!</p>
        </div>
    </footer>

    <script>
    // ============================================
    // FOOTER SCRIPTS
    // ============================================
    (function () {
        'use strict';

        // ============================================
        // FORMSPREE ENDPOINT 
        // ============================================
        const FORMSPREE_FORM_ID = <?php echo json_encode(getenv('FORMSPREE_FORM_ID') ?: ''); ?>;
        const FORMSPREE_ENDPOINT = FORMSPREE_FORM_ID
            ? 'https://formspree.io/f/' + FORMSPREE_FORM_ID
            : '';

        // ============================================
        // NEWSLETTER SUBSCRIPTION 
        // ============================================
        window.subscribeNewsletter = function (event) {
            event.preventDefault();

            const emailInput = document.getElementById('newsletter-email');
            const btn        = document.getElementById('newsletter-btn');
            const email      = (emailInput?.value || '').trim();

            if (!email) {
                showNewsletterMessage('Please enter your email address.', 'error');
                return;
            }

            // Basic email sanity check
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                showNewsletterMessage('Please enter a valid email address.', 'error');
                return;
            }

            if (!FORMSPREE_ENDPOINT) {
                console.error('Formspree form ID is not configured (FORMSPREE_FORM_ID env var missing).');
                showNewsletterMessage('Subscription service is not configured. Please try again later.', 'error');
                return;
            }

            // Disable button while submitting
            const originalBtnHTML = btn ? btn.innerHTML : '';
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Subscribing…';
            }

            showNewsletterMessage('Subscribing…', 'info');

            fetch(FORMSPREE_ENDPOINT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    email: email,
                    source: 'footer_newsletter',
                    page: window.location.pathname,
                    _subject: 'New WittyMart newsletter subscriber'
                })
            })
            .then(async (response) => {
                const data = await response.json().catch(() => ({}));
                if (response.ok) {
                    return { ok: true, data: data };
                }
                // Formspree returns errors in `errors` array
                const msg = (data.errors && data.errors.length)
                    ? data.errors.map(e => e.message).join(', ')
                    : (data.error || 'Subscription failed. Please try again.');
                return { ok: false, message: msg };
            })
            .then((result) => {
                if (result.ok) {
                    showNewsletterMessage('Thank you for subscribing!', 'success');
                    if (emailInput) emailInput.value = '';
                } else {
                    showNewsletterMessage(result.message, 'error');
                }
            })
            .catch((error) => {
                console.error('Newsletter subscription error:', error);
                showNewsletterMessage('Network error. Please try again.', 'error');
            })
            .finally(() => {
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalBtnHTML || 'Subscribe';
                }
            });
        };

        function showNewsletterMessage(message, type) {
            const messageDiv = document.getElementById('newsletter-message');
            if (!messageDiv) return;
            messageDiv.textContent = message;
            messageDiv.style.display = 'block';

            if (type === 'success') {
                messageDiv.style.color = '#28a745';
            } else if (type === 'info') {
                messageDiv.style.color = '#0c5460';
            } else {
                messageDiv.style.color = '#dc3545';
            }

            // Auto-hide only for success/error (keep 'info' visible during submit)
            if (type !== 'info') {
                clearTimeout(messageDiv._hideTimer);
                messageDiv._hideTimer = setTimeout(() => {
                    messageDiv.style.transition = 'opacity 0.4s ease';
                    messageDiv.style.opacity = '0';
                    setTimeout(() => {
                        messageDiv.style.display = 'none';
                        messageDiv.style.opacity = '1';
                    }, 400);
                }, 5000);
            }
        }

        // ---------- DARK MODE TOGGLE ----------
        if (typeof window.toggleTheme !== 'function') {
            window.toggleTheme = function () {
                document.body.classList.toggle('dark-mode');
                const isDark = document.body.classList.contains('dark-mode');
                const icon = document.getElementById('theme-icon');
                if (icon) {
                    icon.innerHTML = isDark ? '<i class="fas fa-moon"></i>' : '<i class="fas fa-sun"></i>';
                    icon.title = isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode';
                }
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
            };
        }

        // Load saved theme — scoped variable to avoid collision with header.php
        const savedThemeFooter = localStorage.getItem('theme');
        if (savedThemeFooter === 'dark') {
            document.body.classList.add('dark-mode');
            const icon = document.getElementById('theme-icon');
            if (icon) {
                icon.innerHTML = '<i class="fas fa-moon"></i>';
                icon.title = 'Switch to Light Mode';
            }
        }

        // ---------- MOBILE MENU TOGGLE (fallback) ----------
        if (typeof window.toggleMenu !== 'function') {
            window.toggleMenu = function () {
                const navLinks = document.getElementById('nav-links') || document.getElementById('navLinks');
                if (navLinks) navLinks.classList.toggle('active');
            };
        }

        // ---------- SIDEBAR TOGGLE (fallback) ----------
        if (typeof window.toggleSidebar !== 'function') {
            window.toggleSidebar = function () {
                const sidebar = document.getElementById('sidebar');
                const overlay = document.getElementById('sidebarOverlay');
                if (sidebar && overlay) {
                    sidebar.classList.toggle('active');
                    overlay.classList.toggle('active');
                    document.body.classList.toggle('sidebar-open');
                }
            };
        }

        document.addEventListener('DOMContentLoaded', function () {
            const overlay = document.getElementById('sidebarOverlay');
            if (overlay && !overlay.dataset.footerBound) {
                overlay.dataset.footerBound = '1';
                overlay.addEventListener('click', function () {
                    if (typeof window.toggleSidebar === 'function') {
                        window.toggleSidebar();
                    }
                });
            }
        });
    })();
    </script>
</body>
</html>
