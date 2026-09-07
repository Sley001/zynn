import { STORE_CONFIG } from "@/config/store";
import type {
  CartItem,
  CustomerDetails,
  DeliveryDetails,
  PaymentMethod,
} from "@/types/store";

export function formatCurrency(value: number) {
  return new Intl.NumberFormat(STORE_CONFIG.locale, {
    style: "currency",
    currency: STORE_CONFIG.currency,
  }).format(value);
}

type OrderMessageInput = {
  items: OrderMessageItem[];
  deliveryFee?: number;
  total: number;
  customer: CustomerDetails;
  delivery: DeliveryDetails;
  paymentMethod: PaymentMethod;
  orderNumber?: string;
};

export type OrderMessageItem = {
  name: string;
  strengthMg: number;
  quantity: number;
  lineTotal: number;
};

export function orderMessageItemsFromCart(items: CartItem[]): OrderMessageItem[] {
  return items.map(({ product, quantity }) => ({
    name: product.name,
    strengthMg: product.strengthMg,
    quantity,
    lineTotal: product.price * quantity,
  }));
}

export function buildOrderMessage({
  items,
  deliveryFee = 0,
  total,
  customer,
  delivery,
  paymentMethod,
  orderNumber,
}: OrderMessageInput) {
  const lines = items.map(
    ({ name, strengthMg, quantity, lineTotal }) =>
      `• ${name} ${strengthMg} mg × ${quantity} — ${formatCurrency(lineTotal)}`,
  );
  const paymentLabel =
    paymentMethod === "payway"
      ? "ABA PayWay"
      : paymentMethod.toUpperCase();

  return [
    "ZYN RESERVE — NEW ORDER",
    "",
    ...(orderNumber ? [`Order: ${orderNumber}`, ""] : []),
    ...lines,
    "",
    `Delivery fee: ${formatCurrency(deliveryFee)}`,
    `Total: ${formatCurrency(total)}`,
    `Payment: ${paymentLabel}`,
    "",
    `Name: ${customer.fullName}`,
    `Phone: ${customer.phone}`,
    `Delivery: ${delivery.district}, ${delivery.province}`,
    `Note: ${delivery.note || "—"}`,
    "",
    `I confirm that I am ${STORE_CONFIG.minAge}+ and currently use nicotine.`,
  ].join("\n");
}

export function createTelegramOrderUrl(message: string) {
  return `https://t.me/${STORE_CONFIG.telegramUsername}?text=${encodeURIComponent(message)}`;
}
