import { STORE_CONFIG } from "@/config/store";
import type { CheckoutSessionOrder } from "@/lib/api";

function formatAmount(value: number, currency: string) {
  return new Intl.NumberFormat(STORE_CONFIG.locale, {
    style: "currency",
    currency,
  }).format(value);
}

export function buildTelegramPaymentConfirmationMessage(order: CheckoutSessionOrder) {
  const items = order.items
    .map(
      (item) =>
        `${item.productName} ${item.strengthMg} mg x ${item.quantity} - ${formatAmount(
          item.lineTotal,
          order.currency,
        )}`,
    )
    .join("\n");

  return [
    "សួស្តី ខ្ញុំបានបង់ប្រាក់រួច ✅",
    "",
    `Order: #${order.orderNumber}`,
    `Customer: ${order.customer.name}`,
    `Phone: ${order.customer.phone}`,
    "",
    "Items:",
    items,
    "",
    `Total: ${formatAmount(order.total, order.currency)}`,
    "Payment: ABA PayWay — Paid",
    "",
    "Delivery:",
    `${order.delivery.province}, ${order.delivery.district}`,
    order.delivery.addressNote || "—",
    "",
    "សូមបញ្ជាក់ការកម្ម៉ង់របស់ខ្ញុំ។",
  ].join("\n");
}

export function buildTelegramPaymentConfirmationUrl(order: CheckoutSessionOrder) {
  return `https://t.me/${STORE_CONFIG.telegramUsername}?text=${encodeURIComponent(
    buildTelegramPaymentConfirmationMessage(order),
  )}`;
}
