const explicitTransactionLabel =
  /\b(?:transaction|trx|txn)\.?\s*(?:id|no\.?|number)\b\.?/gi;

const abaPurchaseLabel = /\bpurchase[ \t]*(?:#|(?:id|no\.?|number)\b\.?)/gi;

const fallbackReferenceLabel =
  /\b(?:reference|ref)\.?\s*(?:id|no\.?|number)\b\.?|លេខប្រតិបត្តិការ(?:[ \t]*ធនាគារ)?/gi;

const followingField =
  /(?:[,;]\s*|\s+)(?:(?:transaction|trx|txn|reference|ref)\.?\s*(?:id|no\.?|number)|purchase\s*(?:#|id|no\.?|number)|apv|approval(?:\s+code)?|pin|account(?:\s+(?:no\.?|number|name))?|acc\.?|amount|total|date|time|currency|status|from|to)\b/i;

type CandidateScan = {
  candidates: Set<string>;
  sawInvalidValue: boolean;
  sawLabel: boolean;
};

function referenceFromLines(lines: string[]): string | null {
  // Look only next to the label. Never search past another receipt field.
  let lineIndex = 0;
  let value = lines[lineIndex]?.trim() ?? "";
  if (!value) {
    lineIndex += 1;
    value = lines[lineIndex]?.trim() ?? "";
    if (!value) {
      lineIndex += 1;
      value = lines[lineIndex]?.trim() ?? "";
    }
  }

  value = value.split(followingField, 1)[0].trim().replace(/[.,;]$/, "");
  if (!value) return null;
  if (/^\d{4}[ \t]+\d{1,2}[ \t]+\d{1,2}$/.test(value)) return null;

  if (/^\d{1,5}$/.test(value)) {
    // Join short wrapped digit fragments, never two complete references.
    for (let offset = 1; offset <= 3; offset += 1) {
      const next = lines[lineIndex + offset]?.trim() ?? "";
      if (!/^\d{1,5}$/.test(next)) break;
      value += next;
    }
    if (/^\d{1,5}$/.test(lines[lineIndex + 4]?.trim() ?? "")) return null;
  } else if (/^\d+(?:[ \t]+\d+)+$/.test(value)) {
    const groups = value.split(/[ \t]+/);
    if (groups.some((group) => group.length > 5)) return null;
    value = groups.join("");
  }

  // A second unlabelled complete value directly below is ambiguous.
  if (/^[A-Za-z0-9-]{6,100}$/.test(lines[lineIndex + 1]?.trim() ?? "")) {
    const next = lines[lineIndex + 1].trim();
    if (/\d/.test(next)) return null;
  }

  if (!/^[A-Za-z0-9-]{6,100}$/.test(value) || !/\d/.test(value)) return null;
  if (!/^[A-Za-z0-9].*[A-Za-z0-9]$/.test(value)) return null;
  // Dates can satisfy the backend's reference format, but are not references.
  if (/^(?:\d{4}-\d{1,2}-\d{1,2}|\d{1,2}-\d{1,2}-\d{2,4})$/.test(value)) {
    return null;
  }

  return value.toUpperCase();
}

function scanLabelCandidates(
  text: string,
  label: RegExp,
  accepts: (candidate: string) => boolean = () => true,
): CandidateScan {
  const candidates = new Set<string>();
  let sawInvalidValue = false;
  let sawLabel = false;

  for (const match of text.matchAll(label)) {
    sawLabel = true;
    const valueStart = match.index + match[0].length;
    const remainder = text
      .slice(valueStart, valueStart + 1_024)
      .replace(/^[ \t]*[:：#=៖]?[ \t]*/, "");
    const candidate = referenceFromLines(remainder.split("\n", 6));

    if (!candidate || !accepts(candidate)) {
      sawInvalidValue = true;
      continue;
    }

    candidates.add(candidate);
  }

  return { candidates, sawInvalidValue, sawLabel };
}

/**
 * Extract a suggested bank reference from OCR text, not proof of payment.
 * Only explicitly labelled, unambiguous references are eligible for autofill.
 */
export function extractReceiptTransactionReference(text: string): string | null {
  // Bound work on OCR output and refuse truncation that could hide a conflict.
  if (!text || text.length > 100_000) return null;

  const normalized = text
    .normalize("NFKC")
    .replace(/[\u200B-\u200D\uFEFF]/g, "")
    .replace(/[០-៩]/g, (digit) => String(digit.charCodeAt(0) - 0x17e0))
    .replace(/\r\n?/g, "\n");
  const explicit = scanLabelCandidates(normalized, explicitTransactionLabel);
  const purchase = scanLabelCandidates(
    normalized,
    abaPurchaseLabel,
    (candidate) => /^\d{12,20}$/.test(candidate),
  );
  const fallback = scanLabelCandidates(normalized, fallbackReferenceLabel);

  if (explicit.candidates.size > 1 || purchase.candidates.size > 1) return null;

  if (explicit.candidates.size === 1) {
    // An explicit Trx/Transaction ID remains authoritative, but any different
    // labelled candidate makes the receipt ambiguous.
    const candidates = new Set([
      ...explicit.candidates,
      ...purchase.candidates,
      ...fallback.candidates,
    ]);
    return candidates.size === 1 ? [...candidates][0] : null;
  }

  if (purchase.sawLabel) {
    // ABA transaction-detail receipts expose the Telegram Trx ID as Purchase #.
    // Refuse damaged or conflicting Purchase values rather than correcting OCR.
    if (purchase.sawInvalidValue || purchase.candidates.size !== 1) return null;
    return [...purchase.candidates][0];
  }

  return fallback.candidates.size === 1 ? [...fallback.candidates][0] : null;
}
