import { STORE_CONFIG } from "@/config/store";
import type { StoreCopy } from "@/content/store-copy";

import styles from "./storefront.module.css";

export function StoreFooter({ copy }: { copy: StoreCopy }) {
  return (
    <footer className={styles.footer}>
      <a className={styles.brand} href="#top">
        ZYN <span>·</span> RESERVE
      </a>
      <div>
        <p>{copy.footerWarning}</p>
        <p>{copy.independent}</p>
      </div>
      <a
        className={styles.footerTelegram}
        href={`https://t.me/${STORE_CONFIG.telegramUsername}`}
        target="_blank"
        rel="noreferrer"
      >
        @{STORE_CONFIG.telegramUsername} ↗
      </a>
    </footer>
  );
}
