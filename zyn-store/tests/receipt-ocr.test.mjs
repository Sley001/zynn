import assert from "node:assert/strict";
import test from "node:test";
import { fileURLToPath } from "node:url";
import { createWorker, PSM } from "tesseract.js";
import { extractReceiptTransactionReference } from "../lib/receipt-reference.ts";

test("reads the transaction ID from a synthetic receipt image using local English and Khmer models", { timeout: 90000 }, async () => {
  const worker = await createWorker(["eng", "khm"], 1, {
    langPath: fileURLToPath(new URL("../public/ocr/v7/lang", import.meta.url)),
    cacheMethod: "none",
    errorHandler: () => {},
  });
  try {
    await worker.setParameters({ tessedit_pageseg_mode: PSM.AUTO });
    const { data } = await worker.recognize(fileURLToPath(new URL("./fixtures/receipt-ocr.png", import.meta.url)));
    assert.equal(extractReceiptTransactionReference(data.text), "123456789012345");
  } finally { await worker.terminate(); }
});
