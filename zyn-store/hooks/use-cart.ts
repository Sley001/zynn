"use client";

import { useCallback, useEffect, useMemo, useState } from "react";

import { STORE_CONFIG } from "@/config/store";
import { getTrackedStockLimit } from "@/lib/product";
import type { CartItem, Product } from "@/types/store";

export const CART_STORAGE_KEY = "zyn-store-cart";

function loadCart(): CartItem[] {
  if (typeof window === "undefined") return [];

  try {
    const stored = window.localStorage.getItem(CART_STORAGE_KEY);
    return stored ? (JSON.parse(stored) as CartItem[]) : [];
  } catch {
    return [];
  }
}

function clearStoredCart() {
  if (typeof window !== "undefined") {
    window.localStorage.removeItem(CART_STORAGE_KEY);
  }
}

export function useCart(deliveryFeeAmount: number = STORE_CONFIG.deliveryFee) {
  const [items, setItems] = useState<CartItem[]>(loadCart);

  useEffect(() => {
    window.localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(items));
  }, [items]);

  const addItem = useCallback((product: Product, quantity = 1) => {
    setItems((current) => {
      const trackedStockLimit = getTrackedStockLimit(product);
      const requestedQuantity = Math.max(1, Math.floor(quantity));
      if (trackedStockLimit !== null && trackedStockLimit <= 0) return current;

      const existing = current.find((item) => item.product.id === product.id);
      if (!existing) {
        return [
          ...current,
          {
            product,
            quantity:
              trackedStockLimit === null
                ? requestedQuantity
                : Math.min(requestedQuantity, trackedStockLimit),
          },
        ];
      }

      return current.map((item) =>
        item.product.id === product.id
          ? {
              ...item,
              quantity:
                trackedStockLimit === null
                  ? item.quantity + requestedQuantity
                  : Math.min(item.quantity + requestedQuantity, trackedStockLimit),
            }
          : item,
      );
    });
  }, []);

  const updateQuantity = useCallback((productId: number, change: number) => {
    setItems((current) =>
      current
        .map((item) =>
          item.product.id === productId
            ? {
                ...item,
                quantity: (() => {
                  const trackedStockLimit = getTrackedStockLimit(item.product);
                  const nextQuantity = Math.max(0, item.quantity + change);
                  return trackedStockLimit === null
                    ? nextQuantity
                    : Math.min(nextQuantity, trackedStockLimit);
                })(),
              }
            : item,
        )
        .filter((item) => item.quantity > 0),
    );
  }, []);

  const removeItem = useCallback((productId: number) => {
    setItems((current) => current.filter((item) => item.product.id !== productId));
  }, []);

  const clear = useCallback(() => {
    clearStoredCart();
    setItems([]);
  }, []);

  const itemCount = useMemo(
    () => items.reduce((sum, item) => sum + item.quantity, 0),
    [items],
  );
  const subtotal = useMemo(
    () => items.reduce((sum, item) => sum + item.product.price * item.quantity, 0),
    [items],
  );
  const deliveryFee = itemCount > 0 ? deliveryFeeAmount : 0;
  const total = Number((subtotal + deliveryFee).toFixed(2));

  return {
    items,
    itemCount,
    subtotal,
    deliveryFee,
    total,
    addItem,
    updateQuantity,
    removeItem,
    clear,
  };
}
