export type ApiProduct = {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  strength_mg: number;
  price: number;
  price_cents: number;
  color: string;
  accent: string;
  origin: string | null;
  release_duration: string | null;
  notes: string[];
  image_url: string | null;
  status: "available" | "sample";
  is_orderable: boolean;
};

export type CreateOrderPayload = {
  customer_name: string;
  phone: string;
  province: string;
  district: string;
  address_note?: string;
  payment_method: "khqr" | "payway" | "cod";
  telegram_username?: string;
  age_confirmed: true;
  items: Array<{ product_id: number; quantity: number }>;
};

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? "http://127.0.0.1:8000/api";

async function apiRequest<T>(path: string, init?: RequestInit): Promise<T> {
  const response = await fetch(`${API_URL}${path}`, {
    ...init,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...init?.headers,
    },
  });

  const body = await response.json();

  if (!response.ok) {
    const message = body.message ?? "The store API request failed.";
    throw new Error(message);
  }

  return body;
}

export async function getProducts(): Promise<ApiProduct[]> {
  const response = await apiRequest<{ data: ApiProduct[] }>("/products");
  return response.data;
}

export async function createOrder(payload: CreateOrderPayload) {
  return apiRequest<{ data: { order_number: string; total: number }; message: string }>(
    "/orders",
    {
      method: "POST",
      body: JSON.stringify(payload),
    },
  );
}
