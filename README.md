# Alasne Platform

Alasne is a custom PHP digital-commerce platform for multi-store e-commerce, CMS workflows, Mission Control operations, dropshipping automation, customer accounts, order tracking, returns/RMA, refunds, store credit, and future integrations.

## Current Platform

The current branch includes the custom PHP framework, PDO database layer, routing and dependency injection, authentication and permissions, Mission Control administration, multi-store catalog management, customers and orders, inventory ledger, storefront/cart/checkout, customer account access, public order tracking, returns/RMA, restocking/refunds, SEO endpoints, responsive/accessibility work, and production-readiness tooling.

## Local Development

Local URL:

```text
http://alasne.net.local
```

Project path on the current XAMPP workstation:

```text
C:\xampp\htdocs\alasne.net
```

### Setup

1. Install PHP/Apache/MySQL (the current development environment uses XAMPP).
2. Install Composer.
3. Copy `.env.example` to `.env` and configure local values.
4. Install dependencies:

```bash
composer install
composer dump-autoload -o
```

5. Run database migrations:

```bash
php alasne migrate
```

6. Point the web-server document root at the project's `public` directory.

## Runtime Baseline

Alasne declares PHP 8.1+ and the required PDO/MySQL, mbstring, OpenSSL, and JSON extensions in `composer.json`. Additional extensions such as GD/WebP, fileinfo, cURL, and ZIP are recommended for image, upload, integration, and archive workflows.

Use this command after installing dependencies:

```bash
composer check-platform-reqs
```

## Production Deployment

Do not deploy using the local `.env` file. Production configuration, HTTPS/session hardening, mail/queue settings, proxy rules, database practices, carrier/supplier credentials, scheduler guidance, backup requirements, and launch verification are documented in:

```text
PRODUCTION-DEPLOYMENT.md
```

Real `.env` files and credentials are intentionally excluded from Git. `.env.example` contains names and safe placeholders only.
