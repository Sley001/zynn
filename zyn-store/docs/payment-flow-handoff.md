# ZYN Store Payment Flow Handoff

## Part 1 Scope

Part 1 implements reusable fixed-amount QR storage, admin QR management, server-calculated checkout sessions, and customer-side QR display. It does not implement Telegram message ingestion, payment matching, order finalization, or a final Paid UI.

## Schema

`store_settings`

- `id`
- `key`
- `value`
- timestamps

`payment_qrs`

- `id`
- `amount` decimal(12, 2)
- `amount_cents`
- `currency`
- `image_path`
- `is_active`
- `admin_note`
- timestamps

The database enforces one active QR for a given `amount_cents` and `currency` using a partial unique index.

`checkout_sessions`

- `id`
- public UUID `token`
- `customer_snapshot`
- `cart_snapshot`
- `subtotal_cents`
- `delivery_fee_cents`
- `total_cents`
- `currency`
- `payment_method`
- `status`
- `payment_qr_id`
- `qr_image_path`
- `payment_claimed_at`
- `expires_at`
- `consumed_at`
- `resulting_order_id`
- timestamps

Indexes exist for token, status, exact total/currency, expiration, and created time.

## Routes

Public:

- `GET /api/store-settings`
- `POST /api/checkout/sessions`
- `GET /api/checkout/sessions/{token}/status`
- `POST /api/checkout/sessions/{token}/claim-paid`

Admin:

- `GET /api/admin/store-settings`
- `PATCH /api/admin/store-settings`
- `GET /api/admin/payment-qrs`
- `POST /api/admin/payment-qrs`
- `GET /api/admin/payment-qrs/{paymentQr}`
- `PATCH /api/admin/payment-qrs/{paymentQr}`
- `DELETE /api/admin/payment-qrs/{paymentQr}`

Legacy:

- `POST /api/orders` remains for existing direct-order behavior but the storefront checkout now uses checkout sessions.

## Services

- `CartPricingService` reloads products from PostgreSQL, validates active/orderable stock state, and calculates subtotal, delivery fee, total, and line snapshots from trusted server data.
- `StoreSettingsService` exposes configurable store currency, delivery fee, checkout session lifetime, and prune retention.
- `PaymentQrService` creates, replaces, activates/deactivates, and deletes reusable QR records while preserving image files referenced by active checkout sessions.
- `CheckoutSessionService` creates temporary checkout sessions, finds the exact active QR by total/currency, records customer “I HAVE PAID” claims without marking payment paid, and expires/prunes abandoned sessions.
- `Money` normalizes decimal amounts to integer cents and back without using floats for backend persistence.

## Environment Variables

- `STORE_CURRENCY`
- `STORE_DELIVERY_FEE_CENTS`
- `CHECKOUT_SESSION_LIFETIME_MINUTES`
- `CHECKOUT_SESSION_PRUNE_AFTER_MINUTES`

No PayWay merchant credentials are required for Part 1.

## Files Changed

- `app/layout.tsx`
- `config/store.ts`
- `content/store-copy.ts`
- `hooks/use-cart.ts`
- `hooks/use-store-settings.ts`
- `lib/api.ts`
- `lib/order.ts`
- `types/store.ts`
- `components/store/Storefront.tsx`
- `components/store/CartDrawer.tsx`
- `components/store/CheckoutForm.tsx`
- `components/store/storefront.module.css`
- `components/admin/AdminDashboard.tsx`
- `components/admin/OrdersPanel.tsx`
- `components/admin/PaymentQrsPanel.tsx`
- `components/admin/admin-api.ts`
- `components/admin/admin.module.css`
- `components/admin/types.ts`
- `zyn-backend/.env.example`
- `zyn-backend/app/Enums/CheckoutSessionStatus.php`
- `zyn-backend/app/Exceptions/PaymentQrUnavailableException.php`
- `zyn-backend/app/Http/Controllers/Api/CheckoutSessionController.php`
- `zyn-backend/app/Http/Controllers/Api/StoreSettingController.php`
- `zyn-backend/app/Http/Controllers/Api/Admin/PaymentQrController.php`
- `zyn-backend/app/Http/Controllers/Api/Admin/StoreSettingController.php`
- `zyn-backend/app/Http/Requests/ClaimCheckoutSessionPaidRequest.php`
- `zyn-backend/app/Http/Requests/StoreCheckoutSessionRequest.php`
- `zyn-backend/app/Http/Requests/StorePaymentQrRequest.php`
- `zyn-backend/app/Http/Requests/UpdatePaymentQrRequest.php`
- `zyn-backend/app/Http/Requests/UpdateStoreSettingsRequest.php`
- `zyn-backend/app/Http/Resources/CheckoutSessionResource.php`
- `zyn-backend/app/Http/Resources/PaymentQrResource.php`
- `zyn-backend/app/Http/Resources/OrderResource.php`
- `zyn-backend/app/Models/CheckoutSession.php`
- `zyn-backend/app/Models/Order.php`
- `zyn-backend/app/Models/PaymentQr.php`
- `zyn-backend/app/Models/StoreSetting.php`
- `zyn-backend/app/Providers/AppServiceProvider.php`
- `zyn-backend/app/Services/CartPricingService.php`
- `zyn-backend/app/Services/CheckoutSessionService.php`
- `zyn-backend/app/Services/OrderService.php`
- `zyn-backend/app/Services/PaymentQrService.php`
- `zyn-backend/app/Services/StoreSettingsService.php`
- `zyn-backend/app/Support/Money.php`
- `zyn-backend/config/store.php`
- `zyn-backend/database/factories/CheckoutSessionFactory.php`
- `zyn-backend/database/factories/PaymentQrFactory.php`
- `zyn-backend/database/migrations/2026_09_01_000001_add_delivery_fee_to_orders_table.php`
- `zyn-backend/database/migrations/2026_09_01_000010_create_store_settings_table.php`
- `zyn-backend/database/migrations/2026_09_01_000020_create_payment_qrs_table.php`
- `zyn-backend/database/migrations/2026_09_01_000030_create_checkout_sessions_table.php`
- `zyn-backend/routes/api.php`
- `zyn-backend/routes/console.php`
- `zyn-backend/tests/Feature/CheckoutSessionApiTest.php`
- `zyn-backend/tests/Feature/OrderApiTest.php`

## Tests Run

- `php -l` on the new PHP services, controllers, requests, resources, models, migrations, factories, routes, and tests: passed.
- `php artisan migrate --force`: passed.
- `php artisan migrate:status`: new migrations are marked `Ran`.
- `php artisan route:list --path=api`: passed.
- `npm run lint`: passed.
- `node_modules\.bin\vinext.cmd build`: passed.
- `node --test tests\rendered-html.test.mjs`: passed.
- `npm run build`: blocked by Windows Bash/WSL access on this host.
- `php artisan test`: blocked before assertions because local PHP lacks `pdo_sqlite`, while `phpunit.xml` uses SQLite `:memory:`.

## Part 2 Scope

Part 2 implements Telegram-detected ABA payment ingestion through the Website Order Bot. It does not use ABA PayWay API credentials, does not invite customers into the private payment group, and does not redesign the customer QR UI.

The bot receives ABA transaction-notification messages in the private group. A valid, verified ABA alert consumes exactly one oldest unexpired checkout session with the same exact total and currency, then creates one real paid order and one payment.

## Part 2 Schema

`telegram_payment_alerts`

- Telegram update/chat/message/from/sender IDs
- Telegram message sent time and local received time
- raw ABA payment message for private admin audit
- parsed amount, currency, payer name, masked account digits, displayed paid time, method, merchant, transaction ID, and APV
- status: `received`, `matched`, `unmatched`, `rejected`, `duplicate`, `needs_review`
- matched checkout session ID
- created order ID
- created payment ID
- duplicate source alert ID
- processed timestamp, failure reason, metadata

Database uniqueness protects:

- `telegram_chat_id` plus `telegram_message_id`
- `transaction_id`

Duplicate alerts with a repeated ABA transaction ID are stored as `duplicate` without writing the repeated ID into the unique `transaction_id` column; the repeated parsed ID is retained in alert metadata.

## Part 2 Routes

Public Telegram webhook:

- `POST /api/telegram/payments/webhook`

The webhook route requires `TELEGRAM_MODE=webhook` and validates Telegram’s `X-Telegram-Bot-Api-Secret-Token` header against `TELEGRAM_WEBHOOK_SECRET`. It dispatches `ProcessTelegramPaymentUpdate` so production can return quickly while queue workers process payment matching idempotently.

## Part 2 Commands

- `php artisan telegram:payments:poll --once`
- `php artisan telegram:payments:poll`
- `php artisan telegram:diagnose-updates --limit=5 --timeout=0`
- `php artisan checkout-sessions:prune`

Use polling only for local development with `TELEGRAM_MODE=polling`. Use the webhook only in production with `TELEGRAM_MODE=webhook`. Do not run both modes at the same time.

Polling requests `message`, `channel_post`, and `callback_query` updates. The processor accepts normal Telegram messages and channel posts, and ignores edited updates.

Because PayWay sends the bank alert as another Telegram bot, enable **Bot-to-Bot Communication Mode** for the receiving Website Order Bot in BotFather. The receiving bot must also be a group administrator or have Group Privacy disabled so it receives PayWay's unmentioned group messages. Telegram does not replay messages that were missed before this setting was enabled; test with a fresh checkout and payment.

The diagnostic command prints only safe fields:

- `chat.id`
- `from.id`
- `sender_chat.id`
- `message_id`
- update/message type

It never prints the bot token or full payment text.

## Telegram Environment Variables

- `TELEGRAM_BOT_TOKEN`
- `TELEGRAM_PAYMENT_GROUP_ID`
- `TELEGRAM_ABA_SENDER_ID`
- `TELEGRAM_ADMIN_USER_IDS`
- `TELEGRAM_WEBHOOK_SECRET`
- `TELEGRAM_MODE`
- `TELEGRAM_WEBHOOK_URL`
- `TELEGRAM_PAYMENT_MERCHANT_NAME`

`TELEGRAM_PAYMENT_GROUP_ID` must be the private ABA notification group numeric `chat.id`. `TELEGRAM_ABA_SENDER_ID` must be the numeric `from.id` or `sender_chat.id` used by the ABA notification account. Never trust the display name “ABA.”

To discover safe numeric IDs:

1. Add the Website Order Bot to the private payment group.
2. Set `TELEGRAM_BOT_TOKEN` locally.
3. Send one harmless test message in the private group.
4. Run `php artisan telegram:diagnose-updates --limit=5 --timeout=0`.
5. Copy only the numeric IDs into `.env`.

## Parser Format

The parser accepts this ABA alert shape:

`$4.00 paid by TEST CUSTOMER (*770) on Sep 01, 02:11 PM via ABA PAY at LIV SEANGLY. Trx. ID: 123456789012345, APV: 123456.`

Extracted values:

- `$` maps to `USD`
- amount is stored in cents
- payer name
- masked account digits
- displayed payment date/time resolved in `Asia/Phnom_Penh` using Telegram’s message timestamp for the missing year
- payment method
- merchant name
- transaction ID
- APV
- Telegram IDs and timestamps

Malformed alerts from the configured group/sender are stored as `rejected` and never create orders.

## Matching Rules

- Only new `message` updates are processed.
- Edited updates are ignored.
- Forwarded messages are rejected.
- `chat.id` must exactly match `TELEGRAM_PAYMENT_GROUP_ID`.
- `from.id` or `sender_chat.id` must exactly match `TELEGRAM_ABA_SENDER_ID`.
- Merchant name must match `TELEGRAM_PAYMENT_MERCHANT_NAME`.
- Matching is FIFO by oldest checkout session.
- Checkout session status must be `awaiting_payment` or `payment_claimed`.
- Checkout session must be unexpired and unconsumed.
- Alert amount cents must exactly equal `checkout_sessions.total_cents`.
- Alert currency must exactly equal `checkout_sessions.currency`.
- Customer name is never used for matching.

Inside one database transaction, the matcher locks the alert/session, creates the order and order items from the trusted checkout-session snapshot, creates a paid payment with the ABA transaction reference, decrements stock once without going negative, consumes the session, and links alert, session, order, and payment.

If no checkout session matches, the alert becomes `unmatched`, no order is created, and the private group is notified for manual review.

If stock became insufficient after payment, the paid order/payment are retained, stock is not made negative, the alert becomes `needs_review`, and the order admin note calls for urgent manual review before shipping.

## Telegram Admin Actions

Matched-payment notifications sent to the private group include:

- order number
- amount and currency
- customer name and phone from checkout
- product summary
- ABA transaction ID/APV
- status
- inline actions for “Verified for shipping” and “Needs review”

Only numeric Telegram user IDs listed in `TELEGRAM_ADMIN_USER_IDS` can trigger these actions. “Verified for shipping” changes the existing order status to `confirmed`; it does not mark the order shipped. “Needs review” appends an admin note and leaves the order pending.

## Part 2 Files Changed

- `docs/payment-flow-handoff.md`
- `zyn-backend/.env.example`
- `zyn-backend/app/Enums/TelegramPaymentAlertStatus.php`
- `zyn-backend/app/Exceptions/MalformedTelegramPaymentAlertException.php`
- `zyn-backend/app/Http/Controllers/Api/Telegram/PaymentWebhookController.php`
- `zyn-backend/app/Jobs/ProcessTelegramPaymentUpdate.php`
- `zyn-backend/app/Models/CheckoutSession.php`
- `zyn-backend/app/Models/Order.php`
- `zyn-backend/app/Models/TelegramPaymentAlert.php`
- `zyn-backend/app/Providers/AppServiceProvider.php`
- `zyn-backend/app/Services/OrderService.php`
- `zyn-backend/app/Services/TelegramBotClient.php`
- `zyn-backend/app/Services/TelegramPaymentAlertParser.php`
- `zyn-backend/app/Services/TelegramPaymentAlertService.php`
- `zyn-backend/config/telegram.php`
- `zyn-backend/database/migrations/2026_09_01_000040_create_telegram_payment_alerts_table.php`
- `zyn-backend/routes/api.php`
- `zyn-backend/routes/console.php`
- `zyn-backend/tests/Feature/TelegramPaymentAlertProcessingTest.php`
- `zyn-backend/tests/Unit/TelegramPaymentAlertParserTest.php`

## Part 2 Tests Run

- `php -l` on touched PHP files: passed.
- `php artisan route:list --path=api`: passed.
- `php artisan migrate --force`: passed; `telegram_payment_alerts` table created in PostgreSQL.
- `php artisan migrate:status`: new migration is marked `Ran`.
- `php artisan list telegram`: passed; Telegram polling and diagnostic commands are registered.
- `php artisan test --filter=TelegramPaymentAlertParserTest`: passed.
- `php artisan test --testsuite=Unit`: passed.
- Rollback-only PostgreSQL smoke through Laravel Tinker: passed; a synthetic alert created a paid order and decremented stock once inside an outer transaction, then rolled back.
- `vendor\bin\pint.bat --test` on Part 2 touched files: passed.
- `php artisan test --filter=TelegramPaymentAlertProcessingTest`: blocked before assertions because local PHP lacks `pdo_sqlite`, while `phpunit.xml` uses SQLite `:memory:`.
- `php artisan test`: blocked before DB-backed feature assertions for the same missing `pdo_sqlite` driver; unit parser tests passed.
- `npm run lint`: passed.
- `npm run build`: blocked because Windows denied Bash/WSL startup for `scripts/build-verified.sh`.
- `node_modules\.bin\vinext.cmd build`: passed with escalation after the sandbox blocked Vite child-process spawning.
- `node --test tests\rendered-html.test.mjs`: passed with escalation after the sandbox blocked Node child-process spawning.

## Part 3 Scope

Part 3 completes the customer-facing Telegram-detected payment flow. The checkout now creates only a temporary checkout session, shows the stored exact-amount QR for that session, waits for the backend Telegram alert matcher, and displays Paid only after Laravel reports that the session has been matched to a real paid order.

This still does not use ABA PayWay API credentials and does not use the public open-amount PayWay link. Fixed-amount QR images must be uploaded in Admin for each exact order total that can be accepted.

## Part 3 Customer Flow

1. Customer completes checkout and clicks `ORDER NOW`.
2. The button disables immediately while Laravel recalculates product prices, stock, subtotal, delivery fee, and final total from PostgreSQL.
3. Laravel creates a temporary checkout session only if an active QR exists for the exact total and currency.
4. The checkout drawer shows the merchant name, item summary, subtotal, delivery fee, final total, QR image, expiration countdown, and `I HAVE PAID`.
5. `I HAVE PAID` records only the customer claim. It does not create an order, reduce stock, clear the cart, or display Paid.
6. The frontend polls `GET /api/checkout/sessions/{token}/status` every few seconds without overlapping requests. Network errors back off and keep the same session visible.
7. When the Telegram alert service matches a verified ABA notification, Laravel creates the paid order/payment atomically, consumes the checkout session, and exposes only safe order/payment details in the status response.
8. The frontend then shows Paid, clears the cart once, builds a Telegram draft from verified backend order data, attempts to open `https://t.me/b_imv?text=<URL_ENCODED_MESSAGE>`, and keeps large fallback buttons for confirm, copy, and open Telegram.

If no exact QR exists, the checkout shows the unavailable total and tells the customer to contact `@b_imv`; it does not fall back to any wrong QR or open-amount link.

## Part 3 Admin Flow

- Admin Orders now displays Telegram payment source, detected time, payer name/masked digits, transaction ID, APV, and alert verification status when available.
- The dashboard includes an unmatched/review payment-alert queue for alerts that need manual attention.
- Admin can mark an order as verified for shipping or needing review.
- Shipping a Telegram-detected order with a non-matched alert requires an explicit strong confirmation flag.

## Part 3 Routes

New in Part 3:

- `POST /api/checkout/sessions/{checkoutSession}/cancel`
- `GET /api/admin/payment-alerts`

Used by the complete flow:

- `POST /api/checkout/sessions`
- `GET /api/checkout/sessions/{checkoutSession}/status`
- `POST /api/checkout/sessions/{checkoutSession}/claim-paid`
- `POST /api/telegram/payments/webhook`
- `GET /api/admin/orders`
- `PATCH /api/admin/orders/{order}`

## Part 3 Files Changed

- `config/store.ts`
- `content/store-copy.ts`
- `lib/api.ts`
- `lib/checkout-session-storage.ts`
- `lib/telegram.ts`
- `components/store/Storefront.tsx`
- `components/store/CartDrawer.tsx`
- `components/store/CheckoutForm.tsx`
- `components/store/storefront.module.css`
- `components/admin/AdminDashboard.tsx`
- `components/admin/OrdersPanel.tsx`
- `components/admin/admin-api.ts`
- `components/admin/admin.module.css`
- `components/admin/types.ts`
- `zyn-backend/app/Enums/CheckoutSessionStatus.php`
- `zyn-backend/app/Exceptions/PaymentQrUnavailableException.php`
- `zyn-backend/app/Http/Controllers/Api/CheckoutSessionController.php`
- `zyn-backend/app/Http/Controllers/Api/Admin/OrderController.php`
- `zyn-backend/app/Http/Controllers/Api/Admin/TelegramPaymentAlertController.php`
- `zyn-backend/app/Http/Requests/CancelCheckoutSessionRequest.php`
- `zyn-backend/app/Http/Requests/UpdateOrderRequest.php`
- `zyn-backend/app/Http/Resources/CheckoutSessionResource.php`
- `zyn-backend/app/Http/Resources/OrderResource.php`
- `zyn-backend/app/Http/Resources/TelegramPaymentAlertResource.php`
- `zyn-backend/app/Services/CheckoutSessionService.php`
- `zyn-backend/routes/api.php`
- `zyn-backend/tests/Feature/CheckoutSessionApiTest.php`
- `zyn-backend/tests/Feature/TelegramPaymentAlertProcessingTest.php`
- `docs/payment-flow-handoff.md`

## Part 3 Tests Run

- `vendor\bin\pint.bat --test` on touched backend PHP files: passed.
- `php artisan route:list --path=api`: passed; 28 API routes registered.
- `php artisan migrate:status`: all current payment-flow migrations are marked `Ran`.
- `php artisan test --testsuite=Unit`: passed.
- `php artisan test`: blocked before DB-backed feature assertions because this PHP install lacks the `pdo_sqlite` driver while `phpunit.xml` uses SQLite `:memory:`.
- `npm run lint`: passed.
- `node --test tests\rendered-html.test.mjs`: passed with escalation after sandbox child-process restrictions.
- `npm run build`: blocked because Windows denied Bash/WSL startup for `scripts/build-verified.sh`.
- `node_modules\.bin\vinext.cmd build`: passed with escalation as the Windows-native production build path.

## Remaining Setup

Required in the real environment, without committing secrets:

- Seed the attached ABA PAY/KHQR fixed-amount images with `php artisan db:seed --class=PaymentQrSeeder`, or upload active fixed-amount QR images in Admin for every exact total/currency the store should accept.
- Set `STORE_DELIVERY_FEE_CENTS=150` if the delivery fee should be `$1.50`.
- Configure `TELEGRAM_BOT_TOKEN`, `TELEGRAM_PAYMENT_GROUP_ID`, `TELEGRAM_ABA_SENDER_ID`, `TELEGRAM_ADMIN_USER_IDS`, `TELEGRAM_WEBHOOK_SECRET`, `TELEGRAM_MODE`, `TELEGRAM_WEBHOOK_URL`, and `TELEGRAM_PAYMENT_MERCHANT_NAME`.
- Use `php artisan telegram:diagnose-updates --limit=5 --timeout=0` to discover numeric Telegram IDs safely.
- Run either Telegram polling locally or the webhook in production, never both at the same time.
- Keep a queue worker running in production so Telegram webhook jobs are processed promptly.
