import { ArrowRight, ShieldCheck } from "lucide-react";
import Image from "next/image";

import { STORE_CONFIG } from "@/config/store";
import type { StoreCopy } from "@/content/store-copy";

import styles from "./storefront.module.css";

type HeroProps = { copy: StoreCopy };

export function Hero({ copy }: HeroProps) {
  return (
    <section className={styles.hero} id="top">
      <div className={styles.heroCopy}>
        <p className={styles.eyebrow}>{copy.heroEyebrow}</p>
        <h1>
          {copy.heroTitleA}
          <em>{copy.heroTitleB}</em>
        </h1>
        <p className={styles.heroBody}>{copy.heroBody}</p>
        <div className={styles.heroActions}>
          <a className={styles.primaryButton} href="#collection">
            {copy.shopCollection} <ArrowRight size={16} />
          </a>
          <a
            className={styles.secondaryButton}
            href={`https://t.me/${STORE_CONFIG.telegramUsername}`}
            target="_blank"
            rel="noreferrer"
          >
            {copy.telegram}
          </a>
        </div>
        <p className={styles.adultNotice}>
          <ShieldCheck size={15} /> {copy.adultOnly}
        </p>
      </div>
      <div className={styles.heroVisual} aria-hidden="true">
        <Image
          className={styles.heroImage}
          src="/assets/figma/hero-zyn-peppermint.png"
          alt=""
          width={1024}
          height={1024}
          priority
          unoptimized
        />
      </div>
    </section>
  );
}
