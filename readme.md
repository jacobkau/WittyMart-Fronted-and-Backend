# WittyMart

> **Smart Shopping for Witty Minds!**

A full-featured e-commerce platform built with PHP, PostgreSQL, and Cloudinary, powering product management, cart, checkout, M-Pesa payments, admin dashboards, and more.

---

## Table of Contents

- [Overview](#overview)
- [Tech Stack](#tech-stack)
- [Features](#features)
- [Project Structure](#project-structure)
- [Requirements](#requirements)
- [Installation](#installation)
- [Environment Variables](#environment-variables)
- [Database Setup](#database-setup)
- [Running Locally](#running-locally)
- [Deployment (Render)](#deployment-render)
- [Admin Panel](#admin-panel)
- [Payment Integration (M-Pesa)](#payment-integration-m-pesa)
- [Email Integration (EmailJS)](#email-integration-emailjs)
- [Image Storage (Cloudinary)](#image-storage-cloudinary)
- [Forms (Formspree)](#forms-formspree)
- [Activity Logging](#activity-logging)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

---

## Overview

WittyMart is a modern e-commerce platform tailored for the Kenyan market. It supports:

- **Customer-facing storefront** — browse, search, wishlist, cart, checkout, order tracking
- **Admin dashboard** — products, orders, customers, suppliers, coupons, M-Pesa statements, activity logs
- **Full M-Pesa STK Push integration** via the Safaricom Daraja API
- **Cloudinary image hosting** with local fallback
- **Server-side email notifications** via the EmailJS REST API
- **Contact and newsletter forms** with DB persistence and Formspree delivery
- **Comprehensive activity logging** for every meaningful action

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.x (procedural + PDO) |
| Database | PostgreSQL (hosted on Render) |
| Frontend | Vanilla HTML, CSS, JavaScript (no framework) |
| Image CDN | Cloudinary |
| Payments | Safaricom M-Pesa Daraja (STK Push + Callbacks) |
| Email | EmailJS (server-side REST) |
| Forms | Formspree |
| Hosting | Render (web service + managed Postgres) |
| Composer packages | `cloudinary/cloudinary_php` |

---

## Features

### Customer Storefront

- Home page with hero slider, featured deals, testimonials
- Category-browsable shop with live search
- Product detail page with image gallery and lightbox zoom
- Wishlist (add / remove / toggle)
- Shopping cart with quantity controls, coupon codes, transport fee calculator
- Delivery address book with default and pickup options
- Multi-step checkout with Pay on Delivery, M-Pesa (STK Push), and Paybill
- Order confirmation page with real-time payment polling
- Order tracking timeline
- Downloadable PDF invoice
- Contact form and newsletter subscription
- Dark / light theme toggle
- Fully responsive (mobile-first design)

### Authentication

- User registration (username, name, phone, email, password)
- Login / logout with session management
- Password strength validation
- Failed-login tracking

### Admin Panel

- **Dashboard** — stats and recent activity
- **Products** — full CRUD with multi-image gallery, Cloudinary uploads, SKU generator, supplier linking, live search, filters, sorting, pagination
- **Categories** — manage product categories
- **Suppliers** — manage suppliers with contact info
- **Orders** — view, filter, update status, delete, view details in modal
- **Customers** — view user accounts
- **Coupons** — create and manage discount codes
- **M-Pesa Statements** — reconcile payments, retry STK push, mark paid or failed, export CSV
- **Newsletter** — manage subscribers, toggle status, export CSV
- **Contact Messages** — full inbox with mark-read and delete
- **Notifications** — aggregated view across contact, newsletter, agent, login, and orders
- **Activity Logs** — searchable, filterable audit trail with CSV export and print
- **Featured Products** — pin products to the home page
- **Slider Images** — manage the hero slider

### Payments (M-Pesa)

- STK Push integration with Safaricom Daraja
- Automatic callback handling (`mpesa_callback.php`)
- Order status flips to `paid` on success
- Failed payments saved for retry
- Admin can manually mark paid with a receipt number

### Email Notifications

- Server-side order confirmation emails via EmailJS
- Newsletter and contact forms forwarded to Formspree
- Fully HTML-rendered emails with product images

---

## Project Structure

```
wittymart/
├── admin/                          # Admin panel (protected by requireAdmin)
│   ├── activity_logs.php
│   ├── contact_messages.php
│   ├── dashboard.php
│   ├── header.php
│   ├── manage_products.php
│   ├── mpesa_statements.php
│   ├── newsletter.php
│   ├── notifications.php
│   ├── orders.php
│   ├── sidebar.php
│   ├── suppliers.php
│   └── ...
├── includes/
│   ├── ajax.php                    # Shared AJAX endpoints
│   ├── cloudinary_helper.php       # Cloudinary upload/delete helpers
│   ├── config.php                  # DB, session, auth, logging, helpers
│   ├── emailjs.php                 # Server-side EmailJS mailer
│   ├── invoice_pdf.php             # PDF generation
│   └── mpesa_service.php           # Safaricom Daraja API wrapper
├── images/                         # Static assets (logos, icons)
├── uploads/                        # Local image fallbacks
│   └── products/
├── vendor/                         # Composer dependencies
├── about.php
├── cart.php
├── checkout.php
├── contact-submit.php
├── contact.php
├── footer.php
├── header.php
├── home.php                        # Register and login page
├── index.php
├── logout.php
├── mpesa_callback.php              # Safaricom callback handler
├── order_confirmation.php
├── orders.php
├── product.php
├── receipt.php
├── shop.php
├── sidebar.php
├── subscribe.php
├── terms.php
├── track_order.php
├── welcome.php
├── wishlist.php
├── style.css
├── script.js
├── admin.css
├── homestyle.css
├── install.php                     # Database installer
├── composer.json
└── README.md
```

---

## Requirements

- PHP 8.0 or higher (with PDO, cURL, mbstring extensions)
- PostgreSQL 13 or higher
- Composer (for Cloudinary SDK)
- Node / npm — not required
- Modern browser — Chrome, Firefox, Safari, Edge

---

## Installation

### 1. Clone the repository

```bash
git clone https://github.com/your-username/wittymart.git
cd wittymart
```

### 2. Install Composer dependencies

```bash
composer install
```

If you don't have a `composer.json` yet, create one:

```bash
composer require cloudinary/cloudinary_php
```

### 3. Configure environment variables

Copy `.env.example` to `.env` (or set them in your hosting dashboard):

```bash
cp .env.example .env
```

See [Environment Variables](#environment-variables) below for the full list.

### 4. Run the database installer

Create and Visit `https://your-domain/install.php` once to create tables and seed default data.

**Delete `install.php` after running it in production.**

---

## Environment Variables

Set these in your hosting environment (Render dashboard → Environment tab):

### Required

| Variable | Description | Example |
|---|---|---|
| `DATABASE_URL` | Postgres connection string | `postgres://user:pass@host:5432/db` |

### Cloudinary (image hosting)

| Variable | Description |
|---|---|
| `CLOUDINARY_CLOUD_NAME` | Your Cloudinary cloud name |
| `CLOUDINARY_API_KEY` | Cloudinary API key |
| `CLOUDINARY_API_SECRET` | Cloudinary API secret |

### M-Pesa (Daraja API)

| Variable | Description |
|---|---|
| `MPESA_CONSUMER_KEY` | Safaricom Daraja consumer key |
| `MPESA_CONSUMER_SECRET` | Safaricom Daraja consumer secret |
| `MPESA_SHORTCODE` | Business shortcode (Paybill or Till) |
| `MPESA_PASSKEY` | Lipa Na M-Pesa passkey |
| `MPESA_CALLBACK_URL` | Public HTTPS URL, e.g. `https://wittymart.onrender.com/mpesa_callback.php` |
| `MPESA_ENVIRONMENT` | `sandbox` or `production` |

### EmailJS (server-side email)

| Variable | Description |
|---|---|
| `EMAILJS_PUBLIC_KEY` | EmailJS public key |
| `EMAILJS_PRIVATE_KEY` | EmailJS private key (recommended for server-side) |
| `EMAILJS_SERVICE_ID` | EmailJS service ID |
| `EMAILJS_TEMPLATE_ID` | EmailJS template ID |

### Formspree (forms)

| Variable | Description |
|---|---|
| `FORMSPREE_FORM_ID` | Default Formspree form ID |
| `CONTACT_FORMSPREE_ID` | (Optional) Separate form ID for contact only |

---

## Database Setup

### 1. Create a PostgreSQL database

On Render: **New → PostgreSQL**, then copy the **Internal Database URL** into `DATABASE_URL`.

### 2. Run the installer

Visit `https://your-domain/install.php`.

The installer creates the following tables if missing:

| Table | Purpose |
|---|---|
| `users` | Customer and admin accounts |
| `products` | Product catalog |
| `product_images` | Multi-image gallery per product |
| `categories` | Product categories |
| `suppliers` | Supplier records |
| `cart` | Per-user cart items |
| `cart_items` | Guest cart items (session-based) |
| `wishlist` | User wishlists |
| `orders` | Order headers |
| `order_items` | Order line items |
| `user_addresses` | Customer delivery addresses |
| `coupons` | Discount codes |
| `coupon_usages` | Coupon redemption log |
| `newsletter_subscribers` | Newsletter signups |
| `contact_us` | Contact form submissions |
| `activity_logs` | System-wide audit trail |
| `featured_products` | Home-page pinned products |
| `slider_images` | Hero slider images |
| `testimonials` | Customer reviews |
| `reviews` | Product reviews |
| `settings` | Key/value app settings |

### 3. Delete `install.php`

After successful setup, remove it:

```bash
rm install.php
```

---

## Running Locally

If you're developing locally:

```bash
# Start PHP's built-in server
php -S localhost:8000

# Or use a full Apache / Nginx setup via XAMPP, MAMP, Laragon, etc.
```

Then open `http://localhost:8000` in your browser.

**For local testing with a real M-Pesa callback**, use [ngrok](https://ngrok.com):

```bash
ngrok http 8000
# Copy the HTTPS URL into MPESA_CALLBACK_URL
```

---

## Deployment (Render)

### 1. Push to GitHub

```bash
git add .
git commit -m "Initial commit"
git push origin main
```

### 2. Create a Render Web Service

- **New → Web Service → Connect GitHub repo**
- **Runtime**: `PHP`
- **Build Command**: `composer install --no-dev --optimize-autoloader`
- **Start Command**: Render auto-detects; if not, use `php -S 0.0.0.0:$PORT`

### 3. Create a Render Postgres database

- **New → PostgreSQL** (free tier works)
- Copy the **Internal Database URL** and set as `DATABASE_URL` in the web service's environment

### 4. Set all environment variables

Use the [Environment Variables](#environment-variables) table above.

### 5. Deploy

Render auto-deploys on every push to `main`.

### 6. Visit `/install.php` once

To create tables and seed data.

---

## Admin Panel

Access at `https://your-domain/admin/`.

Login with the admin account created during registration. To promote a user to admin, run:

```sql
UPDATE users SET role = 'admin' WHERE email = 'your@email.com';
```

### Admin Sections

| Section | Purpose |
|---|---|
| Dashboard | KPIs, charts, recent activity |
| Products | CRUD, multi-image, Cloudinary, SKU generator |
| Categories | Organize catalog |
| Suppliers | Manage vendors |
| Orders | Status updates, customer info, invoice download |
| Customers | User list with details |
| Coupons | Discount codes with usage limits |
| M-Pesa Statements | Reconcile payments, retry STK, export CSV |
| Newsletter | Subscriber management |
| Contact Messages | Inbox with mark-read and delete |
| Notifications | Aggregated alerts across modules |
| Activity Logs | Full audit trail with filters, CSV export, print |
| Featured Products | Pin items to home page |
| Slider Images | Hero slider content |

---

## Payment Integration (M-Pesa)

### Flow

```
Customer chooses M-Pesa at checkout
    ↓
Order created with payment_status = 'awaiting_payment'
    ↓
STK Push sent to Safaricom (via mpesa_service.php)
    ↓
CheckoutRequestID saved to orders.mpesa_checkout_id
    ↓
Customer enters M-Pesa PIN on their phone
    ↓
Safaricom POSTs callback to mpesa_callback.php
    ↓
Order updated: payment_status = 'paid', mpesa_receipt = 'XXXXX'
    ↓
order_confirmation.php poller detects, shows "Payment Confirmed"
```

### Setup Checklist

- [ ] Daraja app registered at https://developer.safaricom.co.ke
- [ ] Consumer Key, Secret, Passkey, Shortcode obtained
- [ ] Callback URL is public HTTPS (Safaricom can't reach `localhost`)
- [ ] `MPESA_ENVIRONMENT` set to `sandbox` or `production`
- [ ] Tested with a real phone number in sandbox first

### Troubleshooting

| Symptom | Fix |
|---|---|
| `The Public Key is invalid` | Wrong env var name (case-sensitive) |
| `ERR_NAME_NOT_RESOLVED` | Ad blocker or DNS blocking `api.emailjs.com`, see Email section |
| Callback never arrives | Verify `MPESA_CALLBACK_URL` is public, HTTPS, and returns 200 |
| Order stuck on `awaiting_payment` | Check `mpesa_callback.php` logs, the callback isn't matching `mpesa_checkout_id` |

---

## Email Integration (EmailJS)

### Setup

1. Register at [emailjs.com](https://www.emailjs.com)
2. **Email Services** → connect Gmail / Outlook → copy the **Service ID**
3. **Email Templates** → create a template with content `{{{body}}}` (triple braces for raw HTML)
4. **Account → General** → copy **Public Key**
5. **Account → API Keys** → copy **Private Key** (used for server-side calls)

### Server-Side Sender

`includes/emailjs.php` sends emails from the PHP server, bypassing browser DNS, ad blockers, and CORS issues.

```php
$mailer = new EmailJsMailer();
$mailer->send([
    'to_name'      => 'John Doe',
    'to_email'     => 'john@example.com',
    'order_number' => 'ORD-20260101-12345',
    'subject'      => 'Your order is confirmed',
    'body'         => $htmlContent,
]);
```

### Template Requirement

Your EmailJS template **must** use triple braces:

```
{{{body}}}
```

Triple braces tell EmailJS: "don't escape this, it's trusted HTML." Double braces `{{body}}` will show the HTML as visible text.

---

## Image Storage (Cloudinary)

### How Images Are Stored

Each product has:

- `products.image` — local fallback filename (e.g. `1686743812_widget.jpg`)
- `products.image_url` — full Cloudinary URL (used in production)
- `products.image_public_id` — Cloudinary public ID (for deletion)

Additional gallery images live in `product_images` (product_id, image_url, image_public_id, display_order, is_primary).

### Upload Flow

1. Admin uploads image via product form
2. `uploadToCloudinary()` (in `includes/cloudinary_helper.php`) sends it to Cloudinary
3. On success: `image_url` and `image_public_id` saved; local file also kept as fallback
4. On failure: only the local file is saved

### Email Image URLs

Product images in emails use **Cloudinary transformation URLs**:

```
https://res.cloudinary.com/your-cloud/image/upload/w_128,h_128,c_fill,q_auto,f_auto/v.../product.jpg
```

This makes emails load 5 to 10 times faster.

---

## Forms (Formspree)

Two integrations use Formspree:

| Form | Endpoint | Handler |
|---|---|---|
| Newsletter subscribe | `subscribe.php` | Saves to DB and forwards to Formspree |
| Contact form | `contact-submit.php` | Saves to `contact_us` and forwards to Formspree |

### Newsletter Flow

```
Footer form → POST /subscribe.php (JSON: { email })
    ↓
subscribe.php
    ├─→ INSERT INTO newsletter_subscribers
    └─→ cURL POST to Formspree (server-side)
    ↓
Return { success: true }
```

### Contact Flow

```
Contact form → POST /contact-submit.php (JSON: { name, email, message })
    ↓
contact-submit.php
    ├─→ INSERT INTO contact_us
    └─→ cURL POST to Formspree (server-side)
    ↓
Return { success: true }
```

---

## Activity Logging

Every meaningful action is logged to `activity_logs`:

| Action | Triggered by |
|---|---|
| `register` | User registration |
| `login` | Successful login |
| `failed_login` | Wrong password |
| `logout` | Session destroyed |
| `add_to_cart` | Product added to cart |
| `remove_from_cart` | Item removed from cart |
| `clear_cart` | Cart emptied |
| `save_address` | Address added or updated |
| `toggle_wishlist` | Wishlist add or remove |
| `order_placed` | Checkout committed |
| `download_invoice` | Invoice PDF downloaded |
| `add_product` | Admin created product |
| `update_product` | Admin edited product |
| `delete_product` | Admin deleted product |
| `add_supplier` | Admin created supplier |
| `update_order` | Admin changed order status |
| `delete_order` | Admin deleted order |
| `mpesa_mark_paid` | Admin manually marked paid |
| `mpesa_mark_failed` | Admin marked failed |
| `mpesa_retry_stk` | Admin re-sent STK push |
| `newsletter_subscribe` | Newsletter form submitted |
| `delete_subscriber` | Admin deleted subscriber |
| `toggle_subscriber` | Admin toggled status |
| `contact_message` | Contact form submitted |
| `contact_mark_read` | Admin marked read |
| `contact_delete` | Admin deleted message |
| `clear_logs` | Admin cleared old logs |

### Accessing Logs

Admins view them at `/admin/activity_logs.php` with:

- Search by user, action, or date range
- CSV export (respects filters)
- Print / Save as PDF
- Clear old logs (older than N days)

---

## Troubleshooting

### White page / 500 error

Enable error display temporarily in `includes/config.php`:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

Check `/var/log/php_errors.log` or your host's log viewer.

### "Cannot redeclare function X()"

You have the function defined in two places. Search:

```bash
grep -rn "function X" --include="*.php" .
```

Keep only the definition in `includes/config.php` (or your central helpers file). All other files should just `require_once 'includes/config.php'`.

### Database connection error on Render

- Verify `DATABASE_URL` is set (Render Environment tab)
- For Render's managed Postgres, use the **Internal Database URL** (not External)
- Check that SSL is enabled (Render requires it by default)

### Cloudinary uploads fail

- Verify `CLOUDINARY_CLOUD_NAME`, `CLOUDINARY_API_KEY`, `CLOUDINARY_API_SECRET`
- Check the API key hasn't been rotated in your Cloudinary dashboard
- Look for `Cloudinary initialization error:` in the PHP error log

### M-Pesa callback never arrives

- `MPESA_CALLBACK_URL` must be HTTPS, public, and return 200
- Test with `curl`:

  ```bash
  curl -X POST https://your-domain/mpesa_callback.php \
    -H "Content-Type: application/json" \
    -d '{"Body":{"stkCallback":{"CheckoutRequestID":"TEST","ResultCode":0}}}'
  ```

  Expected: `{"ResultCode":0,"ResultDesc":"Accepted"}`

- Check `activity_logs` and `orders.mpesa_checkout_id` to debug the match

### Cart counter not updating

The badge is updated via `/cart.php?action=get_cart_count`. If it's stuck:

1. Open DevTools → Network
2. Look for the request to `cart.php?action=get_cart_count`
3. Check the response, should be `{"success":true,"count":N}`
4. If it errors, check that `includes/config.php` is loaded and `$_SESSION['user_id']` is set

### JavaScript console errors

- `savedTheme has already been declared` — two files declare the same `const`. Rename or wrap in an IIFE
- `toggleMenu is not defined` — the header script was overwritten by `script.js`. Remove duplicate function definitions
- `ERR_NAME_NOT_RESOLVED` — ad blocker or DNS. Whitelist `api.emailjs.com` or switch to server-side sending

---

## Contributing

1. Fork the repo
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Commit your changes: `git commit -m 'Add amazing feature'`
4. Push: `git push origin feature/amazing-feature`
5. Open a Pull Request

### Coding Standards

- **PHP**: PSR-12 where practical
- **SQL**: Always use prepared statements, never concatenate user input
- **JS**: No global variables; use IIFEs or modules
- **CSS**: Mobile-first; use CSS custom properties for theme colors

---

## License

This project is licensed under the **MIT License**, see the [LICENSE](LICENSE) file for details.

```
MIT License

Copyright (c) 2026 WittyMart

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

## Credits

- **Built by**: Witty Highbrow Technologies
- **Contact**: wittyhighbrowtechnologies@gmail.com
- **Phone**: +254 768 374 497
- **Location**: Nairobi, Kenya

---

## Related Resources

- [Safaricom Daraja API Docs](https://developer.safaricom.co.ke/docs)
- [Cloudinary PHP SDK](https://cloudinary.com/documentation/php_integration)
- [EmailJS REST API](https://www.emailjs.com/docs/rest-api/send/)
- [Formspree Docs](https://help.formspree.io/)
- [Render Docs](https://render.com/docs)

---

**If WittyMart helps you, consider giving it a star on GitHub.**
