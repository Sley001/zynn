export const CHECKOUT_SESSION_STORAGE_KEY = "zyn-store-active-checkout-session-token";

export function readStoredCheckoutSessionToken() {
  if (typeof window === "undefined") return null;

  const token = window.sessionStorage.getItem(CHECKOUT_SESSION_STORAGE_KEY);
  return token && token.length > 0 ? token : null;
}

export function rememberCheckoutSessionToken(token: string) {
  if (typeof window !== "undefined") {
    window.sessionStorage.setItem(CHECKOUT_SESSION_STORAGE_KEY, token);
  }
}

export function forgetCheckoutSessionToken() {
  if (typeof window !== "undefined") {
    window.sessionStorage.removeItem(CHECKOUT_SESSION_STORAGE_KEY);
  }
}
