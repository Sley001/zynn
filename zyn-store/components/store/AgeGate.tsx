"use client";

import { useState } from "react";

import type { StoreCopy } from "@/content/store-copy";
import type { Language } from "@/types/store";

import { Tin } from "./Tin";
import styles from "./storefront.module.css";

type AgeGateProps = {
  copy: StoreCopy;
  language: Language;
  isReady: boolean;
  onLanguageChange: () => void;
  onConfirm: () => void;
};

export function AgeGate({
  copy,
  language,
  isReady,
  onLanguageChange,
  onConfirm,
}: AgeGateProps) {
  const [checked, setChecked] = useState(false);

  return (
    <div className={styles.ageGate} role="dialog" aria-modal="true" aria-labelledby="age-title">
      <div className={styles.ageCard} lang={language}>
        <Tin color="#191b15" size={68} />
        <p className={styles.eyebrow}>ZYN · RESERVE</p>
        <h1 id="age-title">{copy.ageTitle}</h1>
        <p className={styles.ageBody}>{copy.ageBody}</p>
        <label className={styles.ageCheck}>
          <input
            type="checkbox"
            checked={checked}
            onChange={(event) => setChecked(event.target.checked)}
          />
          <span>{copy.ageCheck}</span>
        </label>
        <button
          className={styles.primaryButton}
          type="button"
          onClick={onConfirm}
          disabled={!checked || !isReady}
        >
          {copy.enter}
        </button>
        <button className={styles.languageLink} type="button" onClick={onLanguageChange}>
          {copy.languageToggle}
        </button>
      </div>
    </div>
  );
}
