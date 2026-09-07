# Receipt scanning and Telegram-only payment verification

Customers pay with a saved exact-total QR when one is available, or with https://link.payway.com.kh/ABAPAYtr5100589 for USD checkouts. The application does not call the ABA Pay API and does not add undocumented amount parameters to the link. Customers must check the recipient and enter the checkout total shown by the store.

## Verification flow

1. The customer chooses or takes a clear payment receipt photo. English and Khmer OCR reads it in the browser and submits the claim automatically. On an ABA transaction-detail receipt, the numeric `Purchase #` is used as the Telegram `Trx. ID`; `Reference #`, the Khmer transaction-code field, APV, account digits, and unlabelled numbers are ignored. There is no transaction-number field, manual-entry option, or separate submission button. If the scan is unclear, the customer must choose or take a clearer photo.
2. The backend stores the decoded JPEG, PNG, or WebP privately. A hidden correlation value from the scan is used only to connect the receipt to an authenticated Telegram alert; it is not returned in the checkout or admin-review API. This step does not create an order, mark a payment paid, or deduct inventory.
3. The Telegram worker accepts payment evidence only when the message comes from the configured payment group and configured ABA sender ID. Forwarded messages, administrator-pasted `/paid` text, administrator replies, wrong groups, and wrong senders cannot verify payment.
4. A paid order is created only when one unconsumed receipt claim has the exact Telegram transaction ID, its server-calculated amount and currency match the authenticated alert, and the Telegram message time falls inside that checkout's payment window. Matching works whether the receipt claim or Telegram alert arrives first.
5. The seller can inspect or reject a pending claim. Electronic payment status and transaction IDs are read-only in the admin order editor. The Telegram shipping buttons change fulfillment review state after payment verification; they do not create payment verification.

Each normalized claimed transaction ID is unique. Duplicate receipt claims are rejected, duplicate Telegram messages and ABA transaction IDs are idempotent, and equal amounts never choose a checkout by FIFO, timestamp, or customer name. Old alerts cannot verify a new checkout. Claims without an uploaded receipt are not eligible for automatic matching. Pending claims remain available while waiting for the matching Telegram alert and cannot be cancelled by the customer.

## API

- `POST /api/checkout/sessions/{token}/claim-paid`: multipart form with `transaction_reference` and `receipt`. A new claim requires a decoded JPEG, PNG, or WebP up to 5 MiB, 6000 pixels per side, and 16 megapixels. Accepted evidence is immutable.
- `GET /api/checkout/sessions/{token}/status`: returns the waiting or matched state. A matched response contains safe order and paid-payment details.
- `GET /api/admin/checkout/sessions/{token}/receipt`: authenticated admin-only receipt response with private, no-store, nosniff, and sandbox headers.
- `GET /api/admin/payment-reviews?page=1`: authenticated admin queue for unmatched receipt claims.
- `POST /api/admin/checkout/sessions/{token}/reject`: rejects an invalid or duplicate pending claim with a reason.
- `POST /api/telegram/payments/webhook`: receives Telegram updates when webhook mode is enabled and requires the configured Telegram webhook secret.

There is no admin endpoint for approving an electronic payment. Direct order creation accepts cash on delivery only; QR and ABA Pay orders must use the checkout session, receipt scan, and authenticated Telegram matching flow.

## Deployment

Deploy the Laravel backend and apply these migrations before releasing the frontend:

- `2026_09_07_000001_add_manual_payment_verification.php` adds the historical claimed transaction field. Its manual verification table is retained for migration compatibility and is no longer used.
- `2026_09_07_000002_add_payment_receipts_to_checkout_sessions.php` adds private receipt storage.
- `2026_09_07_000003_make_claimed_transaction_references_unique.php` normalizes existing references and enforces one checkout claim per transaction ID.

PHP needs GD with JPEG, PNG, and WebP support. PHP and the reverse proxy must allow a 5 MiB image plus multipart overhead. `zyn-backend/public/.user.ini` configures PHP-FPM/CGI. For Windows development, use `zyn-backend/serve.ps1`, because PHP's CLI server otherwise uses its own upload limits. Restart long-running Telegram pollers and queue workers after deployment while preserving the polling offset.

OCR assets are served locally from `public/ocr/v7`. Run `npm run prepare:ocr-assets` after installing dependencies; it also runs before development and production builds. Receipt images are not sent to OpenAI, ABA, or a third-party OCR service. Scanning times out after 90 seconds and never marks a payment paid.

A Sites frontend deployment does not deploy the Laravel backend. Production must set a reachable HTTPS backend URL and the backend must have the correct Telegram group ID, ABA sender ID, merchant name, bot token, and webhook secret or polling process. In BotFather, enable **Bot-to-Bot Communication Mode** for the receiving Website Order Bot. The receiving bot must also be a payment-group administrator or have Group Privacy disabled, otherwise Telegram will not deliver unmentioned messages from the PayWay bot. `TELEGRAM_PAYMENT_MATCH_GRACE_MINUTES` defaults to 15 minutes beyond checkout expiry for delayed alert delivery.

## Verification coverage

Backend tests cover authenticated source checks, pasted and replied alert rejection, exact transaction/amount/currency matching, alert-first and claim-first matching, duplicate protection, private receipt access, corrupt uploads, rollback, and route authorization. Parser tests cover labeled ABA alert text, ABA detail-receipt `Purchase #` extraction, conflict rejection, and excluded receipt fields. The browser OCR integration test uses a synthetic labeled receipt, and the supplied ABA photo was also checked through the local OCR pipeline. Live bank transfers are outside the automated test suite.
