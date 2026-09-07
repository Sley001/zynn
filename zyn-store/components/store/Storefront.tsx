"use client";

import { useEffect, useState } from "react";

import { STORE_COPY } from "@/content/store-copy";
import { useAgeVerification } from "@/hooks/use-age-verification";
import { useCart } from "@/hooks/use-cart";
import { useProducts } from "@/hooks/use-products";
import { useStoreSettings } from "@/hooks/use-store-settings";
import { readStoredCheckoutSessionToken } from "@/lib/checkout-session-storage";
import type { CheckoutStep, Language, Product } from "@/types/store";

import { AgeGate } from "./AgeGate";
import { CartDrawer } from "./CartDrawer";
import { Hero } from "./Hero";
import { ProductDetailModal } from "./ProductDetailModal";
import { ProductGrid } from "./ProductGrid";
import { StoreFooter } from "./StoreFooter";
import { StoreHeader } from "./StoreHeader";
import styles from "./storefront.module.css";

export function Storefront() {
  const [language, setLanguage] = useState<Language>("km");
  const [cartOpen, setCartOpen] = useState(false);
  const [checkoutStep, setCheckoutStep] = useState<CheckoutStep>("cart");
  const [activeProduct, setActiveProduct] = useState<Product | null>(null);
  const { isReady, isVerified, verify } = useAgeVerification();
  const { settings } = useStoreSettings();
  const {
    items,
    itemCount,
    subtotal,
    deliveryFee,
    total,
    addItem,
    updateQuantity,
    removeItem,
    clear,
  } = useCart(settings.deliveryFee);
  const { products, isLoading, isFromApi, hasError } = useProducts();
  const copy = STORE_COPY[language];

  const toggleLanguage = () => setLanguage((current) => (current === "km" ? "en" : "km"));

  const addToCart = (product: Product, quantity = 1) => {
    if (!product.isOrderable) return;
    addItem(product, quantity);
  };

  const buyNow = (product: Product, quantity = 1) => {
    if (!product.isOrderable) return;
    addItem(product, quantity);
    setCheckoutStep("checkout");
    setCartOpen(true);
  };

  useEffect(() => {
    if (!readStoredCheckoutSessionToken()) return;

    const timeout = window.setTimeout(() => {
      setCheckoutStep("checkout");
      setCartOpen(true);
    }, 0);

    return () => window.clearTimeout(timeout);
  }, []);

  useEffect(() => {
    const closeOverlays = (event: KeyboardEvent) => {
      if (event.key !== "Escape") return;
      setActiveProduct(null);
    };

    const overlayOpen = Boolean(activeProduct) || cartOpen;
    document.body.style.overflow = overlayOpen ? "hidden" : "";
    window.addEventListener("keydown", closeOverlays);

    return () => {
      document.body.style.overflow = "";
      window.removeEventListener("keydown", closeOverlays);
    };
  }, [activeProduct, cartOpen]);

  if (!isReady || !isVerified) {
    return (
      <AgeGate
        copy={copy}
        language={language}
        isReady={isReady}
        onLanguageChange={toggleLanguage}
        onConfirm={verify}
      />
    );
  }

  return (
    <div className={styles.storefront} lang={language}>
      <StoreHeader
        copy={copy}
        itemCount={itemCount}
        onLanguageChange={toggleLanguage}
        onOpenCart={() => {
          setCheckoutStep("cart");
          setCartOpen(true);
        }}
      />
      <main>
        <Hero copy={copy} />
        <ProductGrid
          copy={copy}
          products={products}
          isLoading={isLoading}
          isFromApi={isFromApi}
          hasError={hasError}
          onAdd={addToCart}
          onOpen={setActiveProduct}
          onBuyNow={buyNow}
        />
      </main>
      <StoreFooter copy={copy} />
      <ProductDetailModal
        product={activeProduct}
        copy={copy}
        onClose={() => setActiveProduct(null)}
        onAdd={addToCart}
        onBuyNow={buyNow}
      />
      <CartDrawer
        open={cartOpen}
        step={checkoutStep}
        copy={copy}
        language={language}
        items={items}
        subtotal={subtotal}
        deliveryFee={deliveryFee}
        total={total}
        onClose={() => setCartOpen(false)}
        onClearCart={clear}
        onStepChange={setCheckoutStep}
        onQuantityChange={updateQuantity}
        onRemoveItem={removeItem}
      />
    </div>
  );
}
