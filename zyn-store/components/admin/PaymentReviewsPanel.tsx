"use client";

import { useCallback, useEffect, useState } from "react";
import { fetchPaymentReviews, rejectCheckoutPayment, type PaymentReview } from "./admin-api";
import styles from "./admin.module.css";
import { PaymentReceiptEvidence } from "./PaymentReceiptEvidence";

export function PaymentReviewsPanel({ token, onError, onVerified }: {
  token: string;
  onError: (error: unknown) => void;
  onVerified: () => void;
}) {
  const [reviews, setReviews] = useState<PaymentReview[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(false);
  const [notice, setNotice] = useState("");
  const load = useCallback(async () => {
    setLoading(true);
    try {
      const result = await fetchPaymentReviews(token, page);
      setReviews(result.data);
      setLastPage(result.meta.last_page);
      if (page > result.meta.last_page) setPage(result.meta.last_page);
    } catch (error) { onError(error); }
    finally { setLoading(false); }
  }, [token, page, onError]);
  useEffect(() => { const timer = window.setTimeout(() => void load(), 0); return () => window.clearTimeout(timer); }, [load]);

  return <section className={styles.panelStack}>
    <div className={styles.panelHeading}>
      <h3>Payment reviews</h3>
      <button className={styles.secondaryButton} type="button" onClick={() => void load()} disabled={loading}>Refresh claims</button>
    </div>
    <p>These receipt claims are waiting for the authenticated Telegram payment alert with the exact same transaction ID, amount, and currency. Payment cannot be approved manually here.</p>
    {notice && <p role="status">{notice}</p>}
    {!loading && reviews.length === 0 && <p className={styles.emptyState}>No payments awaiting review.</p>}
    {reviews.map(review => <PaymentReviewCard key={review.token} review={review} token={token} onError={onError} onRejected={() => {
      setReviews(current => current.filter(item => item.token !== review.token));
      setNotice(`Claim rejected for ${review.customer.name}.`);
      onVerified();
      void load();
    }} />)}
    <div className={styles.reviewPagination}>
      <button type="button" className={styles.secondaryButton} disabled={loading || page <= 1} onClick={() => setPage(page - 1)}>Previous</button>
      <span>Page {page} of {lastPage}</span>
      <button type="button" className={styles.secondaryButton} disabled={loading || page >= lastPage} onClick={() => setPage(page + 1)}>Next</button>
    </div>
  </section>;
}

function PaymentReviewCard({ review, token, onError, onRejected }: {
  review: PaymentReview; token: string; onError: (error: unknown) => void; onRejected: () => void;
}) {
  const [saving, setSaving] = useState(false);
  const [rejectionReason, setRejectionReason] = useState("");
  return <article className={styles.paymentReviewCard}>
    <h4>{review.customer.name} · {review.customer.phone}</h4>
    <p>{review.customer.province}, {review.customer.district} · {review.customer.address_note}</p>
    <p>{review.items.map(item => `${item.product_name} × ${item.quantity}`).join(", ")}</p>
    <strong>Expected: {(review.total_cents / 100).toFixed(2)} {review.currency}</strong>
    <p><strong>Receipt received.</strong> Waiting for the matching authenticated Telegram alert.</p>
    <p>Submitted: {new Date(review.payment_claimed_at).toLocaleString()}</p>
    {review.has_receipt && <PaymentReceiptEvidence token={token} sessionToken={review.token} onError={onError} />}
    <p><strong>Verification:</strong> Telegram bot only. Refresh after the matching ABA alert arrives.</p>
    <details>
      <summary>Reject an invalid or duplicate claim</summary>
      <label>Reason for rejection<input value={rejectionReason} onChange={event => setRejectionReason(event.target.value)} maxLength={1000} disabled={saving} /></label>
      <button type="button" className={styles.secondaryButton} disabled={saving || rejectionReason.trim().length < 6} onClick={async () => {
        setSaving(true);
        try { await rejectCheckoutPayment(token, review.token, rejectionReason.trim()); onRejected(); }
        catch (error) { onError(error); }
        finally { setSaving(false); }
      }}>Reject claim</button>
    </details>
  </article>;
}
