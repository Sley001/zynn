import type { StoreCopy } from "@/content/store-copy";
import type { Product } from "@/types/store";

import { ProductCard } from "./ProductCard";
import styles from "./storefront.module.css";

type ProductGridProps = {
  copy: StoreCopy;
  products: Product[];
  isLoading: boolean;
  isFromApi: boolean;
  hasError: boolean;
  onAdd: (product: Product) => void;
  onOpen: (product: Product) => void;
  onBuyNow: (product: Product) => void;
};

export function ProductGrid({
  copy,
  products,
  isLoading,
  isFromApi,
  hasError,
  onAdd,
  onOpen,
  onBuyNow,
}: ProductGridProps) {
  const catalogStatus = isLoading
    ? copy.loadingProducts
    : hasError
      ? copy.catalogFallback
      : isFromApi
        ? copy.catalogDatabase
        : null;

  return (
    <section className={styles.collection} id="collection">
      <div className={styles.sectionHeading}>
        <div>
          <p className={styles.eyebrow}>{copy.collectionEyebrow}</p>
          <h2>{copy.collectionTitle}</h2>
        </div>
        <p>{copy.collectionBody}</p>
      </div>
      {catalogStatus && (
        <p
          className={styles.catalogStatus}
          data-tone={hasError ? "warning" : isFromApi ? "success" : "loading"}
          role="status"
        >
          <span aria-hidden="true" />
          {catalogStatus}
        </p>
      )}
      <div className={styles.productGrid}>
        {products.map((product) => (
          <ProductCard
            key={product.id}
            product={product}
            copy={copy}
            onAdd={onAdd}
            onOpen={onOpen}
            onBuyNow={onBuyNow}
          />
        ))}
      </div>
    </section>
  );
}
