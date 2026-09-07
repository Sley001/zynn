"use client";

/* eslint-disable @next/next/no-img-element */

import { useEffect, useRef, useState } from "react";
import { Camera, Loader2, Upload, X } from "lucide-react";
import type { StoreCopy } from "@/content/store-copy";
import styles from "./storefront.module.css";

export type ReceiptClaim = { receipt: File | null; scanning: boolean };

export function PaymentReceiptUpload({ copy, disabled, value, onChange, onScanComplete }: {
  copy: StoreCopy;
  disabled: boolean;
  value: ReceiptClaim;
  onChange: (value: ReceiptClaim) => void;
  onScanComplete: (receipt: File, detectedReference: string) => void;
}) {
  const previewImage = useRef<HTMLImageElement | null>(null);
  const [message, setMessage] = useState("");
  const [progress, setProgress] = useState(0);
  const generation = useRef(0);
  const abortScan = useRef<AbortController | null>(null);

  useEffect(() => {
    if (!value.receipt) return;
    const url = URL.createObjectURL(value.receipt);
    if (previewImage.current) previewImage.current.src = url;
    return () => URL.revokeObjectURL(url);
  }, [value.receipt]);

  useEffect(() => () => {
    generation.current += 1;
    abortScan.current?.abort();
  }, []);

  const chooseReceipt = async (file?: File) => {
    if (!file) return;
    const current = ++generation.current;
    abortScan.current?.abort();
    const controller = new AbortController();
    abortScan.current = controller;
    onChange({ receipt: null, scanning: true });
    setMessage("");
    setProgress(0);
    if (!["image/jpeg", "image/png", "image/webp"].includes(file.type) || file.size > 5 * 1024 * 1024 || file.size === 0) {
      onChange({ receipt: null, scanning: false });
      setMessage(copy.receiptInvalid);
      return;
    }
    const url = URL.createObjectURL(file);
    try {
      const image = new Image();
      image.src = url;
      await image.decode();
      if (!image.naturalWidth || !image.naturalHeight || image.naturalWidth > 6000 || image.naturalHeight > 6000 || image.naturalWidth * image.naturalHeight > 16000000) {
        throw new Error("Invalid dimensions");
      }
    } catch {
      if (current === generation.current) {
        onChange({ receipt: null, scanning: false });
        setMessage(copy.receiptInvalid);
      }
      return;
    } finally { URL.revokeObjectURL(url); }
    if (current !== generation.current) return;
    onChange({ receipt: file, scanning: true });
    try {
      const { scanReceiptTransactionReference } = await import("@/lib/receipt-ocr");
      if (controller.signal.aborted) return;
      const reference = await scanReceiptTransactionReference(file, controller.signal, nextProgress => {
        if (current === generation.current) setProgress(Math.round(nextProgress * 100));
      });
      if (current !== generation.current) return;
      onChange({ receipt: file, scanning: false });
      setMessage(reference ? copy.receiptScanFound : copy.receiptScanNotFound);
      if (reference) onScanComplete(file, reference);
    } catch {
      if (current !== generation.current) return;
      onChange({ receipt: file, scanning: false });
      setMessage(copy.receiptScanFailed);
    }
  };

  return <section className={styles.receiptUpload} aria-labelledby="receipt-upload-title" aria-busy={value.scanning}>
    <h4 id="receipt-upload-title">{copy.receiptUploadTitle}</h4>
    <p id="receipt-upload-help">{copy.receiptUploadHelp}</p>
    <div className={styles.receiptUploadActions}>
      <label className={styles.secondaryButton}>
        <Upload size={17} aria-hidden="true" />{value.receipt ? copy.receiptReplace : copy.receiptChoosePhoto}
        <input className={styles.receiptFileInput} type="file" accept="image/jpeg,image/png,image/webp" disabled={disabled}
          aria-describedby="receipt-upload-help" onChange={event => { void chooseReceipt(event.target.files?.[0]); event.target.value = ""; }} />
      </label>
      <label className={styles.secondaryButton}>
        <Camera size={17} aria-hidden="true" />{copy.receiptTakePhoto}
        <input className={styles.receiptFileInput} type="file" accept="image/jpeg,image/png,image/webp" capture="environment" disabled={disabled}
          aria-describedby="receipt-upload-help" onChange={event => { void chooseReceipt(event.target.files?.[0]); event.target.value = ""; }} />
      </label>
    </div>
    {value.receipt && <div className={styles.receiptPreview}>
      <img ref={previewImage} alt={copy.receiptPreviewAlt} />
      <button type="button" className={styles.secondaryButton} disabled={disabled} onClick={() => {
        generation.current += 1;
        abortScan.current?.abort();
        onChange({ receipt: null, scanning: false });
        setMessage("");
      }}><X size={16} aria-hidden="true" />{copy.receiptRemove}</button>
    </div>}
    <p role="status" aria-live="polite">
      {value.scanning ? <><Loader2 size={16} aria-hidden="true" /> {copy.receiptScanning} {progress > 0 ? `${progress}%` : ""}</> : message}
    </p>
  </section>;
}
