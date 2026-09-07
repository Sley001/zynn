export type ProductStatus = "available" | "sample" | "draft";
export type OrderStatus =
  | "pending"
  | "confirmed"
  | "processing"
  | "shipped"
  | "completed"
  | "cancelled";
export type AdminPaymentStatus = "pending" | "paid" | "failed" | "refunded";
export type AdminPaymentMethod = "khqr" | "payway" | "cod";
export type AdminTelegramPaymentAlertStatus =
  | "received"
  | "matched"
  | "unmatched"
  | "rejected"
  | "duplicate"
  | "needs_review";

export type AdminUser = {
  id: number;
  name: string;
  email: string;
};

export type AdminProduct = {
  id: number;
  slug: string;
  name: string;
  description: string | null;
  strength_mg: number;
  price: number;
  price_cents?: number;
  color: string | null;
  accent: string | null;
  origin: string | null;
  release_duration: string | null;
  notes: string[];
  image_url: string | null;
  status: ProductStatus;
  is_orderable: boolean;
  stock_quantity?: number | null;
  track_stock?: boolean | null;
  is_active?: boolean | null;
  sort_order?: number | null;
  created_at?: string | null;
  updated_at?: string | null;
};

export type AdminStoreSettings = {
  currency: string;
  deliveryFee: number;
  deliveryFeeCents: number;
  checkoutSessionLifetimeMinutes: number;
};

export type AdminPaymentQr = {
  id: number;
  amount: string;
  amount_cents: number;
  currency: string;
  image_url: string;
  is_active: boolean;
  admin_note: string | null;
  created_at?: string | null;
  updated_at?: string | null;
};

export type AdminOrderItem = {
  id: number;
  product_id: number;
  product_name: string;
  strength_mg: number;
  unit_price: number;
  unit_price_cents: number;
  quantity: number;
  line_total: number;
  line_total_cents: number;
};

export type AdminOrder = {
  order_number: string;
  status: OrderStatus;
  customer: {
    name: string;
    phone: string;
    telegram_username: string | null;
  };
  delivery: {
    province: string;
    district: string;
    address_note: string | null;
  };
  payment: {
    method: AdminPaymentMethod;
    status?: AdminPaymentStatus | null;
    transaction_reference?: string | null;
    paid_at?: string | null;
    source?: "telegram_aba_alert" | null;
    detected_at?: string | null;
  };
  telegram_payment_alert?: {
    status: AdminTelegramPaymentAlertStatus;
    payer_name: string | null;
    masked_account_digits: string | null;
    transaction_id: string | null;
    apv: string | null;
    displayed_paid_at: string | null;
    processed_at: string | null;
    merchant_name: string | null;
  } | null;
  subtotal: number;
  subtotal_cents: number;
  delivery_fee: number;
  delivery_fee_cents: number;
  total: number;
  total_cents: number;
  items: AdminOrderItem[];
  admin_note?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
};

export type AdminTelegramPaymentAlert = {
  id: number;
  status: AdminTelegramPaymentAlertStatus;
  amount: string | null;
  amount_cents: number | null;
  currency: string | null;
  payer_name: string | null;
  masked_account_digits: string | null;
  displayed_paid_at: string | null;
  payment_method: string | null;
  merchant_name: string | null;
  transaction_id: string | null;
  apv: string | null;
  telegram_chat_id: number;
  telegram_message_id: number;
  failure_reason: string | null;
  matched_checkout_session_id: number | null;
  order?: {
    order_number: string;
    status: OrderStatus;
  } | null;
  processed_at: string | null;
  created_at: string | null;
};

export type DashboardSummary = {
  totalProducts: number;
  orderableProducts: number;
  lowStockCount: number;
  totalOrders: number;
  openOrders: number;
  paidRevenue: number;
  pendingRevenue: number;
};

export const PRODUCT_STATUS_OPTIONS: readonly ProductStatus[] = ["available", "sample", "draft"];

export const ORDER_STATUS_OPTIONS: readonly OrderStatus[] = [
  "pending",
  "confirmed",
  "processing",
  "shipped",
  "completed",
  "cancelled",
];

export const PAYMENT_STATUS_OPTIONS: readonly AdminPaymentStatus[] = [
  "pending",
  "paid",
  "failed",
  "refunded",
];
