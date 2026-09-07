import type { CSSProperties } from "react";

import type { Product } from "@/types/store";

import { Tin } from "./Tin";
import styles from "./storefront.module.css";

type ProductArtworkProps = {
  product: Product;
  size: number;
  open?: boolean;
};

export function ProductArtwork({ product, size, open = false }: ProductArtworkProps) {
  if (!product.imageUrl) {
    return <Tin color={product.color} accent={product.accent} size={size} open={open} />;
  }

  const style: CSSProperties = {
    width: size,
    height: size,
    backgroundImage: `url(${JSON.stringify(product.imageUrl)})`,
  };

  return (
    <span
      className={styles.productPhoto}
      style={style}
      role="img"
      aria-label={product.name}
    />
  );
}
