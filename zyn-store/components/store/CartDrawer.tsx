import { Minus, Plus, Trash2, X } from "lucide-react";
import { useCallback, useEffect, useRef } from "react";

import type { StoreCopy } from "@/content/store-copy";
import { formatCurrency } from "@/lib/order";
import { getTrackedStockLimit } from "@/lib/product";
import type { CartItem, CheckoutStep, Language } from "@/types/store";

import { CheckoutForm } from "./CheckoutForm";
import { ProductArtwork } from "./ProductArtwork";
import styles from "./storefront.module.css";

type CartDrawerProps = {
  open: boolean;
  step: CheckoutStep;
  copy: StoreCopy;
  language: Language;
  items: CartItem[];
  subtotal: number;
  deliveryFee: number;
  total: number;
  onClose: () => void;
  onClearCart: () => void;
  onStepChange: (step: CheckoutStep) => void;
  onQuantityChange: (productId: number, change: number) => void;
  onRemoveItem: (productId: number) => void;
};

type CloseGuard = () => boolean | Promise<boolean>;

export function CartDrawer({
  open,
  step,
  copy,
  language,
  items,
  subtotal,
  deliveryFee,
  total,
  onClose,
  onClearCart,
  onStepChange,
  onQuantityChange,
  onRemoveItem,
}: CartDrawerProps) {
  const closeGuardRef = useRef<CloseGuard | null>(null);

  const setCheckoutCloseGuard = useCallback((guard: CloseGuard | null) => {
    closeGuardRef.current = guard;
  }, []);

  const requestClose = useCallback(async () => {
    const canClose = closeGuardRef.current ? await closeGuardRef.current() : true;
    if (canClose) {
      onClose();
    }
  }, [onClose]);

  useEffect(() => {
    if (!open) return undefined;

    const closeOnEscape = (event: KeyboardEvent) => {
      if (event.key !== "Escape") return;
      event.preventDefault();
      void requestClose();
    };

    window.addEventListener("keydown", closeOnEscape);

    return () => window.removeEventListener("keydown", closeOnEscape);
  }, [open, requestClose]);

  if (!open) return null;

  const title = step === "checkout" ? copy.checkout : copy.cart;

  return (
    <div className={styles.drawerLayer} role="presentation">
      <button
        className={styles.drawerBackdrop}
        type="button"
        onClick={() => void requestClose()}
        aria-label={copy.close}
      />
      <aside
        className={styles.cartDrawer}
        data-step={step}
        role="dialog"
        aria-modal="true"
        aria-labelledby="cart-title"
      >
        <div className={styles.drawerHeader}>
          <div>
            <p className={styles.eyebrow}>ZYN · RESERVE</p>
            <h2 id="cart-title">{title}</h2>
          </div>
          <button type="button" onClick={() => void requestClose()} aria-label={copy.close}>
            <X size={19} />
          </button>
        </div>

        {step === "cart" && (
          <div className={styles.cartView}>
            {items.length === 0 ? (
              <div className={styles.emptyCart}>
                <span>0</span>
                <p>{copy.emptyCart}</p>
                <button type="button" onClick={onClose}>
                  {copy.continueShopping}
                </button>
              </div>
            ) : (
              <>
                <div className={styles.cartItems}>
                  {items.map(({ product, quantity }) => {
                    const trackedStockLimit = getTrackedStockLimit(product);
                    const canIncrease = trackedStockLimit === null || quantity < trackedStockLimit;

                    return (
                      <article className={styles.cartLine} key={product.id}>
                        <ProductArtwork product={product} size={64} />
                        <div>
                          <h3>{product.name}</h3>
                          <p>
                            {product.strengthMg} mg · {formatCurrency(product.price)}
                          </p>
                          <div className={styles.quantityControl} aria-label={copy.quantity}>
                            <button
                              type="button"
                              onClick={() => onQuantityChange(product.id, -1)}
                              aria-label={`${copy.decreaseQuantity}: ${product.name}`}
                            >
                              <Minus size={12} aria-hidden="true" />
                            </button>
                            <span>{quantity}</span>
                            <button
                              type="button"
                              onClick={() => onQuantityChange(product.id, 1)}
                              disabled={!canIncrease}
                              aria-label={`${copy.increaseQuantity}: ${product.name}`}
                            >
                              <Plus size={12} aria-hidden="true" />
                            </button>
                          </div>
                        </div>
                        <div className={styles.cartLineActions}>
                          <strong>{formatCurrency(product.price * quantity)}</strong>
                          <button
                            type="button"
                            onClick={() => onRemoveItem(product.id)}
                            aria-label={`${copy.removeItem}: ${product.name}`}
                          >
                            <Trash2 size={14} aria-hidden="true" />
                          </button>
                        </div>
                      </article>
                    );
                  })}
                </div>
                <div className={styles.cartSummary}>
                  <div className={styles.orderTotals}>
                    <p>
                      <span>{copy.subtotal}</span>
                      <strong>{formatCurrency(subtotal)}</strong>
                    </p>
                    <p>
                      <span>{copy.deliveryFee}</span>
                      <strong>{formatCurrency(deliveryFee)}</strong>
                    </p>
                    <p className={styles.totalRow}>
                      <span>{copy.total}</span>
                      <strong>{formatCurrency(total)}</strong>
                    </p>
                  </div>
                  <button
                    className={styles.primaryButton}
                    type="button"
                    onClick={() => onStepChange("checkout")}
                  >
                    {copy.checkout} <span>→</span>
                  </button>
                </div>
              </>
            )}
          </div>
        )}

        {step === "checkout" && (
          <CheckoutForm
            copy={copy}
            language={language}
            items={items}
            subtotal={subtotal}
            deliveryFee={deliveryFee}
            total={total}
            onBack={() => onStepChange("cart")}
            onClose={onClose}
            onCloseGuardChange={setCheckoutCloseGuard}
            onClearCart={onClearCart}
            onQuantityChange={onQuantityChange}
            onRemoveItem={onRemoveItem}
          />
        )}
      </aside>
    </div>
  );
}
