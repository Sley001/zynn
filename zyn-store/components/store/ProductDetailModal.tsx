"use client";

import { ArrowLeft, Minus, Plus } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import type { StoreCopy } from "@/content/store-copy";
import { formatCurrency } from "@/lib/order";
import { canOrderProduct, clampOrderQuantity, getTrackedStockLimit } from "@/lib/product";
import type { Product } from "@/types/store";

import { ProductArtwork } from "./ProductArtwork";
import styles from "./storefront.module.css";

type ProductDetailModalProps = {
  product: Product | null;
  copy: StoreCopy;
  onClose: () => void;
  onAdd: (product: Product, quantity?: number) => void;
  onBuyNow: (product: Product, quantity?: number) => void;
};

export function ProductDetailModal({
  product,
  copy,
  onClose,
  onAdd,
  onBuyNow,
}: ProductDetailModalProps) {
  if (!product) return null;

  return (
    <ProductDetailContent
      key={product.id}
      product={product}
      copy={copy}
      onClose={onClose}
      onAdd={onAdd}
      onBuyNow={onBuyNow}
    />
  );
}

function ProductDetailContent({
  product,
  copy,
  onClose,
  onAdd,
  onBuyNow,
}: Omit<ProductDetailModalProps, "product"> & { product: Product }) {
  const [quantity, setQuantity] = useState(() => clampOrderQuantity(product, 1));
  const [added, setAdded] = useState(false);
  const feedbackTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(
    () => () => {
      if (feedbackTimer.current) clearTimeout(feedbackTimer.current);
    },
    [],
  );

  const canOrder = canOrderProduct(product);
  const trackedStockLimit = getTrackedStockLimit(product);
  const canDecrease = quantity > 1;
  const canIncrease = trackedStockLimit === null || quantity < trackedStockLimit;
  const stockLabel = canOrder
    ? trackedStockLimit === null
      ? copy.inStock
      : `${trackedStockLimit} ${copy.stockLeft}`
    : copy.notOrderable;

  const updateQuantity = (change: number) => {
    setQuantity((currentQuantity) => clampOrderQuantity(product, currentQuantity + change));
  };

  const addSelection = () => {
    if (!canOrder) return;
    onAdd(product, quantity);
    setAdded(true);
    if (feedbackTimer.current) clearTimeout(feedbackTimer.current);
    feedbackTimer.current = setTimeout(() => setAdded(false), 1300);
  };

  return (
    <div className={styles.modalLayer} role="presentation">
      <button className={styles.modalBackdrop} type="button" onClick={onClose} aria-label={copy.close} />
      <section
        className={styles.productModal}
        role="dialog"
        aria-modal="true"
        aria-labelledby="product-detail-title"
      >
        <button className={styles.detailBackButton} type="button" onClick={onClose}>
          <ArrowLeft size={25} aria-hidden="true" />
          <span>Back</span>
        </button>
        <div className={styles.detailVisual}>
          <span className={styles.statusBadge}>{stockLabel}</span>
          <ProductArtwork product={product} size={300} open />
        </div>
        <div className={styles.detailCopy}>
          <h2 id="product-detail-title">{product.name}</h2>
          <p className={styles.detailDescription}>{product.description}</p>
          <dl className={styles.detailFacts}>
            <div>
              <dt>{copy.strength}</dt>
              <dd>{product.strengthMg} mg</dd>
            </div>
            <div>
              <dt>{copy.origin}</dt>
              <dd>{product.origin}</dd>
            </div>
            <div>
              <dt>{copy.releaseTime}</dt>
              <dd>{product.duration}</dd>
            </div>
            <div>
              <dt>{copy.total}</dt>
              <dd>{formatCurrency(product.price)}</dd>
            </div>
            <div>
              <dt>{copy.available}</dt>
              <dd>{stockLabel}</dd>
            </div>
          </dl>
          <div className={styles.noteBlock}>
            <h3>{copy.tastingNotes}</h3>
            <div>
              {product.notes.map((note) => (
                <span key={note}>{note}</span>
              ))}
            </div>
          </div>
          <div className={styles.detailPurchase}>
            <div className={styles.quantityControl} aria-label={copy.quantity}>
              <button
                type="button"
                onClick={() => updateQuantity(-1)}
                disabled={!canDecrease}
                aria-label={copy.decreaseQuantity}
              >
                <Minus size={14} aria-hidden="true" />
              </button>
              <span>{quantity}</span>
              <button
                type="button"
                onClick={() => updateQuantity(1)}
                disabled={!canIncrease}
                aria-label={copy.increaseQuantity}
              >
                <Plus size={14} aria-hidden="true" />
              </button>
            </div>
            <strong>{formatCurrency(product.price * quantity)}</strong>
          </div>
          <div className={styles.detailActions}>
            <button
              type="button"
              disabled={!canOrder}
              title={!canOrder ? copy.notOrderable : undefined}
              onClick={addSelection}
            >
              {added ? copy.added : copy.addToCart}
            </button>
            <button
              className={styles.primaryButton}
              type="button"
              disabled={!canOrder}
              title={!canOrder ? copy.notOrderable : undefined}
              onClick={() => {
                onBuyNow(product, quantity);
                onClose();
              }}
            >
              {copy.orderNow}
            </button>
          </div>
        </div>
      </section>
    </div>
  );
}
