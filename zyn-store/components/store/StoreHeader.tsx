import { Menu, ShoppingCart } from "lucide-react";
import Image from "next/image";

import type { StoreCopy } from "@/content/store-copy";

import styles from "./storefront.module.css";

type StoreHeaderProps = {
  copy: StoreCopy;
  itemCount: number;
  onLanguageChange: () => void;
  onOpenCart: () => void;
};

export function StoreHeader({
  copy,
  itemCount,
  onLanguageChange,
  onOpenCart,
}: StoreHeaderProps) {
  return (
    <header className={styles.siteHeader}>
      <div className={styles.topTrim} />
      <nav className={styles.header} aria-label="Store navigation">
        <div className={styles.headerLeft}>
          <a className={styles.brand} href="#collection" aria-label={copy.shopCollection}>
            <Menu size={18} strokeWidth={1.7} aria-hidden="true" />
            <span>ZYN Reserve</span>
          </a>
          <button className={styles.languageButton} type="button" onClick={onLanguageChange}>
            {copy.languageToggle}
          </button>
        </div>
        <a className={styles.centerBrand} href="#top" aria-label="ZYN Reserve home">
          ZYN
        </a>
        <div className={styles.headerActions}>
          <button
            className={styles.cartButton}
            type="button"
            onClick={onOpenCart}
            aria-label={`${copy.openCart}: ${itemCount}`}
          >
            <ShoppingCart size={23} strokeWidth={1.8} aria-hidden="true" />
            <span>{itemCount}</span>
          </button>
          <a className={styles.profileButton} href="/admin" aria-label={copy.adminLogin}>
            <Image src="/assets/figma/profile-admin.svg" alt="" width={44} height={38} unoptimized />
          </a>
        </div>
      </nav>
    </header>
  );
}
