# Connect the existing storefront

Do not point the public storefront at `localhost`. First run the Laravel API
locally for development or deploy it to an HTTPS domain.

For local frontend development, add:

```env
NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api
VITE_API_URL=http://127.0.0.1:8000/api
```

For production, use the deployed HTTPS API URL:

```env
NEXT_PUBLIC_API_URL=https://api.your-domain.com/api
```

Copy `frontend-example/zyn-api.ts` into the frontend and use:

```ts
const products = await getProducts();
const order = await createOrder(payload);
```

The frontend should send only `product_id` and `quantity`. Laravel is the source
of truth for product price, stock, order total, and payment status.

Add the exact frontend domains to `CORS_ALLOWED_ORIGINS` in the Laravel `.env`,
separated by commas.
