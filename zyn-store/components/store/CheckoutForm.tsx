"use client";

/* eslint-disable @next/next/no-img-element */

import {
  ArrowUpRight,
  CheckCircle2,
  Clock,
  Copy,
  Home,
  Loader2,
  MessageCircle,
  Minus,
  Plus,
  RefreshCw,
  Trash2,
  X,
  XCircle,
} from "lucide-react";
import { FormEvent, useCallback, useEffect, useMemo, useRef, useState } from "react";

import { PAYMENT_METHODS, STORE_CONFIG } from "@/config/store";
import type { StoreCopy } from "@/content/store-copy";
import {
  ApiRequestError,
  cancelCheckoutSession,
  claimCheckoutSessionPaid,
  createCheckoutSession,
  createOrder,
  getCheckoutSessionStatus,
  isApiConfigured,
  type CheckoutSession,
  type CheckoutSessionItem,
  type CheckoutSessionOrder,
  type CreatedOrder,
} from "@/lib/api";
import {
  forgetCheckoutSessionToken,
  readStoredCheckoutSessionToken,
  rememberCheckoutSessionToken,
} from "@/lib/checkout-session-storage";
import { formatCurrency } from "@/lib/order";
import { getTrackedStockLimit } from "@/lib/product";
import {
  buildTelegramPaymentConfirmationMessage,
  buildTelegramPaymentConfirmationUrl,
} from "@/lib/telegram";
import type {
  CartItem,
  CustomerDetails,
  DeliveryDetails,
  Language,
  PaymentMethod,
} from "@/types/store";

import { DeliverySelector } from "./DeliverySelector";
import { ProductArtwork } from "./ProductArtwork";
import { PaymentReceiptUpload, type ReceiptClaim } from "./PaymentReceiptUpload";
import styles from "./storefront.module.css";

type CheckoutFormProps = {
  copy: StoreCopy;
  language: Language;
  items: CartItem[];
  subtotal: number;
  deliveryFee: number;
  total: number;
  onBack: () => void;
  onClose: () => void;
  onCloseGuardChange?: (guard: (() => boolean | Promise<boolean>) | null) => void;
  onClearCart: () => void;
  onQuantityChange: (productId: number, change: number) => void;
  onRemoveItem: (productId: number) => void;
};

type MissingQrState = {
  total: number;
  currency: string;
};

type LegacyOrderState = CreatedOrder & {
  customer: CustomerDetails;
  delivery: DeliveryDetails;
  paymentMethod: PaymentMethod;
};

const EMPTY_CUSTOMER: CustomerDetails = { fullName: "", phone: "" };
const EMPTY_DELIVERY: DeliveryDetails = { province: "", district: "", note: "" };
const POLL_MS = 4000;

function isQrPayment(method: PaymentMethod) {
  return method === "payway" || method === "khqr";
}

function isTerminalSession(session: CheckoutSession) {
  return ["matched", "expired", "cancelled"].includes(session.status);
}

function formatMoney(value: number, currency: string = STORE_CONFIG.currency) {
  return new Intl.NumberFormat(STORE_CONFIG.locale, {
    style: "currency",
    currency,
  }).format(value);
}

function formatCountdown(expiresAt: string, nowMs: number) {
  const remaining = Math.max(0, new Date(expiresAt).getTime() - nowMs);
  const minutes = Math.floor(remaining / 60000);
  const seconds = Math.floor((remaining % 60000) / 1000);

  return `${minutes}:${String(seconds).padStart(2, "0")}`;
}

function sessionItemsFromCart(items: CartItem[]): CheckoutSessionItem[] {
  return items.map(({ product, quantity }) => ({
    productId: product.id,
    productName: product.name,
    strengthMg: product.strengthMg,
    unitPrice: product.price,
    unitPriceCents: Math.round(product.price * 100),
    quantity,
    lineTotal: product.price * quantity,
    lineTotalCents: Math.round(product.price * quantity * 100),
  }));
}

export function CheckoutForm({
  copy,
  language,
  items,
  subtotal,
  deliveryFee,
  total,
  onBack,
  onClose,
  onCloseGuardChange,
  onClearCart,
  onQuantityChange,
  onRemoveItem,
}: CheckoutFormProps) {
  const [customer, setCustomer] = useState<CustomerDetails>(EMPTY_CUSTOMER);
  const [delivery, setDelivery] = useState<DeliveryDetails>(EMPTY_DELIVERY);
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>("payway");
  const [ageConfirmed, setAgeConfirmed] = useState(false);
  const [error, setError] = useState("");
  const [networkMessage, setNetworkMessage] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [checkoutSession, setCheckoutSession] = useState<CheckoutSession | null>(null);
  const [missingQr, setMissingQr] = useState<MissingQrState | null>(null);
  const [legacyOrder, setLegacyOrder] = useState<LegacyOrderState | null>(null);
  const [isClaiming, setIsClaiming] = useState(false);
  const [isCancelling, setIsCancelling] = useState(false);
  const [nowMs, setNowMs] = useState(() => Date.now());
  const [telegramCountdown, setTelegramCountdown] = useState<number | null>(null);
  const [hasOpenedTelegram, setHasOpenedTelegram] = useState(false);
  const [copyStatus, setCopyStatus] = useState("");
  const clearedOrderRef = useRef<string | null>(null);

  const sessionToken = checkoutSession?.token;
  const matchedOrder = checkoutSession?.status === "matched" ? checkoutSession.order : null;
  const displayItems = checkoutSession?.items.length
    ? checkoutSession.items
    : sessionItemsFromCart(items);

  useEffect(() => {
    if (checkoutSession) return undefined;

    const storedToken = readStoredCheckoutSessionToken();
    if (!storedToken) return undefined;

    let alive = true;

    void getCheckoutSessionStatus(storedToken)
      .then((session) => {
        if (!alive) return;
        setCheckoutSession(session);
        setNetworkMessage("");
        if (session.status === "expired" || session.status === "cancelled") {
          forgetCheckoutSessionToken();
        }
      })
      .catch(() => {
        if (!alive) return;
        setNetworkMessage(copy.networkUnavailable);
      });

    return () => {
      alive = false;
    };
  }, [checkoutSession, copy.networkUnavailable]);

  useEffect(() => {
    if (!checkoutSession || isTerminalSession(checkoutSession)) return undefined;

    const interval = window.setInterval(() => setNowMs(Date.now()), 1000);
    return () => window.clearInterval(interval);
  }, [checkoutSession]);

  useEffect(() => {
    if (!sessionToken || !checkoutSession || isTerminalSession(checkoutSession)) {
      return undefined;
    }

    let stopped = false;
    let timeoutId = 0;
    let failureCount = 0;
    let controller: AbortController | null = null;

    const poll = async () => {
      controller = new AbortController();

      try {
        const nextSession = await getCheckoutSessionStatus(sessionToken, controller.signal);
        failureCount = 0;
        setNetworkMessage("");
        setCheckoutSession(nextSession);

        if (isTerminalSession(nextSession)) {
          return;
        }
      } catch {
        if (!stopped) {
          failureCount += 1;
          setNetworkMessage(copy.networkUnavailable);
        }
      }

      if (!stopped) {
        timeoutId = window.setTimeout(poll, Math.min(POLL_MS + failureCount * 2000, 12000));
      }
    };

    timeoutId = window.setTimeout(poll, POLL_MS);

    return () => {
      stopped = true;
      controller?.abort();
      window.clearTimeout(timeoutId);
    };
  }, [checkoutSession, copy.networkUnavailable, sessionToken]);

  useEffect(() => {
    if (!matchedOrder) return;

    forgetCheckoutSessionToken();

    if (clearedOrderRef.current !== matchedOrder.orderNumber) {
      clearedOrderRef.current = matchedOrder.orderNumber;
      onClearCart();
    }

    const timeout = window.setTimeout(() => {
      setTelegramCountdown((current) => current ?? 3);
    }, 0);

    return () => window.clearTimeout(timeout);
  }, [matchedOrder, onClearCart]);

  const telegramUrl = useMemo(
    () => (matchedOrder ? buildTelegramPaymentConfirmationUrl(matchedOrder) : ""),
    [matchedOrder],
  );
  const telegramMessage = useMemo(
    () => (matchedOrder ? buildTelegramPaymentConfirmationMessage(matchedOrder) : ""),
    [matchedOrder],
  );

  useEffect(() => {
    if (!matchedOrder || telegramCountdown === null || hasOpenedTelegram) return undefined;

    if (telegramCountdown <= 0) {
      window.open(telegramUrl, "_blank", "noopener,noreferrer");
      const timeout = window.setTimeout(() => setHasOpenedTelegram(true), 0);
      return () => window.clearTimeout(timeout);
    }

    const timeout = window.setTimeout(() => {
      setTelegramCountdown((current) => (current === null ? null : Math.max(0, current - 1)));
    }, 1000);

    return () => window.clearTimeout(timeout);
  }, [hasOpenedTelegram, matchedOrder, telegramCountdown, telegramUrl]);

  const submitOrder = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    if (isSubmitting) return;

    if (items.length === 0) {
      setError(copy.emptyCart);
      return;
    }

    if (
      !customer.fullName.trim() ||
      !customer.phone.trim() ||
      !delivery.province ||
      !delivery.district ||
      !ageConfirmed
    ) {
      setError(copy.required);
      return;
    }

    setIsSubmitting(true);
    setError("");
    setNetworkMessage("");
    setMissingQr(null);

    try {
      if (!isApiConfigured) {
        throw new Error(copy.orderApiError);
      }

      if (isQrPayment(paymentMethod)) {
        const session = await createCheckoutSession({
          items,
          customer,
          delivery,
          paymentMethod,
        });

        rememberCheckoutSessionToken(session.token);
        setCheckoutSession(session);
      } else {
        const order = await createOrder({
          items,
          customer,
          delivery,
          paymentMethod,
        });
        setLegacyOrder({ ...order, customer, delivery, paymentMethod });
        onClearCart();
      }
    } catch (submissionError: unknown) {
      if (
        submissionError instanceof ApiRequestError &&
        submissionError.code === "payment_qr_unavailable"
      ) {
        setMissingQr({
          total: submissionError.total ?? total,
          currency: submissionError.currency ?? STORE_CONFIG.currency,
        });
        setError(copy.paymentQrUnavailable);
      } else {
        const apiMessage = submissionError instanceof Error ? submissionError.message : "";
        setError(apiMessage && apiMessage !== "Failed to fetch" ? apiMessage : copy.orderApiError);
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  const claimPaid = async (transactionReference: string, receipt: File) => {
    if (!checkoutSession || isClaiming) return;

    setIsClaiming(true);
    setError("");

    try {
      setCheckoutSession(await claimCheckoutSessionPaid(checkoutSession.token, transactionReference, receipt));
    } catch (claimError: unknown) {
      const apiMessage = claimError instanceof Error ? claimError.message : "";
      setError(apiMessage && apiMessage !== "Failed to fetch" ? apiMessage : copy.orderApiError);
    } finally {
      setIsClaiming(false);
    }
  };

  const cancelSession = useCallback(async (): Promise<boolean> => {
    if (!checkoutSession || isTerminalSession(checkoutSession)) return true;
    if (checkoutSession.status === "payment_claimed") return true;
    if (isCancelling || isClaiming) return false;
    if (!window.confirm(copy.cancelPaymentConfirm)) return false;

    setIsCancelling(true);
    setError("");

    try {
      setCheckoutSession(await cancelCheckoutSession(checkoutSession.token));
      forgetCheckoutSessionToken();
      return true;
    } catch (cancelError: unknown) {
      const apiMessage = cancelError instanceof Error ? cancelError.message : "";
      setError(apiMessage && apiMessage !== "Failed to fetch" ? apiMessage : copy.orderApiError);
      return false;
    } finally {
      setIsCancelling(false);
    }
  }, [
    checkoutSession,
    copy.cancelPaymentConfirm,
    copy.orderApiError,
    isCancelling,
    isClaiming,
  ]);

  useEffect(() => {
    if (!onCloseGuardChange) return undefined;

    onCloseGuardChange(cancelSession);

    return () => onCloseGuardChange(null);
  }, [cancelSession, onCloseGuardChange]);

  const resetSession = () => {
    forgetCheckoutSessionToken();
    setCheckoutSession(null);
    setMissingQr(null);
    setNetworkMessage("");
    setError("");
    setTelegramCountdown(null);
    setHasOpenedTelegram(false);
  };

  const copyTelegramMessage = async () => {
    try {
      await navigator.clipboard.writeText(telegramMessage);
      setCopyStatus(copy.copiedMessage);
    } catch {
      setCopyStatus(copy.copyMessage);
    }
  };

  if (legacyOrder) {
    return (
      <div className={styles.doneView}>
        <span>✓</span>
        <h3>{copy.orderReady}</h3>
        <p>{copy.orderSavedBody}</p>
        <p className={styles.orderNumber}>
          {copy.orderNumber}
          <strong>{legacyOrder.orderNumber}</strong>
        </p>
        <button className={styles.secondaryButton} type="button" onClick={onClose}>
          {copy.home}
        </button>
      </div>
    );
  }

  if (matchedOrder) {
    return (
      <PaidResult
        copy={copy}
        order={matchedOrder}
        telegramUrl={telegramUrl}
        telegramMessage={telegramMessage}
        telegramCountdown={telegramCountdown}
        copyStatus={copyStatus}
        onCopyMessage={copyTelegramMessage}
        onClose={onClose}
      />
    );
  }

  if (checkoutSession) {
    return (
      <PaymentQrView
        copy={copy}
        session={checkoutSession}
        items={displayItems}
        nowMs={nowMs}
        error={error}
        networkMessage={networkMessage}
        isClaiming={isClaiming}
        isCancelling={isCancelling}
        onClaimPaid={claimPaid}
        onCancel={() => void cancelSession()}
        onRetry={resetSession}
        onClose={onClose}
      />
    );
  }

  return (
    <form
      className={styles.checkoutForm}
      id="store-checkout-form"
      name="store-checkout-form"
      onSubmit={submitOrder}
      noValidate
    >
      <button className={styles.backButton} type="button" onClick={onBack} disabled={isSubmitting}>
        ← {copy.backToCart}
      </button>
      <div className={styles.checkoutItems} aria-label={copy.cart}>
        {items.map(({ product, quantity }) => {
          const trackedStockLimit = getTrackedStockLimit(product);
          const canIncrease = trackedStockLimit === null || quantity < trackedStockLimit;

          return (
            <article className={styles.checkoutLine} key={product.id}>
              <ProductArtwork product={product} size={54} />
              <div>
                <h3>{product.name}</h3>
                <p>
                  {product.strengthMg} mg · {formatCurrency(product.price)}
                </p>
                <div className={styles.quantityControl} aria-label={copy.quantity}>
                  <button
                    type="button"
                    onClick={() => onQuantityChange(product.id, -1)}
                    disabled={isSubmitting}
                    aria-label={`${copy.decreaseQuantity}: ${product.name}`}
                  >
                    <Minus size={12} aria-hidden="true" />
                  </button>
                  <span>{quantity}</span>
                  <button
                    type="button"
                    onClick={() => onQuantityChange(product.id, 1)}
                    disabled={isSubmitting || !canIncrease}
                    aria-label={`${copy.increaseQuantity}: ${product.name}`}
                  >
                    <Plus size={12} aria-hidden="true" />
                  </button>
                </div>
              </div>
              <div className={styles.cartLineActions}>
                <strong>{formatCurrency(product.price * quantity)}</strong>
                <button
                  type="button"
                  onClick={() => onRemoveItem(product.id)}
                  disabled={isSubmitting}
                  aria-label={`${copy.removeItem}: ${product.name}`}
                >
                  <Trash2 size={14} aria-hidden="true" />
                </button>
              </div>
            </article>
          );
        })}
        <div className={styles.checkoutSubtotal}>
          <p>
            <span>{copy.subtotal}</span>
            <strong>{formatCurrency(subtotal)}</strong>
          </p>
          <p>
            <span>{copy.deliveryFee}</span>
            <strong>{formatCurrency(deliveryFee)}</strong>
          </p>
          <p className={styles.totalRow}>
            <span>{copy.total}</span>
            <strong>{formatCurrency(total)}</strong>
          </p>
        </div>
      </div>
      <p className={styles.checkoutIntro}>{copy.checkoutIntro}</p>
      <div className={styles.customerFields}>
        <label>
          <span>{copy.fullName}</span>
          <input
            id="checkout-full-name"
            name="fullName"
            type="text"
            autoComplete="name"
            value={customer.fullName}
            onChange={(event) => setCustomer({ ...customer, fullName: event.target.value })}
            required
          />
        </label>
        <label>
          <span>{copy.phone}</span>
          <input
            id="checkout-phone"
            name="phone"
            type="tel"
            inputMode="tel"
            autoComplete="tel"
            value={customer.phone}
            onChange={(event) => setCustomer({ ...customer, phone: event.target.value })}
            required
          />
        </label>
      </div>
      <DeliverySelector
        copy={copy}
        language={language}
        value={delivery}
        onChange={setDelivery}
      />
      <fieldset className={styles.paymentOptions}>
        <legend>{copy.payment}</legend>
        {PAYMENT_METHODS.map((method) => (
          <label key={method.id} className={paymentMethod === method.id ? styles.selectedPayment : ""}>
            <input
              type="radio"
              name="paymentMethod"
              value={method.id}
              checked={paymentMethod === method.id}
              onChange={() => setPaymentMethod(method.id)}
            />
            <span>
              <strong>{method.label}</strong>
              <small>{method.description}</small>
            </span>
          </label>
        ))}
      </fieldset>
      <label className={styles.confirmAge}>
        <input
          id="checkout-age-confirmed"
          name="ageConfirmed"
          type="checkbox"
          checked={ageConfirmed}
          onChange={(event) => setAgeConfirmed(event.target.checked)}
        />
        <span>{copy.ageConfirmation}</span>
      </label>
      {missingQr && (
        <div className={styles.missingQrPanel}>
          <strong>{copy.missingQrTitle}</strong>
          <p>{copy.missingQrBody}</p>
          <span>
            {copy.unavailableTotal}: {formatMoney(missingQr.total, missingQr.currency)}
          </span>
          <a href={`https://t.me/${STORE_CONFIG.telegramUsername}`} target="_blank" rel="noreferrer">
            {copy.contactSeller}
          </a>
        </div>
      )}
      {error && <p className={styles.formError}>{error}</p>}
      <div className={styles.checkoutSubmitBar}>
        <p>
          <span>{copy.total}</span>
          <strong>{formatCurrency(total)}</strong>
        </p>
        <button className={styles.sendOrderButton} type="submit" disabled={isSubmitting}>
          {isSubmitting ? copy.creatingPayment : copy.orderNow}
          {isSubmitting ? <Loader2 size={16} /> : <ArrowUpRight size={16} />}
        </button>
      </div>
    </form>
  );
}

function PaymentQrView({
  copy,
  session,
  items,
  nowMs,
  error,
  networkMessage,
  isClaiming,
  isCancelling,
  onClaimPaid,
  onCancel,
  onRetry,
  onClose,
}: {
  copy: StoreCopy;
  session: CheckoutSession;
  items: CheckoutSessionItem[];
  nowMs: number;
  error: string;
  networkMessage: string;
  isClaiming: boolean;
  isCancelling: boolean;
  onClaimPaid: (transactionReference: string, receipt: File) => void;
  onCancel: () => void;
  onRetry: () => void;
  onClose: () => void;
}) {
  const isExpired = session.status === "expired";
  const isCancelled = session.status === "cancelled";
  const isClaimed = session.status === "payment_claimed" || session.paymentClaimedAt !== null;
  const countdown = formatCountdown(session.expiresAt, nowMs);
  const [isQrExpanded, setIsQrExpanded] = useState(false);
  const [receiptClaim, setReceiptClaim] = useState<ReceiptClaim>({ receipt: null, scanning: false });

  if (isExpired || isCancelled) {
    return (
      <div className={styles.checkoutForm}>
        <section className={styles.qrPaymentPanel} aria-labelledby="checkout-session-ended-title">
          <div className={styles.qrStatusIcon} data-status={session.status}>
            <XCircle size={30} />
          </div>
          <p className={styles.eyebrow}>{copy.checkoutSessionStatus}: {session.status}</p>
          <h3 id="checkout-session-ended-title">
            {isExpired ? copy.sessionExpiredTitle : copy.sessionCancelledTitle}
          </h3>
          <p>{isExpired ? copy.sessionExpiredBody : copy.sessionCancelledBody}</p>
          <button className={styles.sendOrderButton} type="button" onClick={onRetry}>
            {copy.retryCheckout} <RefreshCw size={16} />
          </button>
        </section>
      </div>
    );
  }

  return (
    <div className={styles.checkoutForm}>
      <button
        className={styles.backButton}
        type="button"
        onClick={isClaimed ? onClose : onCancel}
        disabled={isCancelling || isClaiming}
      >
        ← {isClaimed ? copy.close : isCancelling ? copy.cancellingPayment : copy.cancelPayment}
      </button>

      <section className={styles.qrPaymentPanel} aria-labelledby="checkout-qr-title">
        <div className={styles.qrStatusIcon} data-status={session.status}>
          <Clock size={30} />
        </div>
        <p className={styles.eyebrow}>{STORE_CONFIG.paymentMerchantName}</p>
        <h3 id="checkout-qr-title">
          {isClaimed ? copy.paymentClaimedTitle : copy.paymentQrTitle}
        </h3>
        <p>{isClaimed ? copy.paymentClaimedBody : copy.paymentQrBody}</p>

        <div className={styles.paymentSummary}>
          <div>
            <span>{copy.productSummary}</span>
            {items.map((item) => (
              <strong key={`${item.productId}-${item.productName}`}>
                {item.productName} × {item.quantity}
              </strong>
            ))}
          </div>
          <div>
            <span>{copy.subtotal}</span>
            <strong>{formatMoney(session.subtotal, session.currency)}</strong>
          </div>
          <div>
            <span>{copy.deliveryFee}</span>
            <strong>{formatMoney(session.deliveryFee, session.currency)}</strong>
          </div>
          <div>
            <span>{copy.total}</span>
            <strong>{formatMoney(session.total, session.currency)}</strong>
          </div>
        </div>

        {!isClaimed && session.paymentLink && (
          <a className={styles.sendOrderButton} href={session.paymentLink} target="_blank" rel="noopener noreferrer">
            {copy.openPaymentLink} <ArrowUpRight size={16} />
          </a>
        )}
        {!isClaimed && session.paymentLink && <p>{copy.paymentLinkInstructions}</p>}

        {!isClaimed && session.qr && (
          <div className={styles.qrCard}>
            <button
              className={styles.qrPreviewButton}
              type="button"
              onClick={() => setIsQrExpanded(true)}
              aria-label={`${copy.paymentQrTitle} ${session.currency}`}
              title={`${copy.paymentQrTitle} ${session.currency}`}
            >
              <img src={session.qr.imageUrl} alt={`${copy.paymentQrTitle} ${session.currency}`} />
            </button>
            <div>
              <span>{copy.scanToPay}</span>
              <strong>{formatMoney(session.total, session.currency)}</strong>
              <small>{copy.timeRemaining}: {countdown}</small>
            </div>
          </div>
        )}

        {!isClaimed && session.qr && isQrExpanded && (
          <div className={styles.qrLightbox} role="dialog" aria-modal="true">
            <button
              className={styles.qrLightboxBackdrop}
              type="button"
              onClick={() => setIsQrExpanded(false)}
              aria-label="Close"
            />
            <div className={styles.qrLightboxPanel}>
              <button
                className={styles.qrLightboxClose}
                type="button"
                onClick={() => setIsQrExpanded(false)}
                aria-label="Close"
                title="Close"
              >
                <X size={22} />
              </button>
              <img src={session.qr.imageUrl} alt={`${copy.paymentQrTitle} ${session.currency}`} />
            </div>
          </div>
        )}

        <p className={styles.supportMessage}>{copy.paymentSupportMessage}</p>
        {networkMessage && <p className={styles.formNotice}>{networkMessage}</p>}
        {error && <p className={styles.formError}>{error}</p>}

        {!isClaimed && (
          <PaymentReceiptUpload
            copy={copy}
            disabled={isClaiming || isCancelling}
            value={receiptClaim}
            onChange={setReceiptClaim}
            onScanComplete={(receipt, detectedReference) => onClaimPaid(detectedReference, receipt)}
          />
        )}
        {isClaimed && (
          <a className={styles.secondaryButton}
            href={`https://t.me/${STORE_CONFIG.telegramUsername}?text=${encodeURIComponent(`${copy.total}: ${formatMoney(session.total, session.currency)}`)}`}
            target="_blank" rel="noopener noreferrer">{copy.contactSeller}</a>
        )}
        {isClaiming && <p className={styles.formNotice} role="status">
          <Loader2 size={16} /> {copy.claimingPayment}
        </p>}
      </section>
    </div>
  );
}

function PaidResult({
  copy,
  order,
  telegramUrl,
  telegramMessage,
  telegramCountdown,
  copyStatus,
  onCopyMessage,
  onClose,
}: {
  copy: StoreCopy;
  order: CheckoutSessionOrder;
  telegramUrl: string;
  telegramMessage: string;
  telegramCountdown: number | null;
  copyStatus: string;
  onCopyMessage: () => void;
  onClose: () => void;
}) {
  return (
    <div className={styles.paidResult}>
      <div className={styles.paidMark}>
        <CheckCircle2 size={46} />
      </div>
      <h3>{copy.paidTitle}</h3>
      <strong>{formatMoney(order.total, order.currency)}</strong>
      <p>{copy.paidBody}</p>
      <p className={styles.orderNumber}>
        {copy.orderNumber}
        <strong>{order.orderNumber}</strong>
      </p>
      {telegramCountdown !== null && telegramCountdown > 0 && (
        <p className={styles.telegramCountdown}>
          {copy.openingTelegramIn} {telegramCountdown}
        </p>
      )}
      <a className={styles.sendOrderButton} href={telegramUrl} target="_blank" rel="noreferrer">
        <MessageCircle size={17} />
        {copy.confirmOnTelegram}
      </a>
      <div className={styles.paidActions}>
        <button className={styles.secondaryButton} type="button" onClick={onCopyMessage}>
          <Copy size={16} />
          {copy.copyMessage}
        </button>
        <a className={styles.secondaryButton} href={telegramUrl} target="_blank" rel="noreferrer">
          <MessageCircle size={16} />
          {copy.openTelegram}
        </a>
        <button className={styles.secondaryButton} type="button" onClick={onClose}>
          <Home size={16} />
          {copy.home}
        </button>
      </div>
      {copyStatus && <p className={styles.formNotice}>{copyStatus}</p>}
      <textarea className={styles.telegramDraftPreview} value={telegramMessage} readOnly rows={7} />
    </div>
  );
}
