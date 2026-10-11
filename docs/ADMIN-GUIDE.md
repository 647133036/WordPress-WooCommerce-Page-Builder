# NaijaMart Commerce — Admin Handover Guide

WordPress + WooCommerce storefront with dual B2C/B2B pricing, local payments, cross-selling and lead capture.

## Access

| Item | Value |
|------|-------|
| Site URL | http://localhost:8080 |
| WP Admin | http://localhost:8080/wp-admin |
| Admin user | `admin` / `Admin@12345` |
| Retail test account | `retail@example.com` / `Retail@123` |
| Wholesale test account | `wholesale@example.com` / `Wholesale@123` (approved) |

Database: MariaDB `wordpress` / user `wpuser` / pass `wp_pass_local` (local dev only — change before production).

## Installed Stack

| Purpose | Plugin |
|---|---|
| E-commerce | WooCommerce |
| Page builder | Elementor (free) + Hello Elementor theme |
| B2B pricing | WooCommerce Wholesale Prices (WWPP) — role `wholesale_customer` |
| B2B registration/approval + MOQ | Custom plugin `naijamart-b2b` |
| Card payments | Paystack for WooCommerce, Flutterwave WooCommerce |
| Bank transfer | Built-in WooCommerce BACS (configured with demo GTBank details) |
| Cross-selling | WPC Frequently Bought Together |
| Smart search | FiboSearch (AJAX autocomplete, typo tolerance) |
| Newsletter | Newsletter plugin (standalone) + coupon `WELCOME5000` |
| Currency switcher | FOX / WOOCS (NGN default, USD at fixed rate) |

## Common Admin Tasks

### Add a product (with wholesale price)
1. Products > Add New. Set name, description, image, category.
2. Set the **Regular price** (this is the RRP shown to guests/retail).
3. Scroll to the **Wholesale Prices** box in Product Data and set the **Wholesale Customer Price**.
4. Publish. Guests see RRP; logged-in approved wholesale users see the wholesale price automatically.

### Approve a wholesale application
1. Users > All Users — look for the **Wholesale** column showing "Pending review".
2. Open the user, review Business Name / CAC / Phone in the *Wholesale Application* section.
3. Tick **Approve Wholesale Access** and save. The user is switched to the Wholesale Customer role and sees wholesale pricing on next login.
4. You are emailed at the admin address whenever a new application arrives.

### Update stock
Products > All Products > use the inline **Stock** field, or edit the product > Inventory tab. Out-of-stock items are hidden automatically (per WooCommerce settings).

### Manage orders
WooCommerce > Orders. Bank transfer orders stay **On hold** until you confirm payment, then set to **Processing**. Paystack orders auto-confirm via webhook once keys are configured.

### Activate Paystack (card payments)
1. WooCommerce > Settings > Payments > Paystack > Manage.
2. Enter test/live public and secret keys from your Paystack dashboard.
3. Enable the gateway and save. (Currently disabled — placeholder config only.)

### Frequently Bought Together
Edit a product > Product Data > Linked Products / "Bought Together" tab > search and add matching products. They render on the product page and in the side cart.

### Currency rates
WooCommerce > Currency Switcher tab. NGN is the base (etalon) currency; update the USD fixed rate there.

## Store Rules in Effect

- Wholesale minimum order subtotal: **₦100,000** (enforced at checkout for `wholesale_customer` role). Change the constant in `wp-content/plugins/naijamart-b2b/naijamart-b2b.php`.
- Guest checkout enabled; account creation offered at checkout and on My Account.
- Newsletter subscribers receive coupon `WELCOME5000` (₦5,000 off first order, single use per user).

## Testing Checklist (acceptance)

- [x] Guest sees RRP prices store-wide
- [x] Approved wholesale login reveals discounted prices (grid, product page, checkout)
- [x] Wholesale checkout below ₦100,000 is blocked with a clear notice
- [x] Guest checkout end-to-end with bank transfer (order created, On hold)
- [x] Product pages show Frequently Bought Together carousel
- [x] Currency switcher converts NGN to USD
- [x] Newsletter form renders on Home and Contact pages
- [ ] Paystack live/test keys entered (needs merchant account)
- [ ] PageSpeed optimization pass (WP Rocket / LiteSpeed Cache on production host)

## Notes for Production

- Replace all demo credentials and the demo bank account details.
- Purchase Elementor Pro if you want the theme builder / mega-menu templates; free Elementor is installed.
- Add Cloudflare, a caching plugin (WP Rocket or LiteSpeed Cache) and Wordfence before launch to hit the 85%+ PageSpeed target.
- For AI content/SEO assistance, install Rank Math (free) and enable its Content AI module.
