"use client";

import { useCallback, useSyncExternalStore } from "react";

const STORAGE_KEY = "zyn-reserve-age-verified";
const CHANGE_EVENT = "zyn-reserve-age-change";

function subscribeToAgeStatus(callback: () => void) {
  window.addEventListener("storage", callback);
  window.addEventListener(CHANGE_EVENT, callback);

  return () => {
    window.removeEventListener("storage", callback);
    window.removeEventListener(CHANGE_EVENT, callback);
  };
}

function getAgeStatus() {
  return window.localStorage.getItem(STORAGE_KEY) === "true";
}

function subscribeToHydration() {
  return () => undefined;
}

export function useAgeVerification() {
  const isReady = useSyncExternalStore(subscribeToHydration, () => true, () => false);
  const isVerified = useSyncExternalStore(subscribeToAgeStatus, getAgeStatus, () => false);

  const verify = useCallback(() => {
    window.localStorage.setItem(STORAGE_KEY, "true");
    window.dispatchEvent(new Event(CHANGE_EVENT));
  }, []);

  return { isReady, isVerified, verify };
}
