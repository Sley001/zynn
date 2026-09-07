"use client";

/* eslint-disable @next/next/no-img-element */

import { useEffect, useState } from "react";
import { fetchPaymentReceipt } from "./admin-api";
import styles from "./admin.module.css";

export function PaymentReceiptEvidence({ token, sessionToken, onError }: {
  token: string; sessionToken: string; onError: (error: unknown) => void;
}) {
  const [open, setOpen] = useState(false);
  return <div className={styles.receiptEvidence}>
    <button type="button" className={styles.secondaryButton} onClick={() => setOpen(!open)} aria-expanded={open}>
      {open ? "Hide receipt photo" : "View receipt photo"}
    </button>
    {open && <PrivateReceiptImage token={token} sessionToken={sessionToken} onError={onError} />}
  </div>;
}

function PrivateReceiptImage({ token, sessionToken, onError }: {
  token: string; sessionToken: string; onError: (error: unknown) => void;
}) {
  const [url, setUrl] = useState("");
  const [failed, setFailed] = useState(false);
  useEffect(() => {
    const controller = new AbortController();
    let objectUrl = "";
    void fetchPaymentReceipt(token, sessionToken, controller.signal).then(blob => {
      if (controller.signal.aborted) return;
      objectUrl = URL.createObjectURL(blob);
      setUrl(objectUrl);
    }).catch(error => {
      if (controller.signal.aborted) return;
      setFailed(true);
      onError(error);
    });
    return () => { controller.abort(); if (objectUrl) URL.revokeObjectURL(objectUrl); };
  }, [token, sessionToken, onError]);
  if (failed) return <p role="alert">Could not load receipt. Close and reopen to retry.</p>;
  if (!url) return <p role="status">Loading private receipt…</p>;
  return <a href={url} target="_blank" rel="noopener noreferrer" aria-label="Open full-size receipt photo">
    <img className={styles.receiptEvidenceImage} src={url} alt="Customer-uploaded payment receipt for transaction ID scan" />
  </a>;
}
