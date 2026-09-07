import type { PaymentMethod } from "@/types/store";

export const STORE_CONFIG = {
  name: "ZYN Reserve",
  minAge: 21,
  currency: "USD",
  locale: "en-US",
  deliveryFee: 1.5,
  telegramUsername: "b_imv",
  paymentMerchantName: "LIV SEANGLY",
} as const;

export const PAYMENT_METHODS: readonly {
  id: PaymentMethod;
  label: string;
  description: string;
}[] = [
  /*{
    id: "khqr",
    label: "ABA / KHQR",
    description: "Receive a QR after the seller confirms your order.",
  },*/
  {
    id: "payway",
    label: "ABA / KHQR",
    description: "Pay by QR or ABA link. The seller verifies your bank payment.",
  },
  /*{
    id: "cod",
    label: "Cash on delivery",
    description: "Pay when your order arrives.",
  },*/
];
