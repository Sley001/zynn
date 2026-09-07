"use client";

import { AlertTriangle, CheckCircle2, RefreshCw, Save, Search } from "lucide-react";
import { useMemo, useState } from "react";

import { updateAdminOrder, type OrderUpdatePayload } from "./admin-api";
import styles from "./admin.module.css";
import type {
  AdminOrder,
  AdminPaymentStatus,
  AdminTelegramPaymentAlert,
  OrderStatus,
} from "./types";
import { ORDER_STATUS_OPTIONS, PAYMENT_STATUS_OPTIONS } from "./types";

type OrdersPanelProps = {
  token: string;
  orders: AdminOrder[];
  paymentAlerts: AdminTelegramPaymentAlert[];
  onOrderUpdated: (order: AdminOrder) => void;
  onError: (error: unknown) => void;
  formatCurrency: (value: number) => string;
};

type OrderDraft = OrderUpdatePayload;

function getOrderDraft(order: AdminOrder): OrderDraft {
  return {
    status: order.status,
    admin_note: order.admin_note ?? "",
    ...(order.payment.method === "cod"
      ? {
          payment_status: order.payment.status ?? "pending",
          transaction_reference: order.payment.transaction_reference ?? "",
        }
      : {}),
  };
}

function formatDate(value?: string | null) {
  if (!value) return "No date";
  return new Intl.DateTimeFormat("en-US", {
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(value));
}

function appendNote(existing: string | null | undefined, note: string) {
  return [existing, note].filter(Boolean).join("\n");
}

export function OrdersPanel({
  token,
  orders,
  paymentAlerts,
  onOrderUpdated,
  onError,
  formatCurrency,
}: OrdersPanelProps) {
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<"all" | OrderStatus>("all");
  const [paymentFilter, setPaymentFilter] = useState<"all" | AdminPaymentStatus>("all");
  const [drafts, setDrafts] = useState<Record<string, OrderDraft>>({});
  const [savingOrder, setSavingOrder] = useState("");

  const filteredOrders = useMemo(() => {
    const query = search.trim().toLowerCase();

    return orders.filter((order) => {
      const paymentStatus = order.payment.status ?? "pending";
      const matchesSearch =
        !query ||
        order.order_number.toLowerCase().includes(query) ||
        order.customer.name.toLowerCase().includes(query) ||
        order.customer.phone.toLowerCase().includes(query) ||
        order.delivery.province.toLowerCase().includes(query) ||
        order.delivery.district.toLowerCase().includes(query);
      const matchesStatus = statusFilter === "all" || order.status === statusFilter;
      const matchesPayment = paymentFilter === "all" || paymentStatus === paymentFilter;
      return matchesSearch && matchesStatus && matchesPayment;
    });
  }, [orders, paymentFilter, search, statusFilter]);

  const reviewPaymentAlerts = useMemo(
    () =>
      paymentAlerts.filter((alert) =>
        ["unmatched", "needs_review", "rejected", "duplicate"].includes(alert.status),
      ),
    [paymentAlerts],
  );

  const updateDraft = (order: AdminOrder, patch: Partial<OrderDraft>) => {
    setDrafts((current) => ({
      ...current,
      [order.order_number]: {
        ...(current[order.order_number] ?? getOrderDraft(order)),
        ...patch,
      },
    }));
  };

  const saveOrder = async (order: AdminOrder) => {
    const draft = drafts[order.order_number] ?? getOrderDraft(order);
    const needsStrongShipConfirm =
      draft.status === "shipped" &&
      order.payment.source === "telegram_aba_alert" &&
      order.telegram_payment_alert?.status !== "matched";

    if (
      needsStrongShipConfirm &&
      !window.confirm(
        "This Telegram-detected payment still needs review. Mark it shipped anyway?",
      )
    ) {
      return;
    }

    setSavingOrder(order.order_number);

    try {
      const updatedOrder = await updateAdminOrder(
        token,
        order.order_number,
        {
          ...draft,
          force_ship_without_verified_payment: needsStrongShipConfirm,
        },
      );
      onOrderUpdated(updatedOrder);
      setDrafts((current) => {
        const next = { ...current };
        delete next[order.order_number];
        return next;
      });
    } catch (error) {
      onError(error);
    } finally {
      setSavingOrder("");
    }
  };

  const quickUpdateOrder = async (order: AdminOrder, patch: Partial<OrderDraft>) => {
    setSavingOrder(order.order_number);

    try {
      const updatedOrder = await updateAdminOrder(token, order.order_number, {
        ...getOrderDraft(order),
        ...patch,
      });
      onOrderUpdated(updatedOrder);
      setDrafts((current) => {
        const next = { ...current };
        delete next[order.order_number];
        return next;
      });
    } catch (error) {
      onError(error);
    } finally {
      setSavingOrder("");
    }
  };

  return (
    <section className={styles.panelStack} aria-labelledby="orders-title">
      <div className={styles.sectionTitle}>
        <div>
          <p className={styles.eyebrow}>Orders</p>
          <h2 id="orders-title">Search, filter, and update orders</h2>
        </div>
        <p>{filteredOrders.length} matching orders</p>
      </div>

      <div className={styles.dataPanel}>
        {reviewPaymentAlerts.length > 0 && (
          <section className={styles.alertQueue} aria-label="Unmatched payment alerts">
            <div className={styles.panelHeading}>
              <div>
                <p className={styles.eyebrow}>Payment alerts</p>
                <h3>Unmatched and review queue</h3>
              </div>
              <span>{reviewPaymentAlerts.length}</span>
            </div>
            <div className={styles.alertRows}>
              {reviewPaymentAlerts.slice(0, 8).map((alert) => (
                <article className={styles.alertRow} key={alert.id}>
                  <div>
                    <strong>
                      {alert.amount ? `${Number(alert.amount).toFixed(2)} ${alert.currency ?? ""}` : "Unknown amount"}
                    </strong>
                    <span>{alert.transaction_id ? `Trx ${alert.transaction_id}` : "No transaction ID"}</span>
                  </div>
                  <div>
                    <span className={styles.statusPill} data-status={alert.status}>
                      {alert.status.replace(/_/g, " ")}
                    </span>
                    <small>{alert.failure_reason ?? formatDate(alert.processed_at ?? alert.created_at)}</small>
                  </div>
                </article>
              ))}
            </div>
          </section>
        )}

        <div className={styles.filters}>
          <label className={styles.searchBox}>
            <Search size={16} />
            <input
              type="search"
              placeholder="Order, customer, phone, or location"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
            />
          </label>
          <select
            aria-label="Filter orders by status"
            value={statusFilter}
            onChange={(event) => setStatusFilter(event.target.value as "all" | OrderStatus)}
          >
            <option value="all">All order statuses</option>
            {ORDER_STATUS_OPTIONS.map((status) => (
              <option key={status} value={status}>
                {status}
              </option>
            ))}
          </select>
          <select
            aria-label="Filter orders by payment status"
            value={paymentFilter}
            onChange={(event) =>
              setPaymentFilter(event.target.value as "all" | AdminPaymentStatus)
            }
          >
            <option value="all">All payment statuses</option>
            {PAYMENT_STATUS_OPTIONS.map((status) => (
              <option key={status} value={status}>
                {status}
              </option>
            ))}
          </select>
        </div>

        <div className={styles.orderList}>
          {filteredOrders.map((order) => {
            const draft = drafts[order.order_number] ?? getOrderDraft(order);

            return (
              <article className={styles.orderPanel} key={order.order_number}>
                <div className={styles.orderHeader}>
                  <div>
                    <strong>{order.order_number}</strong>
                    <span>
                      {order.customer.name} · {order.customer.phone}
                    </span>
                  </div>
                  <div>
                    <p>{formatCurrency(order.total)}</p>
                    <span>{formatDate(order.created_at)}</span>
                  </div>
                </div>

                <div className={styles.orderMeta}>
                  <span>{order.delivery.district}, {order.delivery.province}</span>
                  <span>Delivery {formatCurrency(order.delivery_fee)}</span>
                  <span>{order.payment.method.toUpperCase()}</span>
                  <span className={styles.statusPill} data-status={order.payment.status ?? "pending"}>
                    {(order.payment.status ?? "pending").toUpperCase()}
                  </span>
                  {order.payment.transaction_reference && (
                    <span>Ref {order.payment.transaction_reference}</span>
                  )}
                  <span>{order.items.length} item lines</span>
                </div>

                {order.payment.source === "telegram_aba_alert" && (
                  <div className={styles.paymentAudit}>
                    <span>
                      <strong>Payment source</strong>
                      ABA Telegram alert
                    </span>
                    <span>
                      <strong>Detected</strong>
                      {formatDate(order.payment.detected_at ?? order.payment.paid_at)}
                    </span>
                    <span>
                      <strong>Payer</strong>
                      {order.telegram_payment_alert?.payer_name ?? "Unknown"}
                      {order.telegram_payment_alert?.masked_account_digits
                        ? ` (*${order.telegram_payment_alert.masked_account_digits})`
                        : ""}
                    </span>
                    <span>
                      <strong>Trx ID</strong>
                      {order.telegram_payment_alert?.transaction_id ?? order.payment.transaction_reference ?? "—"}
                    </span>
                    <span>
                      <strong>APV</strong>
                      {order.telegram_payment_alert?.apv ?? "—"}
                    </span>
                    <span>
                      <strong>Verification</strong>
                      {order.telegram_payment_alert?.status.replace(/_/g, " ") ?? "missing alert"}
                    </span>
                  </div>
                )}

                <div className={styles.orderItems}>
                  {order.items.map((item) => (
                    <span key={item.id}>
                      {item.product_name} × {item.quantity}
                    </span>
                  ))}
                </div>

                <div className={styles.orderControls}>
                  <label>
                    <span>Order status</span>
                    <select
                      value={draft.status}
                      onChange={(event) =>
                        updateDraft(order, { status: event.target.value as OrderStatus })
                      }
                    >
                      {ORDER_STATUS_OPTIONS.map((status) => (
                        <option key={status} value={status}>
                          {status}
                        </option>
                      ))}
                    </select>
                  </label>
                  {order.payment.method === "cod" && <>
                    <label>
                      <span>Payment status</span>
                      <select
                        value={draft.payment_status}
                        onChange={(event) =>
                          updateDraft(order, {
                            payment_status: event.target.value as AdminPaymentStatus,
                          })
                        }
                      >
                        {PAYMENT_STATUS_OPTIONS.map((status) => (
                          <option key={status} value={status}>
                            {status}
                          </option>
                        ))}
                      </select>
                    </label>
                    <label>
                      <span>Payment reference</span>
                      <input
                        type="text"
                        value={draft.transaction_reference}
                        onChange={(event) =>
                          updateDraft(order, { transaction_reference: event.target.value })
                        }
                      />
                    </label>
                  </>}
                  <label className={styles.fullField}>
                    <span>Admin note</span>
                    <textarea
                      rows={2}
                      value={draft.admin_note}
                      onChange={(event) => updateDraft(order, { admin_note: event.target.value })}
                    />
                  </label>
                  <button
                    className={styles.primaryButton}
                    type="button"
                    disabled={savingOrder === order.order_number}
                    onClick={() => saveOrder(order)}
                  >
                    {savingOrder === order.order_number ? <RefreshCw size={16} /> : <Save size={16} />}
                    Update order
                  </button>
                  {order.payment.source === "telegram_aba_alert" && (
                    <div className={styles.orderQuickActions}>
                      <button
                        className={styles.secondaryButton}
                        type="button"
                        disabled={savingOrder === order.order_number}
                        onClick={() =>
                          quickUpdateOrder(order, {
                            status: "confirmed",
                            admin_note: appendNote(
                              draft.admin_note,
                              "Verified for shipping from Admin dashboard.",
                            ),
                          })
                        }
                      >
                        <CheckCircle2 size={16} />
                        Verified for shipping
                      </button>
                      <button
                        className={styles.secondaryButton}
                        type="button"
                        disabled={savingOrder === order.order_number}
                        onClick={() =>
                          quickUpdateOrder(order, {
                            status: "pending",
                            admin_note: appendNote(
                              draft.admin_note,
                              "Needs review before shipping.",
                            ),
                          })
                        }
                      >
                        <AlertTriangle size={16} />
                        Needs review
                      </button>
                    </div>
                  )}
                </div>
              </article>
            );
          })}
        </div>
      </div>
    </section>
  );
}
