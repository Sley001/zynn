"use client";

import {
  ImageUp,
  PackagePlus,
  Pencil,
  RefreshCw,
  Save,
  Search,
  Trash2,
  X,
} from "lucide-react";
import { ChangeEvent, FormEvent, useMemo, useState } from "react";

import { deleteAdminProduct, saveAdminProduct, type ProductFormPayload } from "./admin-api";
import styles from "./admin.module.css";
import type { AdminProduct, ProductStatus } from "./types";
import { PRODUCT_STATUS_OPTIONS } from "./types";

type ProductsPanelProps = {
  token: string;
  products: AdminProduct[];
  onProductSaved: (product: AdminProduct) => void;
  onProductDeleted: (slug: string) => void;
  onError: (error: unknown) => void;
  formatCurrency: (value: number) => string;
};

type ProductFormState = {
  originalSlug?: string;
  name: string;
  slug: string;
  description: string;
  strengthMg: string;
  price: string;
  color: string;
  accent: string;
  origin: string;
  releaseDuration: string;
  notesText: string;
  status: ProductStatus;
  stockQuantity: string;
  trackStock: boolean;
  isActive: boolean;
  sortOrder: string;
  imageFile: File | null;
  removeImage: boolean;
};

const EMPTY_FORM: ProductFormState = {
  name: "",
  slug: "",
  description: "",
  strengthMg: "6",
  price: "4.00",
  color: "#1c4a3e",
  accent: "#c9a227",
  origin: "Sweden",
  releaseDuration: "30-45 min",
  notesText: "",
  status: "available",
  stockQuantity: "0",
  trackStock: true,
  isActive: true,
  sortOrder: "0",
  imageFile: null,
  removeImage: false,
};

function slugify(value: string) {
  return value
    .toLowerCase()
    .trim()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");
}

function productToForm(product: AdminProduct): ProductFormState {
  return {
    originalSlug: product.slug,
    name: product.name,
    slug: product.slug,
    description: product.description ?? "",
    strengthMg: String(product.strength_mg),
    price: product.price.toFixed(2),
    color: product.color ?? "#1c4a3e",
    accent: product.accent ?? "#c9a227",
    origin: product.origin ?? "",
    releaseDuration: product.release_duration ?? "",
    notesText: product.notes.join("\n"),
    status: product.status,
    stockQuantity: String(product.stock_quantity ?? 0),
    trackStock: Boolean(product.track_stock),
    isActive: Boolean(product.is_active),
    sortOrder: String(product.sort_order ?? 0),
    imageFile: null,
    removeImage: false,
  };
}

function toPayload(form: ProductFormState): ProductFormPayload {
  return {
    originalSlug: form.originalSlug,
    name: form.name,
    slug: form.slug || slugify(form.name),
    description: form.description,
    strengthMg: Number(form.strengthMg),
    price: Number(form.price),
    color: form.color,
    accent: form.accent,
    origin: form.origin,
    releaseDuration: form.releaseDuration,
    notesText: form.notesText,
    status: form.status,
    stockQuantity: Number(form.stockQuantity),
    trackStock: form.trackStock,
    isActive: form.isActive,
    sortOrder: Number(form.sortOrder),
    imageFile: form.imageFile,
    removeImage: form.removeImage,
  };
}

export function ProductsPanel({
  token,
  products,
  onProductSaved,
  onProductDeleted,
  onError,
  formatCurrency,
}: ProductsPanelProps) {
  const [form, setForm] = useState<ProductFormState>(EMPTY_FORM);
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState<"all" | ProductStatus>("all");
  const [isSaving, setIsSaving] = useState(false);
  const [deletingSlug, setDeletingSlug] = useState("");
  const [notice, setNotice] = useState("");

  const filteredProducts = useMemo(() => {
    const query = search.trim().toLowerCase();

    return products.filter((product) => {
      const matchesSearch =
        !query ||
        product.name.toLowerCase().includes(query) ||
        product.slug.toLowerCase().includes(query);
      const matchesStatus = statusFilter === "all" || product.status === statusFilter;
      return matchesSearch && matchesStatus;
    });
  }, [products, search, statusFilter]);

  const updateForm = <Key extends keyof ProductFormState>(key: Key, value: ProductFormState[Key]) => {
    setForm((current) => ({ ...current, [key]: value }));
  };

  const startCreate = () => {
    setForm(EMPTY_FORM);
    setNotice("");
  };

  const submitProduct = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setIsSaving(true);
    setNotice("");

    try {
      const savedProduct = await saveAdminProduct(token, toPayload(form));
      onProductSaved(savedProduct);
      setForm(productToForm(savedProduct));
      setNotice(`${savedProduct.name} saved.`);
    } catch (error) {
      onError(error);
    } finally {
      setIsSaving(false);
    }
  };

  const deleteProduct = async (product: AdminProduct) => {
    const confirmed = window.confirm(`Delete ${product.name}? This cannot be undone.`);
    if (!confirmed) return;

    setDeletingSlug(product.slug);
    setNotice("");

    try {
      await deleteAdminProduct(token, product.slug);
      onProductDeleted(product.slug);
      if (form.originalSlug === product.slug) startCreate();
      setNotice(`${product.name} deleted.`);
    } catch (error) {
      onError(error);
    } finally {
      setDeletingSlug("");
    }
  };

  const chooseImage = (event: ChangeEvent<HTMLInputElement>) => {
    updateForm("imageFile", event.target.files?.[0] ?? null);
  };

  return (
    <section className={styles.panelStack} aria-labelledby="products-title">
      <div className={styles.sectionTitle}>
        <div>
          <p className={styles.eyebrow}>Products</p>
          <h2 id="products-title">Catalog and inventory</h2>
        </div>
        <button className={styles.secondaryButton} type="button" onClick={startCreate}>
          <PackagePlus size={16} />
          New product
        </button>
      </div>

      <div className={styles.splitPanel}>
        <section className={styles.dataPanel} aria-label="Product list">
          <div className={styles.filters}>
            <label className={styles.searchBox}>
              <Search size={16} />
              <input
                type="search"
                placeholder="Search products"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
              />
            </label>
            <select
              aria-label="Filter products by status"
              value={statusFilter}
              onChange={(event) => setStatusFilter(event.target.value as "all" | ProductStatus)}
            >
              <option value="all">All statuses</option>
              {PRODUCT_STATUS_OPTIONS.map((status) => (
                <option key={status} value={status}>
                  {status}
                </option>
              ))}
            </select>
          </div>

          <div className={styles.tableWrap}>
            <table className={styles.dataTable}>
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Price</th>
                  <th>Stock</th>
                  <th>Status</th>
                  <th>Visible</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                {filteredProducts.map((product) => (
                  <tr key={product.id}>
                    <td>
                      <div className={styles.productCell}>
                        <span
                          style={{
                            background: product.color ?? "#1c4a3e",
                            borderColor: product.accent ?? "#c9a227",
                          }}
                        />
                        <div>
                          <strong>{product.name}</strong>
                          <small>{product.slug}</small>
                        </div>
                      </div>
                    </td>
                    <td>{formatCurrency(product.price)}</td>
                    <td>{product.track_stock ? product.stock_quantity ?? 0 : "Untracked"}</td>
                    <td>
                      <span className={styles.statusPill} data-status={product.status}>
                        {product.status}
                      </span>
                    </td>
                    <td>{product.is_active ? "Yes" : "No"}</td>
                    <td>
                      <div className={styles.iconActions}>
                        <button
                          type="button"
                          title={`Edit ${product.name}`}
                          aria-label={`Edit ${product.name}`}
                          onClick={() => {
                            setForm(productToForm(product));
                            setNotice("");
                          }}
                        >
                          <Pencil size={15} />
                        </button>
                        <button
                          type="button"
                          title={`Delete ${product.name}`}
                          aria-label={`Delete ${product.name}`}
                          disabled={deletingSlug === product.slug}
                          onClick={() => deleteProduct(product)}
                        >
                          {deletingSlug === product.slug ? <RefreshCw size={15} /> : <Trash2 size={15} />}
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        <form className={styles.productForm} onSubmit={submitProduct}>
          <div className={styles.panelHeading}>
            <h3>{form.originalSlug ? "Edit product" : "Add product"}</h3>
            {form.originalSlug && (
              <button type="button" title="Clear form" aria-label="Clear form" onClick={startCreate}>
                <X size={16} />
              </button>
            )}
          </div>

          <div className={styles.formGrid}>
            <label className={styles.fullField}>
              <span>Name</span>
              <input
                type="text"
                value={form.name}
                onChange={(event) => {
                  updateForm("name", event.target.value);
                  if (!form.originalSlug) updateForm("slug", slugify(event.target.value));
                }}
                required
              />
            </label>
            <label className={styles.fullField}>
              <span>Slug</span>
              <input
                type="text"
                value={form.slug}
                onChange={(event) => updateForm("slug", slugify(event.target.value))}
                required
              />
            </label>
            <label>
              <span>Price</span>
              <input
                type="number"
                min="0.01"
                step="0.01"
                value={form.price}
                onChange={(event) => updateForm("price", event.target.value)}
                required
              />
            </label>
            <label>
              <span>Strength mg</span>
              <input
                type="number"
                min="1"
                max="100"
                value={form.strengthMg}
                onChange={(event) => updateForm("strengthMg", event.target.value)}
                required
              />
            </label>
            <label>
              <span>Stock</span>
              <input
                type="number"
                min="0"
                value={form.stockQuantity}
                onChange={(event) => updateForm("stockQuantity", event.target.value)}
                required
              />
            </label>
            <label>
              <span>Status</span>
              <select
                value={form.status}
                onChange={(event) => updateForm("status", event.target.value as ProductStatus)}
              >
                {PRODUCT_STATUS_OPTIONS.map((status) => (
                  <option key={status} value={status}>
                    {status}
                  </option>
                ))}
              </select>
            </label>
            <label>
              <span>Color</span>
              <input
                type="color"
                value={form.color}
                onChange={(event) => updateForm("color", event.target.value)}
              />
            </label>
            <label>
              <span>Accent</span>
              <input
                type="color"
                value={form.accent}
                onChange={(event) => updateForm("accent", event.target.value)}
              />
            </label>
            <label>
              <span>Origin</span>
              <input
                type="text"
                value={form.origin}
                onChange={(event) => updateForm("origin", event.target.value)}
              />
            </label>
            <label>
              <span>Release</span>
              <input
                type="text"
                value={form.releaseDuration}
                onChange={(event) => updateForm("releaseDuration", event.target.value)}
              />
            </label>
            <label className={styles.fullField}>
              <span>Description</span>
              <textarea
                rows={3}
                value={form.description}
                onChange={(event) => updateForm("description", event.target.value)}
              />
            </label>
            <label className={styles.fullField}>
              <span>Notes</span>
              <textarea
                rows={3}
                value={form.notesText}
                onChange={(event) => updateForm("notesText", event.target.value)}
                placeholder="One note per line"
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
              Visible in storefront
            </label>
            <label>
              <input
                type="checkbox"
                checked={form.trackStock}
                onChange={(event) => updateForm("trackStock", event.target.checked)}
              />
              Track stock
            </label>
            <label>
              <input
                type="checkbox"
                checked={form.removeImage}
                onChange={(event) => updateForm("removeImage", event.target.checked)}
              />
              Remove current image
            </label>
          </div>

          <label className={styles.filePicker}>
            <ImageUp size={18} />
            <span>{form.imageFile ? form.imageFile.name : "Upload product image"}</span>
            <input type="file" accept="image/png,image/jpeg,image/webp" onChange={chooseImage} />
          </label>

          {notice && <p className={styles.successMessage}>{notice}</p>}
          <button className={styles.primaryButton} type="submit" disabled={isSaving}>
            {isSaving ? <RefreshCw size={16} /> : <Save size={16} />}
            {isSaving ? "Saving" : "Save product"}
          </button>
        </form>
      </div>
    </section>
  );
}
