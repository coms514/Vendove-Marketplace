# Vendove

A peer-to-peer marketplace where people list items, make offers, post "looking for" requests and chat, with Stripe checkout.

![Screenshot](docs/screenshot.png)

## Features

- Listings with up to 5 photos, cropping, drag-to-reorder and a live preview
- Search, filters, sorting and pagination
- Offers: buyers offer below the asking price, sellers accept or decline, and an accepted offer becomes the checkout price
- Buy requests: buyers post what they want and sellers message them
- One-to-one chat with photos, edit, delete, block and unread badges. Messages are encrypted at rest
- Stripe Payment Element checkout, confirmed on the server and backed up by a webhook
- Email verification, profiles, size preferences and a shipping address

## Tech stack

- PHP 8.1+ with no framework, PDO and MySQL/MariaDB
- Vanilla JS and one CSS file, no build step
- Stripe PHP SDK, PHPMailer, Cropper.js (vendored)
- PHP-CS-Fixer, ESLint and Prettier

## Architecture

Every page is a PHP file in the project root that starts with `require 'bootstrap.php'`. The bootstrap loads `.env`, registers the error handlers, opens the session and pulls in the modules in `src/`. Pages handle their POST at the top, then render into `templates/header.php` and `templates/footer.php`. JSON endpoints for the chat live in `api/` and always reply with `{ success, data, error }`.

Error handling follows one pattern. Pages call `abort($status, $message)` or set a `flash()` and redirect. APIs call `json_error()`. Anything uncaught goes to `storage/logs/php-error.log`, and the user gets a generic 500.

**Auth.** Passwords are hashed with `password_hash`. When someone registers, they get a verification link by email. Only the sha256 of the token is stored. While they wait, the register page polls `api/check_verification.php` and logs them in once the link is clicked. Every POST form and API call carries a CSRF token, and the session id is regenerated on login.

**Listings and requests.** `src/catalog.php` validates input and writes the row and its images in one transaction. Photos are resized in the browser, then checked again on the server by real MIME type, size and pixel count. The server re-encodes them with GD and saves them under random names in `uploads/`, where PHP execution is disabled. If the database write fails, the new files are deleted.

**Offers and payments.** An offer must be at least 25% of the asking price and below it. When the seller accepts, `checkout_quote()` uses the offer amount instead of the listed price. The price is always computed on the server, never taken from the client. Checkout creates a PaymentIntent (reused across refreshes) and the Payment Element confirms it. Stripe then redirects to `checkout_complete.php`, which fetches the intent again and calls `finalize_purchase()`. That function locks the item row and records the transaction, then marks the item sold and rejects any other pending offers. If someone else bought the item first, the payment is refunded. The `payment_intent.succeeded` webhook runs the same function, so a buyer who closes the tab still gets the item, and the idempotency check on `payment_id` stops it from being recorded twice.

**Chat.** Each conversation has its own random AES-256 key. That key is wrapped with `APP_KEY` and stored in `conversation_keys`. Messages are encrypted with AES-256-GCM, and the conversation id is bound in as associated data. The client polls `api/fetch_messages.php` every 3 seconds while the tab is visible and only re-renders when something changed. `scripts/migrate_messages.php` upgrades messages from the old CBC format.

```
bootstrap.php        loads env, errors, session and src/
src/                 domain modules (catalog, offers, payments, conversations, auth, uploads)
api/                 JSON endpoints for chat and badges
templates/           layout and partials
assets/              app.css and one script per feature
database/schema.sql  tables
scripts/             one-off CLI scripts
```

## Local setup

You need PHP 8.1+ with `gd`, `openssl`, `fileinfo` and `pdo_mysql`, plus MySQL or MariaDB and Composer. XAMPP covers these. Turn on `extension=gd` in php.ini if it is off.

```bash
git clone <repo-url> marketplace
cd marketplace
composer install

cp .env.example .env
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"   # paste into APP_KEY

mysql -u root -e "CREATE DATABASE marketplace CHARACTER SET utf8mb4"
mysql -u root marketplace < database/schema.sql
```

Point Apache at the folder (with XAMPP, put it in `htdocs/`) and open `http://localhost/marketplace`. The `.htaccess` blocks `src/`, `templates/`, `storage/` and other private folders, so `AllowOverride All` needs to be on.

If `MAIL_HOST` is empty, verification emails go to `storage/logs/mail.log`, so you can copy the link from there. Categories, brands and sizes start empty. Insert a few rows if you want those filters to show options.

To test payments, use Stripe test keys and card `4242 4242 4242 4242`. For the webhook:

```bash
stripe listen --forward-to localhost/marketplace/stripe_webhook.php
```

Linting:

```bash
composer lint        # php-cs-fixer dry run
npm install && npm run lint
```

## Environment variables

| Variable | Required | Notes |
| --- | --- | --- |
| `APP_NAME` | no | Shown in the header and emails. Default `Vendove` |
| `APP_URL` | no | Base URL for email links. Built from the request if empty |
| `APP_DEBUG` | no | `true` shows exception messages. Keep it `false` in production |
| `APP_TIMEZONE` | no | PHP and MySQL session timezone. Default `UTC` |
| `APP_KEY` | yes | Base64 of 32 random bytes. Wraps the chat keys, so changing it makes old messages unreadable |
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` | yes | Database connection |
| `STRIPE_PUBLISHABLE_KEY`, `STRIPE_SECRET_KEY` | for checkout | Checkout returns 503 until both are set |
| `STRIPE_WEBHOOK_SECRET` | no | Turns on `stripe_webhook.php` |
| `STRIPE_CURRENCY` | no | Default `usd` |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION` | no | SMTP server. Leave `MAIL_HOST` empty to log mail instead |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | no | SMTP login |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | no | Sender |
