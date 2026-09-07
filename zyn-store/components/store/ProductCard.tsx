"use client";

import { Plus } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import type { StoreCopy } from "@/content/store-copy";
import { formatCurrency } from "@/lib/order";
import { canOrderProduct, getTrackedStockLimit } from "@/lib/product";
import type { Product } from "@/types/store";

import { ProductArtwork } from "./ProductArtwork";
import styles from "./storefront.module.css";

type ProductCardProps = {
  product: Product;
  copy: StoreCopy;
  onAdd: (product: Product) => void;
  onOpen: (product: Product) => void;
  onBuyNow: (product: Product) => void;
};

export function ProductCard({ product, copy, onAdd, onOpen, onBuyNow }: ProductCardProps) {
  const isSample = product.status === "sample";
  const canOrder = canOrderProduct(product);
  const trackedStockLimit = getTrackedStockLimit(product);
  const [added, setAdded] = useState(false);
  const feedbackTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const statusLabel = isSample
    ? copy.sample
    : canOrder
      ? copy.available
      : copy.notOrderable;
  const stockLabel = canOrder
    ? trackedStockLimit === null
      ? copy.inStock
      : `${trackedStockLimit} ${copy.stockLeft}`
    : copy.notOrderable;

  useEffect(
    () => () => {
      if (feedbackTimer.current) clearTimeout(feedbackTimer.current);
    },
    [],
  );

  const addProduct = () => {
    if (!canOrder) return;
    onAdd(product);
    setAdded(true);
    if (feedbackTimer.current) clearTimeout(feedbackTimer.current);
    feedbackTimer.current = setTimeout(() => setAdded(false), 1300);
  };

  return (
    <article className={`${styles.productCard} ${isSample ? styles.sampleCard : ""}`}>
      <button
        className={styles.productVisual}
        type="button"
        onClick={() => onOpen(product)}
        aria-label={`${copy.details}: ${product.name}`}
      >
        <span className={styles.statusBadge}>{statusLabel}</span>
        <ProductArtwork product={product} size={250} />
      </button>
      <div className={styles.productInfo}>
        <button
          className={styles.productNameButton}
          type="button"
          onClick={() => onOpen(product)}
          aria-label={`${copy.details}: ${product.name}`}
        >
          {product.name}
        </button>
        <div className={styles.productMeta}>
          <span>
            {copy.strength}: {product.strengthMg} mg
          </span>
          <span>{stockLabel}</span>
        </div>
        <div className={styles.productPrice}>
          <strong>{formatCurrency(product.price)}</strong>
        </div>
        <div className={styles.productActions}>
          <button
            type="button"
            onClick={addProduct}
            disabled={!canOrder}
            title={!canOrder ? copy.notOrderable : undefined}
          >
            {added ? copy.added : copy.add} <Plus size={13} aria-hidden="true" />
          </button>
          <button
            className={styles.buyButton}
            type="button"
            onClick={() => onBuyNow(product)}
            disabled={!canOrder}
            title={!canOrder ? copy.notOrderable : undefined}
          >
            {copy.buy}
          </button>
        </div>
      </div>
    </article>
  );
}
