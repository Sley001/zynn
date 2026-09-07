"use client";

import { useEffect, useState } from "react";

import { STORE_CONFIG } from "@/config/store";
import { getStoreSettings, type StoreSettings } from "@/lib/api";

const FALLBACK_SETTINGS: StoreSettings = {
  currency: STORE_CONFIG.currency,
  deliveryFee: STORE_CONFIG.deliveryFee,
  deliveryFeeCents: Math.round(STORE_CONFIG.deliveryFee * 100),
  checkoutSessionLifetimeMinutes: 15,
};

export function useStoreSettings() {
  const [settings, setSettings] = useState<StoreSettings>(FALLBACK_SETTINGS);
  const [isLoading, setIsLoading] = useState(true);
  const [hasError, setHasError] = useState(false);

  useEffect(() => {
    const controller = new AbortController();

    async function loadSettings() {
      try {
        const nextSettings = await getStoreSettings(controller.signal);
        setSettings(nextSettings);
        setHasError(false);
      } catch {
        setHasError(true);
      } finally {
        setIsLoading(false);
      }
    }

    void loadSettings();

    return () => controller.abort();
  }, []);

  return { settings, isLoading, hasError };
}
