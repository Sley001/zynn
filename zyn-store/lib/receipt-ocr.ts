import { extractReceiptTransactionReference } from "./receipt-reference";

const OCR_ASSET_BASE = "/ocr/v7";

/** OCR is a convenience for entering a claim, never evidence that a payment is genuine. */
export async function scanReceiptTransactionReference(
  receipt: File,
  signal: AbortSignal,
  onProgress: (progress: number) => void,
): Promise<string | null> {
  const { createWorker, PSM } = await import("tesseract.js");
  const active: { worker: Awaited<ReturnType<typeof createWorker>> | null } = { worker: null };
  let stopped = false;
  let rejectStopped: (error: Error) => void = () => {};
  const interruption = new Promise<never>((_, reject) => { rejectStopped = reject; });
  const stop = () => {
    stopped = true;
    void active.worker?.terminate().catch(() => {});
    rejectStopped(new Error("Receipt scan stopped"));
  };
  signal.addEventListener("abort", stop, { once: true });
  const timeout = window.setTimeout(stop, 90000);
  const recognize = async () => {
    if (signal.aborted) throw new Error("Receipt scan stopped");
    active.worker = await createWorker(["eng", "khm"], 1, {
      workerPath: `${OCR_ASSET_BASE}/worker.min.js`,
      corePath: `${OCR_ASSET_BASE}/core`,
      langPath: `${OCR_ASSET_BASE}/lang`,
      workerBlobURL: false,
      errorHandler: stop,
      logger: (message) => {
        if (!stopped && message.status === "recognizing text") onProgress(message.progress);
      },
    });
    if (stopped || signal.aborted) {
      await active.worker.terminate();
      throw new Error("Receipt scan stopped");
    }
    await active.worker.setParameters({ tessedit_pageseg_mode: PSM.AUTO });
    const { data } = await active.worker.recognize(receipt);
    return extractReceiptTransactionReference(data.text);
  };
  try {
    return await Promise.race([recognize(), interruption]);
  } finally {
    stopped = true;
    window.clearTimeout(timeout);
    signal.removeEventListener("abort", stop);
    await active.worker?.terminate().catch(() => {});
  }
}
