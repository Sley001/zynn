# ZYN Reserve Cambodia

A responsive, bilingual nicotine-pouch storefront built with Next.js, React,
TypeScript, and Vinext. It connects to the ZYN Store Laravel API for products,
stock-aware ordering, and PostgreSQL order storage. A local catalog remains as a
safe visual fallback when no API URL is configured.

> Adults 21+ only. Nicotine is addictive. This storefront is for current adult
> nicotine users and is not a smoking-cessation service.

## Project structure

```text
app/
  layout.tsx                 Site metadata and root layout
  page.tsx                   Small route entry point
  globals.css                Global reset and accessibility defaults
components/store/
  Storefront.tsx             Page orchestration and overlay state
  StoreHeader.tsx            Header, language switch, cart count
  Hero.tsx                   Main storefront introduction
  ProductGrid.tsx            Catalog section
  ProductCard.tsx            Reusable product card
  ProductArtwork.tsx         API photo with generated-tin fallback
  ProductDetailModal.tsx     Product detail dialog
  CartDrawer.tsx             Cart and checkout flow
  CheckoutForm.tsx           PostgreSQL order submission and Telegram handoff
  DeliverySelector.tsx       Cambodia province/district fields
  AgeGate.tsx                Persistent 21+ confirmation
  Tin.tsx                    Reusable product visual
  StoreFooter.tsx            Legal warning and contact
  storefront.module.css      Scoped responsive styles
config/
  store.ts                   Store identity, Telegram, age, payments
content/
  store-copy.ts              Khmer and English interface text
data/
  products.ts                Local catalog fallback
  cambodia-locations.ts      Delivery locations
hooks/
  use-age-verification.ts    Age-gate persistence
  use-cart.ts                Cart state and totals
  use-products.ts            API catalog loading and fallback state
lib/
  api.ts                     Laravel products and orders client
  order.ts                   Currency and Telegram order formatting
types/
  store.ts                   Shared TypeScript models
```

## Connect Laravel locally on Windows

Keep the backend running in the first terminal:

```powershell
cd C:\ZYN\zyn-store-backend
php artisan serve
```

In the Laravel `.env`, allow the frontend origin:

```env
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:5173,http://127.0.0.1:3000,http://127.0.0.1:5173
```

Then clear cached Laravel configuration:

```powershell
php artisan optimize:clear
```

Copy this frontend to `C:\ZYN\zyn-store-frontend`. Create `.env.local` from
`.env.example` and keep this value:

```env
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api
VITE_API_URL=http://127.0.0.1:8000/api
```

Start the frontend in a second terminal:

```powershell
cd C:\ZYN\zyn-store-frontend
npm install
npm run dev -- --port 3000
```

Open `http://localhost:3000`. Products are loaded from PostgreSQL through
Laravel. Checkout sends only product IDs and quantities; Laravel validates stock,
recalculates prices, stores the order, and returns the final order number.

## Production API

The public frontend cannot connect to `127.0.0.1` on your computer. Deploy the
Laravel backend to an HTTPS domain, then build the frontend with an HTTPS API URL:

```env
NEXT_PUBLIC_API_URL=https://api.your-domain.com/api
```

Add the exact public frontend origin to `CORS_ALLOWED_ORIGINS` in Laravel.

## Add or update a product

After the API is connected, manage products in PostgreSQL through Laravel. The
`data/products.ts` file is only the visual fallback used when no API is configured
or the API cannot be reached. Products with `is_orderable: false` cannot be added
to the cart.

## Store settings

Edit `config/store.ts` to change the Telegram username, minimum age, currency,
or payment choices. Edit `content/store-copy.ts` for Khmer and English wording.

## Commands

- `npm run dev` — local development
- `npm run lint` — code quality checks
- `npm run build` — production build

Node.js 22.13 or newer is required.
