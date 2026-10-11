# NaijaMart Commerce

> Version: 0.0.1

A dual-tier (B2C + B2B) e-commerce platform built on **WordPress + WooCommerce + Elementor**, featuring retail/wholesale pricing, local payment gateways (Paystack / Flutterwave / bank transfer), smart product search, cross-selling, and newsletter lead capture.

## Features

- **Dual-Tier Pricing** — Guests and retail customers see RRP; approved wholesale accounts automatically see discounted B2B prices store-wide
- **Wholesale Portal** — B2B registration with business verification (Business Name, CAC/Tax ID), admin approval workflow, and ₦100,000 minimum order enforcement
- **Payments** — Paystack & Flutterwave card processing, plus direct bank transfer (BACS) with manual confirmation
- **Cross-Selling** — "Frequently Bought Together" recommendations on product pages
- **Smart Search** — AJAX autocomplete search with typo tolerance (FiboSearch)
- **Multi-Currency** — NGN primary, USD fixed-rate switching
- **Lead Capture** — Newsletter signup with first-order coupon incentive (`WELCOME5000`)
- **Responsive Design** — Astra-based boxed 1200px layout, mobile-optimized product grids

## Tech Stack

| Layer | Technology |
|---|---|
| CMS | WordPress 6.x |
| E-commerce | WooCommerce |
| Page Builder | Elementor (free) + Astra theme |
| B2B Pricing | WooCommerce Wholesale Prices + custom `naijamart-b2b` plugin |
| Search | FiboSearch |
| Recommendations | WPC Frequently Bought Together |
| Database | MariaDB / MySQL |
| Server | PHP 8.2 |

## Quick Start (Local)

Requirements: PHP 8.0+, MariaDB/MySQL, [WP-CLI](https://wp-cli.org/)

```bash
# Start the database
service mariadb start

# Create database
mysql -e "CREATE DATABASE wordpress CHARACTER SET utf8mb4; CREATE USER 'wpuser'@'localhost' IDENTIFIED BY 'your_pass'; GRANT ALL ON wordpress.* TO 'wpuser'@'localhost';"

# Configure WordPress
wp config create --dbname=wordpress --dbuser=wpuser --dbpass=your_pass

# Serve the site
wp server --host=0.0.0.0 --port=8080
```

Then visit `http://localhost:8080` and log in at `/wp-admin`.

## Project Structure

```
├── wp-content/
│   ├── plugins/
│   │   └── naijamart-b2b/     # Custom B2B plugin: wholesale registration,
│   │                          # approval workflow, MOQ enforcement, brand styles
│   ├── themes/                # Astra (active)
│   └── uploads/               # Product images (not committed)
├── docs/
│   └── ADMIN-GUIDE.md         # Admin handover & operations manual
└── wp-config.php              # Local config (not committed)
```

## Admin Operations

See [docs/ADMIN-GUIDE.md](docs/ADMIN-GUIDE.md) for:

- Adding products with dual pricing
- Approving wholesale applications
- Processing bank transfer orders
- Configuring Paystack keys

## License

This project is open-sourced under the [MIT License](LICENSE).
