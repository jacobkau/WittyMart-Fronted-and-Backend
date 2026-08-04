// ============================================
// THEME TOGGLE
// ============================================
// Enhanced Theme Toggle with Sun/Moon Icons
function toggleTheme() {
    const body = document.body;
    const icon = document.getElementById('theme-icon');
    
    body.classList.toggle('dark-mode');
    
    // Animated icon transition
    if (icon) {
        icon.style.transition = 'transform 0.3s ease';
        icon.style.transform = 'rotate(180deg)';
        
        setTimeout(() => {
            if (body.classList.contains('dark-mode')) {
                icon.innerHTML = '<i class="fas fa-moon"></i>';
                icon.title = 'Switch to Light Mode';
                localStorage.setItem('theme', 'dark');
            } else {
                icon.innerHTML = '<i class="fas fa-sun"></i>';
                icon.title = 'Switch to Dark Mode';
                localStorage.setItem('theme', 'light');
            }
            icon.style.transform = 'rotate(0deg)';
        }, 150);
    }
}

// Detect system preference
function getSystemTheme() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

// Load theme with system preference fallback
document.addEventListener('DOMContentLoaded', function() {
    const savedTheme = localStorage.getItem('theme');
    const systemTheme = getSystemTheme();
    const theme = savedTheme || systemTheme;
    const icon = document.getElementById('theme-icon');
    
    if (theme === 'dark') {
        document.body.classList.add('dark-mode');
        if (icon) {
            icon.innerHTML = '<i class="fas fa-moon"></i>';
            icon.title = 'Switch to Light Mode';
        }
    } else {
        document.body.classList.remove('dark-mode');
        if (icon) {
            icon.innerHTML = '<i class="fas fa-sun"></i>';
            icon.title = 'Switch to Dark Mode';
        }
    }
    
    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
        if (!localStorage.getItem('theme')) {
            const newTheme = e.matches ? 'dark' : 'light';
            const icon = document.getElementById('theme-icon');
            if (newTheme === 'dark') {
                document.body.classList.add('dark-mode');
                if (icon) {
                    icon.innerHTML = '<i class="fas fa-moon"></i>';
                }
            } else {
                document.body.classList.remove('dark-mode');
                if (icon) {
                    icon.innerHTML = '<i class="fas fa-sun"></i>';
                }
            }
        }
    });
});

// ============================================
// MOBILE MENU TOGGLE
// ============================================
function toggleMenu() {
    const nav = document.getElementById('main-nav');
    const menuIcon = document.getElementById('menuIcon');
    
    if (nav) {
        nav.classList.toggle('show');
        
        // Toggle menu icon
        if (menuIcon) {
            if (nav.classList.contains('show')) {
                menuIcon.className = 'fas fa-times';
            } else {
                menuIcon.className = 'fas fa-bars';
            }
        }
    }
}

// Close menu when clicking outside
document.addEventListener('click', function(event) {
    const nav = document.getElementById('main-nav');
    const menuToggle = document.getElementById('menuToggleBtn');
    const navLinks = document.getElementById('nav-links');
    
    if (nav && nav.classList.contains('show')) {
        if (!nav.contains(event.target) && menuToggle && !menuToggle.contains(event.target)) {
            nav.classList.remove('show');
            const menuIcon = document.getElementById('menuIcon');
            if (menuIcon) {
                menuIcon.className = 'fas fa-bars';
            }
        }
    }
});

// Close menu when a link is clicked
document.querySelectorAll('.nav-links a').forEach(function(link) {
    link.addEventListener('click', function() {
        const nav = document.getElementById('main-nav');
        if (nav && nav.classList.contains('show')) {
            nav.classList.remove('show');
            const menuIcon = document.getElementById('menuIcon');
            if (menuIcon) {
                menuIcon.className = 'fas fa-bars';
            }
        }
    });
});

// ============================================
// SIDEBAR TOGGLE
// ============================================
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar) {
        sidebar.classList.toggle('active');
        if (overlay) {
            overlay.classList.toggle('active');
        }
        document.body.classList.toggle('sidebar-open');
        
        // Close mobile menu when sidebar opens
        const nav = document.getElementById('main-nav');
        if (nav && nav.classList.contains('show')) {
            nav.classList.remove('show');
            const menuIcon = document.getElementById('menuIcon');
            if (menuIcon) {
                menuIcon.className = 'fas fa-bars';
            }
        }
    }
}

// ============================================
// HERO SLIDER
// ============================================
let currentSlide = 0;
const slides = document.querySelectorAll('#heroSlides .slide');
const totalSlides = slides.length;

function showSlide(index) {
    if (index < 0) index = totalSlides - 1;
    if (index >= totalSlides) index = 0;
    const offset = -index * 100;
    const slider = document.getElementById('heroSlides');
    if (slider) {
        slider.style.transform = `translateX(${offset}%)`;
    }
    currentSlide = index;
}

function nextSlide() {
    showSlide(currentSlide + 1);
}

function prevSlide() {
    showSlide(currentSlide - 1);
}

// Auto slide - only if slides exist
if (totalSlides > 0) {
    setInterval(nextSlide, 5000);
}

// ============================================
// TESTIMONIAL SLIDER
// ============================================
let currentTestimonial = 0;
const testimonials = document.querySelectorAll('#testimonialTrack .slide1');
const totalTestimonials = testimonials.length;

function showTestimonial(index) {
    if (index < 0) index = totalTestimonials - 1;
    if (index >= totalTestimonials) index = 0;
    const offset = -index * 100;
    const track = document.getElementById('testimonialTrack');
    if (track) {
        track.style.transform = `translateX(${offset}%)`;
    }
    currentTestimonial = index;
}

function nextTestimonial() {
    showTestimonial(currentTestimonial + 1);
}

function prevTestimonial() {
    showTestimonial(currentTestimonial - 1);
}

// Auto testimonial slide - only if testimonials exist
if (totalTestimonials > 0) {
    setInterval(nextTestimonial, 6000);
}

// ============================================
// NEWSLETTER FORM
// ============================================
const newsletterForm = document.getElementById('newsletter-form');
if (newsletterForm) {
    newsletterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const email = this.querySelector('input[type="email"]');
        if (email && email.value) {
            alert('Thank you for subscribing! You will receive updates from WittyMart.');
            email.value = '';
        }
    });
}

// ============================================
// SHOW PAGE FUNCTION (for terms page)
// ============================================
function showPage(pageId) {
    // Hide all subpages
    var pages = document.querySelectorAll('.subpage');
    pages.forEach(function(page) {
        page.classList.remove('active');
    });

    // Remove active class from all nav links
    var links = document.querySelectorAll('.subnav a');
    links.forEach(function(link) {
        link.classList.remove('active-link');
    });

    // Show the clicked subpage
    var selectedPage = document.getElementById(pageId);
    if (selectedPage) {
        selectedPage.classList.add('active');
    }

    // Add active class to clicked nav link
    var activeLink = document.getElementById(pageId + 'Link');
    if (activeLink) {
        activeLink.classList.add('active-link');
    }
}

// Default: Show Privacy Policy page on initial load (only if on terms page)
if (document.querySelector('.subpage')) {
    showPage('privacy');
}

// ============================================
// CLOSE SIDEBAR ON ESCAPE KEY
// ============================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            if (overlay) {
                overlay.classList.remove('active');
            }
        }
    }
});

// ============================================
// CLOSE MOBILE MENU ON WINDOW RESIZE
// ============================================
window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
        const nav = document.getElementById('main-nav');
        if (nav) {
            nav.classList.remove('show');
            const menuIcon = document.getElementById('menuIcon');
            if (menuIcon) {
                menuIcon.className = 'fas fa-bars';
            }
        }
    }
});

// ============================================
// CART FUNCTIONS (for cart page)
// ============================================
// Update Quantity
function updateQuantity(button, change) {
    const item = button.closest('.cart-item');
    if (item) {
        const quantitySpan = item.querySelector('.quantity');
        if (quantitySpan) {
            let quantity = parseInt(quantitySpan.textContent) + change;
            if (quantity < 1) quantity = 1;
            quantitySpan.textContent = quantity;
            updateTotal();
        }
    }
}

// Remove Item
function removeItem(button) {
    if (confirm('Remove this item from cart?')) {
        const item = button.closest('.cart-item');
        if (item) {
            item.remove();
            updateTotal();
            checkEmptyCart();
        }
    }
}

// Update Total
function updateTotal() {
    const items = document.querySelectorAll('.cart-item');
    let total = 0;
    items.forEach(item => {
        const priceText = item.querySelector('.cart-item-price');
        const quantitySpan = item.querySelector('.quantity');
        if (priceText && quantitySpan) {
            const price = parseFloat(priceText.textContent.replace('Ksh ', '').replace(/,/g, ''));
            const quantity = parseInt(quantitySpan.textContent);
            total += price * quantity;
        }
    });
    const totalElement = document.getElementById('cart-total');
    if (totalElement) {
        totalElement.textContent = total.toLocaleString();
    }
}

// Check Empty Cart
function checkEmptyCart() {
    const items = document.querySelectorAll('.cart-item');
    const cartSection = document.querySelector('.cart');
    if (items.length === 0 && cartSection) {
        cartSection.innerHTML = `
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h2>Your cart is empty</h2>
                <p>Looks like you haven't added any items yet.</p>
                <a href="shop.html" class="shop-now">Start Shopping</a>
            </div>
        `;
    }
}

// Checkout
function checkout() {
    const total = document.getElementById('cart-total');
    if (total) {
        alert(`Thank you for shopping with WittyMart!\nTotal: KES ${total.textContent}\nYour order has been placed successfully.`);
    }
}

// ============================================
// CONTACT FORM
// ============================================
function handleContactForm(event) {
    event.preventDefault();
    const name = document.getElementById('name');
    const email = document.getElementById('email');
    const message = document.getElementById('message');
    const status = document.getElementById('form-status');

    if (name && email && message && name.value && email.value && message.value) {
        if (status) {
            status.className = 'form-status success';
            status.textContent = '✅ Thank you, ' + name.value + '! Your message has been sent successfully. We\'ll get back to you soon.';
        }
        name.value = '';
        email.value = '';
        message.value = '';
    } else {
        if (status) {
            status.className = 'form-status error';
            status.textContent = '❌ Please fill in all fields.';
        }
    }
    return false;
}

// ============================================
// FAQ TOGGLE (for about page)
// ============================================
function toggleFAQ(button) {
    const answer = button.nextElementSibling;
    const isOpen = answer ? answer.classList.contains('open') : false;
    
    // Close all FAQ answers
    document.querySelectorAll('.faq-answer').forEach(item => {
        item.classList.remove('open');
    });
    document.querySelectorAll('.faq-question').forEach(item => {
        item.classList.remove('active');
    });
    
    // Toggle the clicked one
    if (!isOpen && answer) {
        answer.classList.add('open');
        button.classList.add('active');
    }
}

// ============================================
// SHOP - ADD TO CART (for shop page)
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function() {
            const product = this.getAttribute('data-product');
            if (product) {
                alert(product + ' added to cart!');
            }
        });
    });
});
