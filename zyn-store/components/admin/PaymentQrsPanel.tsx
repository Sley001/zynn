"use client";

/* eslint-disable @next/next/no-img-element */

import {
  ImageUp,
  Pencil,
  QrCode,
  RefreshCw,
  Save,
  ToggleLeft,
  ToggleRight,
  Trash2,
  X,
} from "lucide-react";
import { ChangeEvent, FormEvent, useMemo, useState } from "react";

import {
  deleteAdminPaymentQr,
  saveAdminPaymentQr,
  updateAdminStoreSettings,
  type PaymentQrFormPayload,
} from "./admin-api";
import styles from "./admin.module.css";
import type { AdminPaymentQr, AdminProduct, AdminStoreSettings } from "./types";

type PaymentQrsPanelProps = {
  token: string;
  paymentQrs: AdminPaymentQr[];
  products: AdminProduct[];
  settings: AdminStoreSettings;
  onPaymentQrSaved: (paymentQr: AdminPaymentQr) => void;
  onPaymentQrDeleted: (id: number) => void;
  onSettingsSaved: (settings: AdminStoreSettings) => void;
  onError: (error: unknown) => void;
  formatCurrency: (value: number) => string;
};

type PaymentQrFormState = {
  id?: number;
  amount: string;
  currency: string;
  imageFile: File | null;
  isActive: boolean;
  adminNote: string;
};

const EMPTY_FORM: PaymentQrFormState = {
  amount: "0.00",
  currency: "USD",
  imageFile: null,
  isActive: true,
  adminNote: "",
};

function centsToAmount(cents: number) {
  return (cents / 100).toFixed(2);
}

function amountToCents(amount: number) {
  return Math.round(amount * 100);
}

function qrToForm(qr: AdminPaymentQr): PaymentQrFormState {
  return {
    id: qr.id,
    amount: centsToAmount(qr.amount_cents),
    currency: qr.currency,
    imageFile: null,
    isActive: qr.is_active,
    adminNote: qr.admin_note ?? "",
  };
}

function toPayload(form: PaymentQrFormState): PaymentQrFormPayload {
  return {
    id: form.id,
    amount: form.amount,
    currency: form.currency,
    imageFile: form.imageFile,
    isActive: form.isActive,
    adminNote: form.adminNote,
  };
}

export function PaymentQrsPanel({
  token,
  paymentQrs,
  products,
  settings,
  onPaymentQrSaved,
  onPaymentQrDeleted,
  onSettingsSaved,
  onError,
  formatCurrency,
}: PaymentQrsPanelProps) {
  const [form, setForm] = useState<PaymentQrFormState>(EMPTY_FORM);
  const [deliveryFee, setDeliveryFee] = useState(centsToAmount(settings.deliveryFeeCents));
  const [checkoutSessionLifetimeMinutes, setCheckoutSessionLifetimeMinutes] = useState(
    String(settings.checkoutSessionLifetimeMinutes),
  );
  const [isSavingQr, setIsSavingQr] = useState(false);
  const [savingQrId, setSavingQrId] = useState<number | null>(null);
  const [deletingQrId, setDeletingQrId] = useState<number | null>(null);
  const [isSavingSettings, setIsSavingSettings] = useState(false);
  const [notice, setNotice] = useState("");

  const activeQrs = useMemo(
    () => paymentQrs.filter((qr) => qr.is_active),
    [paymentQrs],
  );
  const previewRows = useMemo(() => {
    const orderableProducts = products.filter((product) => product.is_orderable);

    return orderableProducts.flatMap((product) =>
      Array.from({ length: 10 }, (_, index) => {
        const quantity = index + 1;
        const total = product.price * quantity + settings.deliveryFee;
        const totalCents = amountToCents(total);
        const matchingQr = activeQrs.find(
          (qr) => qr.currency === settings.currency && qr.amount_cents === totalCents,
        );

        return {
          key: `${product.id}-${quantity}`,
          productName: product.name,
          quantity,
          total,
          totalCents,
          matchingQr,
        };
      }),
    );
  }, [activeQrs, products, settings.currency, settings.deliveryFee]);

  const updateForm = <Key extends keyof PaymentQrFormState>(
    key: Key,
    value: PaymentQrFormState[Key],
  ) => setForm((current) => ({ ...current, [key]: value }));

  const chooseImage = (event: ChangeEvent<HTMLInputElement>) => {
    updateForm("imageFile", event.target.files?.[0] ?? null);
  };

  const resetForm = () => {
    setForm(EMPTY_FORM);
    setNotice("");
  };

  const submitPaymentQr = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setIsSavingQr(true);
    setNotice("");

    try {
      const savedQr = await saveAdminPaymentQr(token, toPayload(form));
      onPaymentQrSaved(savedQr);
      setForm(qrToForm(savedQr));
      setNotice(`QR ${savedQr.currency} ${centsToAmount(savedQr.amount_cents)} saved.`);
    } catch (error) {
      onError(error);
    } finally {
      setIsSavingQr(false);
    }
  };

  const togglePaymentQr = async (qr: AdminPaymentQr) => {
    setSavingQrId(qr.id);
    setNotice("");

    try {
      const savedQr = await saveAdminPaymentQr(token, {
        id: qr.id,
        amount: centsToAmount(qr.amount_cents),
        currency: qr.currency,
        imageFile: null,
        isActive: !qr.is_active,
        adminNote: qr.admin_note ?? "",
      });
      onPaymentQrSaved(savedQr);
    } catch (error) {
      onError(error);
    } finally {
      setSavingQrId(null);
    }
  };

  const deletePaymentQr = async (qr: AdminPaymentQr) => {
    const confirmed = window.confirm(`Delete QR ${qr.currency} ${centsToAmount(qr.amount_cents)}?`);
    if (!confirmed) return;

    setDeletingQrId(qr.id);
    setNotice("");

    try {
      await deleteAdminPaymentQr(token, qr.id);
      onPaymentQrDeleted(qr.id);
      if (form.id === qr.id) resetForm();
      setNotice("Payment QR deleted.");
    } catch (error) {
      onError(error);
    } finally {
      setDeletingQrId(null);
    }
  };

  const saveStoreSettings = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setIsSavingSettings(true);
    setNotice("");

    try {
      const savedSettings = await updateAdminStoreSettings(token, {
        deliveryFee,
        checkoutSessionLifetimeMinutes: Number(checkoutSessionLifetimeMinutes),
      });
      onSettingsSaved(savedSettings);
      setDeliveryFee(centsToAmount(savedSettings.deliveryFeeCents));
      setCheckoutSessionLifetimeMinutes(String(savedSettings.checkoutSessionLifetimeMinutes));
      setNotice("Checkout settings saved.");
    } catch (error) {
      onError(error);
    } finally {
      setIsSavingSettings(false);
    }
  };

  return (
    <section className={styles.panelStack} aria-labelledby="payment-qrs-title">
      <div className={styles.sectionTitle}>
        <div>
          <p className={styles.eyebrow}>Payment QR</p>
          <h2 id="payment-qrs-title">Fixed-amount QR manager</h2>
        </div>
        <p>{activeQrs.length} active QR records</p>
      </div>

      <div className={styles.splitPanel}>
        <section className={styles.dataPanel} aria-label="Payment QR list">
          <div className={styles.panelHeading}>
            <h3>Saved payment QRs</h3>
            <QrCode size={18} />
          </div>
          {paymentQrs.length === 0 ? (
            <p className={styles.emptyState}>Upload fixed-amount QR images before customers can create checkout sessions.</p>
          ) : (
            <div className={styles.tableWrap}>
              <table className={styles.dataTable}>
                <thead>
                  <tr>
                    <th>QR</th>
                    <th>Amount</th>
                    <th>Currency</th>
                    <th>Status</th>
                    <th>Note</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {paymentQrs.map((qr) => (
                    <tr key={qr.id}>
                      <td>
                        <img className={styles.qrThumb} src={qr.image_url} alt={`QR ${qr.currency} ${qr.amount}`} />
                      </td>
                      <td>{qr.currency === "USD" ? formatCurrency(qr.amount_cents / 100) : qr.amount}</td>
                      <td>{qr.currency}</td>
                      <td>
                        <span className={styles.statusPill} data-status={qr.is_active ? "available" : "draft"}>
                          {qr.is_active ? "active" : "inactive"}
                        </span>
                      </td>
                      <td>{qr.admin_note || "—"}</td>
                      <td>
                        <div className={styles.iconActions}>
                          <button
                            type="button"
                            title={qr.is_active ? "Deactivate QR" : "Activate QR"}
                            aria-label={qr.is_active ? "Deactivate QR" : "Activate QR"}
                            disabled={savingQrId === qr.id}
                            onClick={() => togglePaymentQr(qr)}
                          >
                            {qr.is_active ? <ToggleRight size={15} /> : <ToggleLeft size={15} />}
                          </button>
                          <button
                            type="button"
                            title="Edit QR"
                            aria-label="Edit QR"
                            onClick={() => {
                              setForm(qrToForm(qr));
                              setNotice("");
                            }}
                          >
                            <Pencil size={15} />
                          </button>
                          <button
                            type="button"
                            title="Delete QR"
                            aria-label="Delete QR"
                            disabled={deletingQrId === qr.id}
                            onClick={() => deletePaymentQr(qr)}
                          >
                            {deletingQrId === qr.id ? <RefreshCw size={15} /> : <Trash2 size={15} />}
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>

        <div className={styles.panelStack}>
          <form className={styles.productForm} onSubmit={submitPaymentQr}>
            <div className={styles.panelHeading}>
              <h3>{form.id ? "Replace payment QR" : "Upload payment QR"}</h3>
              {form.id && (
                <button type="button" title="Clear form" aria-label="Clear form" onClick={resetForm}>
                  <X size={16} />
                </button>
              )}
            </div>
            <div className={styles.formGrid}>
              <label>
                <span>Exact amount</span>
                <input
                  type="number"
                  min="0.01"
                  step="0.01"
                  value={form.amount}
                  onChange={(event) => updateForm("amount", event.target.value)}
                  required
                />
              </label>
              <label>
                <span>Currency</span>
                <select
                  value={form.currency}
                  onChange={(event) => updateForm("currency", event.target.value)}
                >
                  <option value="USD">USD</option>
                  <option value="KHR">KHR</option>
                </select>
              </label>
              <label className={styles.fullField}>
                <span>Admin note</span>
                <textarea
                  rows={3}
                  value={form.adminNote}
                  onChange={(event) => updateForm("adminNote", event.target.value)}
                  placeholder="Optional internal note"
                />
              </label>
            </div>
            <div className={styles.toggleGrid}>
              <label>
                <input
                  type="checkbox"
                  checked={form.isActive}
                  onChange={(event) => updateForm("isActive", event.target.checked)}
                />
                Active for matching checkout totals
              </label>
            </div>
            <label className={styles.filePicker}>
              <ImageUp size={18} />
              <span>{form.imageFile ? form.imageFile.name : form.id ? "Replace QR image" : "Upload QR image"}</span>
              <input
                type="file"
                accept="image/png,image/jpeg,image/webp"
                onChange={chooseImage}
                required={!form.id}
              />
            </label>
            {notice && <p className={styles.successMessage}>{notice}</p>}
            <button className={styles.primaryButton} type="submit" disabled={isSavingQr}>
              {isSavingQr ? <RefreshCw size={16} /> : <Save size={16} />}
              {isSavingQr ? "Saving" : "Save QR"}
            </button>
          </form>

          <form className={styles.productForm} onSubmit={saveStoreSettings}>
            <div className={styles.panelHeading}>
              <h3>Checkout settings</h3>
              <Save size={18} />
            </div>
            <label>
              <span>Delivery fee ({settings.currency})</span>
              <input
                type="number"
                min="0"
                step="0.01"
                value={deliveryFee}
                onChange={(event) => setDeliveryFee(event.target.value)}
                required
              />
            </label>
            <label>
              <span>QR waiting time (minutes)</span>
              <input
                type="number"
                min="1"
                max="60"
                step="1"
                value={checkoutSessionLifetimeMinutes}
                onChange={(event) => setCheckoutSessionLifetimeMinutes(event.target.value)}
                required
              />
            </label>
            <button className={styles.primaryButton} type="submit" disabled={isSavingSettings}>
              {isSavingSettings ? <RefreshCw size={16} /> : <Save size={16} />}
              {isSavingSettings ? "Saving" : "Save settings"}
            </button>
          </form>
        </div>
      </div>

      <section className={styles.dataPanel} aria-labelledby="qr-preview-title">
        <div className={styles.panelHeading}>
          <h3 id="qr-preview-title">Expected totals for quantities 1-10</h3>
          <QrCode size={18} />
        </div>
        {previewRows.length === 0 ? (
          <p className={styles.emptyState}>No orderable products are available for QR preview.</p>
        ) : (
          <div className={styles.tableWrap}>
            <table className={styles.dataTable}>
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Qty</th>
                  <th>Total with delivery</th>
                  <th>QR status</th>
                </tr>
              </thead>
              <tbody>
                {previewRows.map((row) => (
                  <tr key={row.key}>
                    <td>{row.productName}</td>
                    <td>{row.quantity}</td>
                    <td>{formatCurrency(row.total)}</td>
                    <td>
                      <span
                        className={styles.statusPill}
                        data-status={row.matchingQr ? "available" : "failed"}
                      >
                        {row.matchingQr ? `QR #${row.matchingQr.id}` : "missing QR"}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </section>
  );
}
