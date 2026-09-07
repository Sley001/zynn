import { copyFile, mkdir, readdir, writeFile } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import path from "node:path";

const root = fileURLToPath(new URL("../", import.meta.url));
const output = path.join(root, "public/ocr/v7");
const core = path.join(root, "node_modules/tesseract.js-core");
await mkdir(path.join(output, "core"), { recursive: true });
await mkdir(path.join(output, "lang"), { recursive: true });
await copyFile(path.join(root, "node_modules/tesseract.js/dist/worker.min.js"), path.join(output, "worker.min.js"));
await copyFile(path.join(root, "node_modules/tesseract.js/LICENSE.md"), path.join(output, "LICENSE-tesseract.txt"));
await copyFile(path.join(core, "LICENSE"), path.join(output, "LICENSE-core.txt"));
// Include every embedded-WASM build: the OCR worker selects the one supported
// by the customer's browser. No receipt image is sent to a third-party OCR API.
for (const name of await readdir(core)) {
  if (name.endsWith(".wasm.js")) await copyFile(path.join(core, name), path.join(output, "core", name));
}
for (const language of ["eng", "khm"]) {
  await copyFile(
    path.join(root, `node_modules/@tesseract.js-data/${language}/4.0.0_best_int/${language}.traineddata.gz`),
    path.join(output, "lang", `${language}.traineddata.gz`),
  );
}
await writeFile(path.join(output, "NOTICE.txt"), "Tesseract.js 7 / Tesseract.js-core 7: Apache-2.0, https://github.com/naptha/tesseract.js\nEnglish and Khmer traineddata distributed by @tesseract.js-data packages (package license: MIT), https://github.com/naptha/tessdata\n");
console.log("Prepared local English and Khmer receipt OCR assets.");
