import { API_URL, isApiConfigured } from "@/lib/api";

import type {
  AdminOrder,
  AdminPaymentStatus,
  AdminPaymentQr,
  AdminProduct,
  AdminStoreSettings,
  AdminTelegramPaymentAlert,
  AdminUser,
  OrderStatus,
  ProductStatus,
} from "./types";

type ApiErrorBody = {
  message?: string;
  errors?: Record<string, string[]>;
};

type PaginatedResponse<T> = {
  data: T[];
};

type ResourceResponse<T> = {
  data: T;
};

export type ProductFormPayload = {
  originalSlug?: string;
  name: string;
  slug: string;
  description: string;
  strengthMg: number;
  price: number;
  color: string;
  accent: string;
  origin: string;
  releaseDuration: string;
  notesText: string;
  status: ProductStatus;
  stockQuantity: number;
  trackStock: boolean;
  isActive: boolean;
  sortOrder: number;
  imageFile: File | null;
  removeImage: boolean;
};

export type OrderUpdatePayload = {
  status: OrderStatus;
  payment_status?: AdminPaymentStatus;
  transaction_reference?: string;
  admin_note: string;
  force_ship_without_verified_payment?: boolean;
};

export type PaymentQrFormPayload = {
  id?: number;
  amount: string;
  currency: string;
  imageFile: File | null;
  isActive: boolean;
  adminNote: string;
};

export type StoreSettingsUpdatePayload = {
  deliveryFee?: string;
  checkoutSessionLifetimeMinutes?: number;
};

export class AdminApiError extends Error {
  status: number;

  constructor(message: string, status: number) {
    super(message);
    this.name = "AdminApiError";
    this.status = status;
  }
}

export type PaymentReview = {
  token: string;
  customer: { name: string; phone: string; province: string; district: string; address_note?: string | null };
  items: { product_name: string; quantity: number }[];
  total_cents: number;
  currency: string;
  has_receipt: boolean;
  payment_claimed_at: string;
};

export async function fetchPaymentReviews(token: string, page = 1) {
  return adminRequest<{ data: PaymentReview[]; meta: { current_page: number; last_page: number; total: number } }>(
    token, `/admin/payment-reviews?page=${page}`,
  );
}

export async function rejectCheckoutPayment(token: string, sessionToken: string, reason: string) {
  return adminRequest(token, `/admin/checkout/sessions/${encodeURIComponent(sessionToken)}/reject`, {
    method: "POST", body: JSON.stringify({ reason }),
  });
}

export async function fetchPaymentReceipt(token: string, sessionToken: string, signal?: AbortSignal): Promise<Blob> {
  if (!isApiConfigured) throw new AdminApiError("The admin API is not configured.", 0);
  const response = await fetch(`${API_URL}/admin/checkout/sessions/${encodeURIComponent(sessionToken)}/receipt`, {
    headers: { Accept: "image/jpeg,image/png,image/webp", Authorization: `Bearer ${token}` },
    cache: "no-store", signal,
  });
  if (!response.ok) throw new AdminApiError("The receipt photo could not be loaded.", response.status);
  const blob = await response.blob();
  if (!["image/jpeg", "image/png", "image/webp"].includes(blob.type) || blob.size > 5 * 1024 * 1024) {
    throw new AdminApiError("The receipt photo has an unsupported format.", 422);
  }
  return blob;
}

function getApiErrorMessage(body: ApiErrorBody | null) {
  const validationMessage = body?.errors ? Object.values(body.errors).flat()[0] : undefined;
  return validationMessage ?? body?.message ?? "The admin API request failed.";
}

async function adminRequest<T>(token: string, path: string, init: RequestInit = {}): Promise<T> {
  if (!isApiConfigured) {
    throw new AdminApiError("NEXT_PUBLIC_API_URL is not configured.", 0);
  }

  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");
  headers.set("Authorization", `Bearer ${token}`);

  if (!(init.body instanceof FormData) && init.body) {
    headers.set("Content-Type", "application/json");
  }

  const response = await fetch(`${API_URL}${path}`, {
    ...init,
    headers,
  });
  const body = (await response.json().catch(() => null)) as (T & ApiErrorBody) | null;

  if (!response.ok) {
    throw new AdminApiError(getApiErrorMessage(body), response.status);
  }

  if (!body && response.status !== 204) {
    throw new AdminApiError("The admin API returned an empty response.", response.status);
  }

  return body as T;
}

function appendText(formData: FormData, key: string, value: string | number | boolean) {
  formData.append(key, String(value));
}

function appendNullableText(formData: FormData, key: string, value: string) {
  if (value.trim()) {
    formData.append(key, value.trim());
  }
}

function appendProductPayload(formData: FormData, payload: ProductFormPayload) {
  appendText(formData, "name", payload.name.trim());
  appendText(formData, "slug", payload.slug.trim());
  appendNullableText(formData, "description", payload.description);
  appendText(formData, "strength_mg", payload.strengthMg);
  appendText(formData, "price_cents", Math.round(payload.price * 100));
  appendNullableText(formData, "color", payload.color);
  appendNullableText(formData, "accent", payload.accent);
  appendNullableText(formData, "origin", payload.origin);
  appendNullableText(formData, "release_duration", payload.releaseDuration);
  appendText(formData, "status", payload.status);
  appendText(formData, "stock_quantity", payload.stockQuantity);
  appendText(formData, "track_stock", payload.trackStock ? 1 : 0);
  appendText(formData, "is_active", payload.isActive ? 1 : 0);
  appendText(formData, "sort_order", payload.sortOrder);

  payload.notesText
    .split(/\r?\n|,/)
    .map((note) => note.trim())
    .filter(Boolean)
    .slice(0, 10)
    .forEach((note) => formData.append("notes[]", note));

  if (payload.imageFile) {
    formData.append("image", payload.imageFile);
  }

  if (payload.removeImage) {
    appendText(formData, "remove_image", 1);
  }
}

function toAdminStoreSettings(data: {
  currency: string;
  delivery_fee: number | string;
  delivery_fee_cents: number;
  checkout_session_lifetime_minutes: number;
}): AdminStoreSettings {
  return {
    currency: data.currency,
    deliveryFee: typeof data.delivery_fee === "number" ? data.delivery_fee : Number(data.delivery_fee),
    deliveryFeeCents: data.delivery_fee_cents,
    checkoutSessionLifetimeMinutes: data.checkout_session_lifetime_minutes,
  };
}

function appendPaymentQrPayload(formData: FormData, payload: PaymentQrFormPayload) {
  appendText(formData, "amount", payload.amount);
  appendText(formData, "currency", payload.currency);
  appendText(formData, "is_active", payload.isActive ? 1 : 0);

  if (payload.adminNote.trim()) {
    formData.append("admin_note", payload.adminNote.trim());
  } else {
    formData.append("admin_note", "");
  }

  if (payload.imageFile) {
    formData.append("image", payload.imageFile);
  }
}

export async function loginAdmin(email: string, password: string) {
  if (!isApiConfigured) {
    throw new AdminApiError("NEXT_PUBLIC_API_URL is not configured.", 0);
  }

  const response = await fetch(`${API_URL}/admin/login`, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
    },
    body: JSON.stringify({ email, password }),
  });
  const body = (await response.json().catch(() => null)) as
    | ResourceResponse<{ token: string; token_type: "Bearer"; user: AdminUser }>
    | (ApiErrorBody & Partial<ResourceResponse<{ token: string; token_type: "Bearer"; user: AdminUser }>>)
    | null;

  if (!response.ok || !body?.data?.token) {
    throw new AdminApiError(getApiErrorMessage(body as ApiErrorBody | null), response.status);
  }

  return body.data;
}

export async function logoutAdmin(token: string) {
  await adminRequest<{ message: string }>(token, "/admin/logout", { method: "POST" });
}

export async function fetchAdminProducts(token: string) {
  const response = await adminRequest<PaginatedResponse<AdminProduct>>(
    token,
    "/admin/products?per_page=100",
  );
  return response.data;
}

export async function fetchAdminOrders(token: string) {
  const response = await adminRequest<PaginatedResponse<AdminOrder>>(
    token,
    "/admin/orders?per_page=100",
  );
  return response.data;
}

export async function fetchAdminPaymentQrs(token: string) {
  const response = await adminRequest<PaginatedResponse<AdminPaymentQr>>(
    token,
    "/admin/payment-qrs",
  );
  return response.data;
}

export async function fetchAdminPaymentAlerts(token: string) {
  const response = await adminRequest<PaginatedResponse<AdminTelegramPaymentAlert>>(
    token,
    "/admin/payment-alerts?per_page=100",
  );
  return response.data;
}

export async function fetchAdminStoreSettings(token: string) {
  const response = await adminRequest<ResourceResponse<{
    currency: string;
    delivery_fee: number | string;
    delivery_fee_cents: number;
    checkout_session_lifetime_minutes: number;
  }>>(token, "/admin/store-settings");

  return toAdminStoreSettings(response.data);
}

export async function saveAdminProduct(token: string, payload: ProductFormPayload) {
  const formData = new FormData();
  appendProductPayload(formData, payload);

  const isUpdate = Boolean(payload.originalSlug);
  if (isUpdate) {
    formData.append("_method", "PATCH");
  }

  const path = isUpdate
    ? `/admin/products/${encodeURIComponent(payload.originalSlug ?? "")}`
    : "/admin/products";
  const response = await adminRequest<ResourceResponse<AdminProduct>>(token, path, {
    method: "POST",
    body: formData,
  });

  return response.data;
}

export async function saveAdminPaymentQr(token: string, payload: PaymentQrFormPayload) {
  const formData = new FormData();
  appendPaymentQrPayload(formData, payload);

  if (payload.id) {
    formData.append("_method", "PATCH");
  }

  const path = payload.id
    ? `/admin/payment-qrs/${encodeURIComponent(payload.id)}`
    : "/admin/payment-qrs";
  const response = await adminRequest<ResourceResponse<AdminPaymentQr>>(token, path, {
    method: "POST",
    body: formData,
  });

  return response.data;
}

export async function updateAdminDeliveryFee(token: string, deliveryFee: string) {
  return updateAdminStoreSettings(token, { deliveryFee });
}

export async function updateAdminStoreSettings(token: string, payload: StoreSettingsUpdatePayload) {
  const response = await adminRequest<ResourceResponse<{
    currency: string;
    delivery_fee: number | string;
    delivery_fee_cents: number;
    checkout_session_lifetime_minutes: number;
  }>>(token, "/admin/store-settings", {
    method: "PATCH",
    body: JSON.stringify({
      ...(payload.deliveryFee !== undefined ? { delivery_fee: payload.deliveryFee } : {}),
      ...(payload.checkoutSessionLifetimeMinutes !== undefined
        ? { checkout_session_lifetime_minutes: payload.checkoutSessionLifetimeMinutes }
        : {}),
    }),
  });

  return toAdminStoreSettings(response.data);
}

export async function deleteAdminProduct(token: string, slug: string) {
  await adminRequest<null>(token, `/admin/products/${encodeURIComponent(slug)}`, {
    method: "DELETE",
  });
}

export async function deleteAdminPaymentQr(token: string, id: number) {
  await adminRequest<null>(token, `/admin/payment-qrs/${encodeURIComponent(id)}`, {
    method: "DELETE",
  });
}

export async function updateAdminOrder(
  token: string,
  orderNumber: string,
  payload: OrderUpdatePayload,
) {
  const response = await adminRequest<ResourceResponse<AdminOrder>>(
    token,
    `/admin/orders/${encodeURIComponent(orderNumber)}`,
    {
      method: "PATCH",
      body: JSON.stringify({
        status: payload.status,
        ...(payload.payment_status !== undefined
          ? { payment_status: payload.payment_status }
          : {}),
        ...(payload.transaction_reference !== undefined
          ? { transaction_reference: payload.transaction_reference || null }
          : {}),
        admin_note: payload.admin_note || null,
        force_ship_without_verified_payment: payload.force_ship_without_verified_payment ?? false,
      }),
    },
  );

  return response.data;
}
