import type { OrderMessageItem } from "@/lib/order";
import type { CartItem, CustomerDetails, DeliveryDetails, PaymentMethod, Product } from "@/types/store";

type ApiProduct = {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  strength_mg: number;
  price: number;
  color: string;
  accent: string;
  origin: string | null;
  release_duration: string | null;
  notes: string[];
  image_url: string | null;
  status: "available" | "sample";
  is_orderable: boolean;
  stock_quantity?: number | null;
  track_stock?: boolean | null;
};

type ApiErrorBody = {
  message?: string;
  code?: string;
  errors?: Record<string, string[]>;
  total?: number | string;
  total_cents?: number;
  currency?: string;
};

export type CreateOrderInput = {
  items: CartItem[];
  customer: CustomerDetails;
  delivery: DeliveryDetails;
  paymentMethod: PaymentMethod;
};

export type CreatedOrder = {
  orderNumber: string;
  items: OrderMessageItem[];
  deliveryFee: number;
  total: number;
};

export type StoreSettings = {
  currency: string;
  deliveryFee: number;
  deliveryFeeCents: number;
  checkoutSessionLifetimeMinutes: number;
};

export type CheckoutSessionStatus =
  | "awaiting_payment"
  | "payment_claimed"
  | "matched"
  | "expired"
  | "cancelled";

export type CheckoutSessionItem = {
  productId: number;
  productName: string;
  strengthMg: number;
  unitPrice: number;
  unitPriceCents: number;
  quantity: number;
  lineTotal: number;
  lineTotalCents: number;
};

export type CheckoutSessionOrder = {
  orderNumber: string;
  status: string;
  customer: {
    name: string;
    phone: string;
  };
  delivery: {
    province: string;
    district: string;
    addressNote: string | null;
  };
  payment: {
    status: "paid" | "pending" | "failed" | "refunded" | null;
    paidAt: string | null;
    detectedAt: string | null;
  };
  total: number;
  totalCents: number;
  currency: string;
  items: CheckoutSessionItem[];
};

export type CheckoutSession = {
  token: string;
  status: CheckoutSessionStatus;
  subtotal: number;
  subtotalCents: number;
  deliveryFee: number;
  deliveryFeeCents: number;
  total: number;
  totalCents: number;
  currency: string;
  paymentMethod: PaymentMethod;
  expiresAt: string;
  paymentClaimedAt: string | null;
  paymentLink: string | null;
  items: CheckoutSessionItem[];
  qr: {
    paymentQrId: number | null;
    imageUrl: string;
    amount: number;
    amountCents: number;
    currency: string;
  } | null;
  order: CheckoutSessionOrder | null;
};

export class ApiRequestError extends Error {
  code?: string;
  status: number;
  total?: number;
  totalCents?: number;
  currency?: string;

  constructor(message: string, status: number, code?: string, body?: ApiErrorBody | null) {
    super(message);
    this.name = "ApiRequestError";
    this.status = status;
    this.code = code;
    this.total = body?.total === undefined ? undefined : decimalToNumber(body.total);
    this.totalCents = body?.total_cents;
    this.currency = body?.currency;
  }
}

type PublicEnv = Record<string, string | undefined>;

const nodeEnv: PublicEnv | undefined = typeof process !== "undefined" ? process.env : undefined;
const viteEnv = (import.meta as unknown as { env?: PublicEnv }).env;

export const API_URL = (
  nodeEnv?.NEXT_PUBLIC_API_URL ??
  nodeEnv?.VITE_API_URL ??
  viteEnv?.NEXT_PUBLIC_API_URL ??
  viteEnv?.VITE_API_URL ??
  ""
).replace(/\/+$/, "");

export const isApiConfigured = API_URL.length > 0;

function resolveImageUrl(imageUrl: string | null) {
  if (!imageUrl || imageUrl.startsWith("http://") || imageUrl.startsWith("https://")) {
    return imageUrl;
  }

  try {
    return new URL(imageUrl, API_URL).toString();
  } catch {
    return imageUrl;
  }
}

function toProduct(product: ApiProduct): Product {
  return {
    id: product.id,
    slug: product.slug,
    name: product.name,
    strengthMg: product.strength_mg,
    price: product.price,
    color: product.color,
    accent: product.accent,
    description: product.description ?? "",
    notes: product.notes,
    duration: product.release_duration ?? "—",
    origin: product.origin ?? "—",
    status: product.status,
    imageUrl: resolveImageUrl(product.image_url),
    isOrderable: product.is_orderable,
    stockQuantity: product.stock_quantity,
    trackStock: product.track_stock,
  };
}

function getApiErrorMessage(body: ApiErrorBody | null) {
  const validationMessage = body?.errors ? Object.values(body.errors).flat()[0] : undefined;
  return validationMessage ?? body?.message ?? "The store API request failed.";
}

function decimalToNumber(value: number | string) {
  return typeof value === "number" ? value : Number(value);
}

async function apiRequest<T>(path: string, init?: RequestInit): Promise<T> {
  if (!isApiConfigured) {
    throw new Error("NEXT_PUBLIC_API_URL or VITE_API_URL is not configured.");
  }

  const response = await fetch(`${API_URL}${path}`, {
    ...init,
    headers: {
      Accept: "application/json",
      ...(init?.body instanceof FormData ? {} : { "Content-Type": "application/json" }),
      ...init?.headers,
    },
  });

  const body = (await response.json().catch(() => null)) as (T & ApiErrorBody) | null;

  if (!response.ok) {
    throw new ApiRequestError(getApiErrorMessage(body), response.status, body?.code, body);
  }

  if (!body) {
    throw new Error("The store API returned an empty response.");
  }

  return body;
}

function toStoreSettings(data: {
  currency: string;
  delivery_fee: number | string;
  delivery_fee_cents: number;
  checkout_session_lifetime_minutes: number;
}): StoreSettings {
  return {
    currency: data.currency,
    deliveryFee: decimalToNumber(data.delivery_fee),
    deliveryFeeCents: data.delivery_fee_cents,
    checkoutSessionLifetimeMinutes: data.checkout_session_lifetime_minutes,
  };
}

function toCheckoutSessionItem(data: {
  product_id: number;
  product_name: string;
  strength_mg: number;
  unit_price: number | string;
  unit_price_cents: number;
  quantity: number;
  line_total: number | string;
  line_total_cents: number;
}): CheckoutSessionItem {
  return {
    productId: data.product_id,
    productName: data.product_name,
    strengthMg: data.strength_mg,
    unitPrice: decimalToNumber(data.unit_price),
    unitPriceCents: data.unit_price_cents,
    quantity: data.quantity,
    lineTotal: decimalToNumber(data.line_total),
    lineTotalCents: data.line_total_cents,
  };
}

function toCheckoutSessionOrder(data: {
  order_number: string;
  status: string;
  customer: {
    name: string;
    phone: string;
  };
  delivery: {
    province: string;
    district: string;
    address_note: string | null;
  };
  payment: {
    status: "paid" | "pending" | "failed" | "refunded" | null;
    paid_at: string | null;
    detected_at: string | null;
  };
  total: number | string;
  total_cents: number;
  currency: string;
  items: Parameters<typeof toCheckoutSessionItem>[0][];
}): CheckoutSessionOrder {
  return {
    orderNumber: data.order_number,
    status: data.status,
    customer: data.customer,
    delivery: {
      province: data.delivery.province,
      district: data.delivery.district,
      addressNote: data.delivery.address_note,
    },
    payment: {
      status: data.payment.status,
      paidAt: data.payment.paid_at,
      detectedAt: data.payment.detected_at,
    },
    total: decimalToNumber(data.total),
    totalCents: data.total_cents,
    currency: data.currency,
    items: data.items.map(toCheckoutSessionItem),
  };
}

type ApiCheckoutSession = {
  token: string;
  status: CheckoutSessionStatus;
  subtotal: number | string;
  subtotal_cents: number;
  delivery_fee: number | string;
  delivery_fee_cents: number;
  total: number | string;
  total_cents: number;
  currency: string;
  payment_method: PaymentMethod;
  expires_at: string;
  payment_claimed_at: string | null;
  payment_link?: string | null;
  items?: Parameters<typeof toCheckoutSessionItem>[0][];
  qr: {
    payment_qr_id: number | null;
    image_url: string;
    amount: number | string;
    amount_cents: number;
    currency: string;
  } | null;
  order?: Parameters<typeof toCheckoutSessionOrder>[0] | null;
};

function toCheckoutSession(data: ApiCheckoutSession): CheckoutSession {
  return {
    token: data.token,
    status: data.status,
    subtotal: decimalToNumber(data.subtotal),
    subtotalCents: data.subtotal_cents,
    deliveryFee: decimalToNumber(data.delivery_fee),
    deliveryFeeCents: data.delivery_fee_cents,
    total: decimalToNumber(data.total),
    totalCents: data.total_cents,
    currency: data.currency,
    paymentMethod: data.payment_method,
    expiresAt: data.expires_at,
    paymentClaimedAt: data.payment_claimed_at,
    paymentLink: data.payment_link === "https://link.payway.com.kh/ABAPAYtr5100589" ? data.payment_link : null,
    items: (data.items ?? []).map(toCheckoutSessionItem),
    qr: data.qr
      ? {
          paymentQrId: data.qr.payment_qr_id,
          imageUrl: data.qr.image_url,
          amount: decimalToNumber(data.qr.amount),
          amountCents: data.qr.amount_cents,
          currency: data.qr.currency,
        }
      : null,
    order: data.order ? toCheckoutSessionOrder(data.order) : null,
  };
}

export async function getProducts(signal?: AbortSignal): Promise<Product[]> {
  const response = await apiRequest<{ data: ApiProduct[] }>("/products", {
    cache: "no-store",
    signal,
  });

  return response.data.map(toProduct);
}

export async function getStoreSettings(signal?: AbortSignal): Promise<StoreSettings> {
  const response = await apiRequest<{
    data: {
      currency: string;
      delivery_fee: number | string;
      delivery_fee_cents: number;
      checkout_session_lifetime_minutes: number;
    };
  }>("/store-settings", {
    cache: "no-store",
    signal,
  });

  return toStoreSettings(response.data);
}

export async function createCheckoutSession({
  items,
  customer,
  delivery,
  paymentMethod,
}: CreateOrderInput): Promise<CheckoutSession> {
  const response = await apiRequest<{
    data: ApiCheckoutSession;
  }>("/checkout/sessions", {
    method: "POST",
    body: JSON.stringify({
      customer_name: customer.fullName,
      phone: customer.phone,
      province: delivery.province,
      district: delivery.district,
      address_note: delivery.note || undefined,
      payment_method: paymentMethod,
      age_confirmed: true,
      items: items.map(({ product, quantity }) => ({
        product_id: product.id,
        quantity,
      })),
    }),
  });

  return toCheckoutSession(response.data);
}

export async function cancelCheckoutSession(token: string): Promise<CheckoutSession> {
  const response = await apiRequest<{
    data: ApiCheckoutSession;
  }>(`/checkout/sessions/${encodeURIComponent(token)}/cancel`, {
    method: "POST",
  });

  return toCheckoutSession(response.data);
}

export async function getCheckoutSessionStatus(
  token: string,
  signal?: AbortSignal,
): Promise<CheckoutSession> {
  const response = await apiRequest<{
    data: ApiCheckoutSession;
  }>(`/checkout/sessions/${encodeURIComponent(token)}/status`, {
    cache: "no-store",
    signal,
  });

  return toCheckoutSession(response.data);
}

export async function claimCheckoutSessionPaid(token: string, transactionReference: string, receipt: File): Promise<CheckoutSession> {
  const formData = new FormData();
  formData.append("transaction_reference", transactionReference.trim());
  formData.append("receipt", receipt);
  const response = await apiRequest<{
    data: ApiCheckoutSession;
  }>(`/checkout/sessions/${encodeURIComponent(token)}/claim-paid`, {
    method: "POST",
    body: formData,
  });

  return toCheckoutSession(response.data);
}

export async function createOrder({
  items,
  customer,
  delivery,
  paymentMethod,
}: CreateOrderInput): Promise<CreatedOrder> {
  const response = await apiRequest<{
    data: {
      order_number: string;
      items?: {
        product_name: string;
        strength_mg: number;
        quantity: number;
        line_total: number;
      }[];
      delivery_fee: number;
      total: number;
    };
  }>("/orders", {
    method: "POST",
    body: JSON.stringify({
      customer_name: customer.fullName,
      phone: customer.phone,
      province: delivery.province,
      district: delivery.district,
      address_note: delivery.note || undefined,
      payment_method: paymentMethod,
      age_confirmed: true,
      items: items.map(({ product, quantity }) => ({
        product_id: product.id,
        quantity,
      })),
    }),
  });

  return {
    orderNumber: response.data.order_number,
    items: (response.data.items ?? []).map((item) => ({
      name: item.product_name,
      strengthMg: item.strength_mg,
      quantity: item.quantity,
      lineTotal: item.line_total,
    })),
    deliveryFee: response.data.delivery_fee,
    total: response.data.total,
  };
}
