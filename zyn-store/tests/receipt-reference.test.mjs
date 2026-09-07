import assert from "node:assert/strict";
import test from "node:test";
import { extractReceiptTransactionReference } from "../lib/receipt-reference.ts";

test("extracts ABA Trx. ID without confusing APV, amount, or account digits", () => {
  assert.equal(
    extractReceiptTransactionReference(
      "$12.00 paid by Customer (*1234) on Sep 7, 1:23 PM via ABA PAY at ZYN. Trx. ID: 169978123456, APV: 987654.",
    ),
    "169978123456",
  );
});

test("maps an ABA transaction-detail receipt Purchase # to the Telegram Trx ID", () => {
  const actualOcrText = `
-0.02usD
LIV SEANGLY
លេខកូដប្រតិបត្តិការៈ 60831728943
APV: 272243
From account: Seangly8 (007 658 770)
Original amount: 0.02 USD
Purchase #: 178879502144726
Reference #: 100FT38918624080
Seller: LIV SEANGLY
Transaction date: Sep 07, 2026 10:30 PM
ABA BANK
`;

  assert.equal(extractReceiptTransactionReference(actualOcrText), "178879502144726");
});

test("recognizes safe Purchase # layouts without treating Reference # as a fallback", () => {
  assert.equal(extractReceiptTransactionReference("Purchase #\n178879502144726"), "178879502144726");
  assert.equal(
    extractReceiptTransactionReference("Purchase #: 178879502144726\nReference #: 100FT38918624080"),
    "178879502144726",
  );
  assert.equal(extractReceiptTransactionReference("Reference #: 100FT38918624080"), null);
});

test("rejects damaged, conflicting, or ambiguous Purchase # values", () => {
  assert.equal(extractReceiptTransactionReference("Purchase #: 1788795O2144726"), null);
  assert.equal(extractReceiptTransactionReference("Purchase #: 12345678901"), null);
  assert.equal(
    extractReceiptTransactionReference("Purchase #: 178879502144726\nPurchase #: 178879502144727"),
    null,
  );
  assert.equal(
    extractReceiptTransactionReference("Trx. ID: 178879502144725\nPurchase #: 178879502144726"),
    null,
  );
});

test("recognizes explicit transaction and reference label variants", () => {
  for (const label of [
    "Transaction ID",
    "Transaction No.",
    "Transaction Number",
    "Reference No.",
    "Reference ID",
    "Ref. No.",
    "Txn ID",
    "Trx. ID",
  ]) {
    assert.equal(
      extractReceiptTransactionReference(`${label}: abC-123456`),
      "ABC-123456",
      label,
    );
  }
});

test("finds the value on the line following its label", () => {
  assert.equal(
    extractReceiptTransactionReference(
      "ABA BANK\nTransaction ID\n123456789012\nAPV: 654321\nAmount: 12.00 USD",
    ),
    "123456789012",
  );
  assert.equal(extractReceiptTransactionReference("Trx. ID:\r\n\r\n123456789"), "123456789");
});

test("normalizes safe short digit fragments adjacent to an explicit label", () => {
  assert.equal(extractReceiptTransactionReference("Trx. ID: 123 456 789"), "123456789");
  assert.equal(extractReceiptTransactionReference("Transaction No.\n1234\n5678"), "12345678");
});

test("recognizes Khmer labels, Khmer digits, and zero-width OCR spaces", () => {
  assert.equal(extractReceiptTransactionReference("លេខប្រតិបត្តិការ៖ 123456789"), "123456789");
  assert.equal(extractReceiptTransactionReference("លេខប្រតិបត្តិការ: 123456789"), "123456789");
  assert.equal(extractReceiptTransactionReference("លេខប្រតិបត្តិការធនាគារ\n១២៣៤៥៦៧៨៩"), "123456789");
  assert.equal(extractReceiptTransactionReference("Trx.\u200b ID: 123456789"), "123456789");
});

test("rejects conflicting references but permits repeated copies of the same ID", () => {
  assert.equal(extractReceiptTransactionReference("Trx ID: 123456789\nTransaction ID: 987654321"), null);
  assert.equal(extractReceiptTransactionReference("Trx ID: 123456789 Reference No: 987654321"), null);
  assert.equal(extractReceiptTransactionReference("Trx ID: abc-123456\nReference No: ABC-123456"), "ABC-123456");
});

test("does not guess unlabelled digits or skip intervening receipt fields", () => {
  for (const text of [
    "ABA Receipt\n123456789\nAPV: 654321",
    "Account No: 123456789\nPIN: 654321\nAPV: 987654",
    "Transaction ID:\nAPV: 654321\n123456789",
    "Transaction ID: Account No: 123456789",
    "Transaction ID:\nAmount: 123456789 USD",
    "Transaction ID: missing\n123456789",
  ]) {
    assert.equal(extractReceiptTransactionReference(text), null, text);
  }
});

test("rejects amounts, dates, incomplete values, and invalid characters", () => {
  for (const value of [
    "123456.00",
    "123,456.00",
    "$123456",
    "2026-09-07",
    "2026 09 07",
    "07-09-2026",
    "07/09/2026",
    "12345",
    "SUCCESS",
    "ABC_123456",
    "-123456",
    "123456-",
    "1".repeat(101),
  ]) {
    assert.equal(extractReceiptTransactionReference(`Trx. ID: ${value}`), null, value);
  }
});

test("rejects multiple complete values instead of joining or selecting one", () => {
  assert.equal(extractReceiptTransactionReference("Trx. ID: 123456 789012"), null);
  assert.equal(extractReceiptTransactionReference("Trx. ID:\n123456789\n987654321"), null);
  assert.equal(extractReceiptTransactionReference("Trx. ID:\n123\n456\n789\n012\n345"), null);
});

test("rejects empty and oversized OCR output", () => {
  assert.equal(extractReceiptTransactionReference(""), null);
  assert.equal(extractReceiptTransactionReference(`Trx. ID: 123456789\n${"x".repeat(100_000)}`), null);
});
