export type Language = "km" | "en";

export type ProductStatus = "available" | "sample";

export type Product = {
  id: number;
  slug: string;
  name: string;
  strengthMg: number;
  price: number;
  color: string;
  accent: string;
  description: string;
  notes: readonly string[];
  duration: string;
  origin: string;
  status: ProductStatus;
  imageUrl: string | null;
  isOrderable: boolean;
  stockQuantity?: number | null;
  trackStock?: boolean | null;
};

export type CartItem = {
  product: Product;
  quantity: number;
};

export type DeliveryDetails = {
  province: string;
  district: string;
  note: string;
};

export type CustomerDetails = {
  fullName: string;
  phone: string;
};

export type PaymentMethod = "khqr" | "payway" | "cod";

export type CheckoutStep = "cart" | "checkout";
