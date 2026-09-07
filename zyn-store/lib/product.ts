import type { Product } from "@/types/store";

export function getTrackedStockLimit(product: Product) {
  if (product.trackStock !== true || typeof product.stockQuantity !== "number") {
    return null;
  }

  return Math.max(0, product.stockQuantity);
}

export function canOrderProduct(product: Product) {
  const trackedStockLimit = getTrackedStockLimit(product);
  return product.isOrderable && (trackedStockLimit === null || trackedStockLimit > 0);
}

export function clampOrderQuantity(product: Product, quantity: number) {
  const safeQuantity = Math.max(1, Math.floor(quantity));
  const trackedStockLimit = getTrackedStockLimit(product);

  if (trackedStockLimit === null) {
    return safeQuantity;
  }

  return Math.min(safeQuantity, Math.max(1, trackedStockLimit));
}
