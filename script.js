// ============================================
// WITTYMART 
// ============================================

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

if (totalTestimonials > 0) {
    setInterval(nextTestimonial, 6000);
}

// ============================================
// NEWSLETTER FORM
// ============================================
const newsletterForm = document.getElementById('newsletter-form');
if (newsletterForm && typeof window.subscribeNewsletter !== 'function') {
    newsletterForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const email = this.querySelector('input[type="email"]');
        if (email && email.value) {
            alert('Thank you for subscribing! You will receive updates from WittyMart.');
            email.value = '';
        }
    });
}

// ============================================
// SHOW PAGE FUNCTION (terms page tabs)
// ============================================
function showPage(pageId) {
    var pages = document.querySelectorAll('.subpage');
    pages.forEach(function (page) {
        page.classList.remove('active');
    });

    var links = document.querySelectorAll('.subnav a');
    links.forEach(function (link) {
        link.classList.remove('active-link');
    });

    var selectedPage = document.getElementById(pageId);
    if (selectedPage) {
        selectedPage.classList.add('active');
    }

    var activeLink = document.getElementById(pageId + 'Link');
    if (activeLink) {
        activeLink.classList.add('active-link');
    }
}

// Show Privacy Policy tab by default on the terms page
if (document.querySelector('.subpage')) {
    showPage('privacy');
}

// ============================================
// CLOSE SIDEBAR ON ESCAPE
// ============================================
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if (sidebar && sidebar.classList.contains('active')) {
            if (typeof window.toggleSidebar === 'function') {
                window.toggleSidebar();
            } else {
                sidebar.classList.remove('active');
                if (overlay) overlay.classList.remove('active');
            }
        }
    }
});

// ============================================
// CART FUNCTIONS 
// ============================================
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

function checkEmptyCart() {
    const items = document.querySelectorAll('.cart-item');
    const cartSection = document.querySelector('.cart');
    if (items.length === 0 && cartSection) {
        cartSection.innerHTML = `
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h2>Your cart is empty</h2>
                <p>Looks like you haven't added any items yet.</p>
                <a href="shop.php" class="shop-now">Start Shopping</a>
            </div>
        `;
    }
}

function checkout() {
    const total = document.getElementById('cart-total');
    if (total) {
        alert(`Thank you for shopping with WittyMart!\nTotal: KES ${total.textContent}\nYour order has been placed successfully.`);
    }
}

// ============================================
// FAQ TOGGLE 
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
// SHOP - LEGACY ADD TO CART FALLBACK
// ============================================
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.add-to-cart[data-product]').forEach(button => {
        button.addEventListener('click', function (e) {
            // Skip if the button also has data-product-id
            // (that means shop.php's AJAX handler owns it)
            if (this.hasAttribute('data-product-id')) return;

            const product = this.getAttribute('data-product');
            if (product) {
                alert(product + ' added to cart!');
            }
        });
    });
});
