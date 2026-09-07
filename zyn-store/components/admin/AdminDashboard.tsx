"use client";

import {
  BarChart3,
  Boxes,
  ClipboardList,
  LayoutDashboard,
  LogOut,
  QrCode,
  RefreshCw,
} from "lucide-react";
import { useCallback, useEffect, useMemo, useState } from "react";

import {
  AdminApiError,
  fetchAdminOrders,
  fetchAdminPaymentAlerts,
  fetchAdminPaymentQrs,
  fetchAdminProducts,
  fetchAdminStoreSettings,
  logoutAdmin,
} from "./admin-api";
import { AdminLogin } from "./AdminLogin";
import styles from "./admin.module.css";
import { AdminOverview } from "./AdminOverview";
import { OrdersPanel } from "./OrdersPanel";
import { PaymentQrsPanel } from "./PaymentQrsPanel";
import { PaymentReviewsPanel } from "./PaymentReviewsPanel";
import { ProductsPanel } from "./ProductsPanel";
import type {
  AdminOrder,
  AdminPaymentQr,
  AdminProduct,
  AdminStoreSettings,
  AdminTelegramPaymentAlert,
  AdminUser,
  DashboardSummary,
} from "./types";

type ActivePanel = "overview" | "orders" | "products" | "payment-qrs" | "payment-reviews";

const TOKEN_STORAGE_KEY = "zyn-admin-token";
const USER_STORAGE_KEY = "zyn-admin-user";

const navItems: readonly {
  id: ActivePanel;
  label: string;
  icon: typeof LayoutDashboard;
}[] = [
  { id: "overview", label: "Overview", icon: LayoutDashboard },
  { id: "orders", label: "Orders", icon: ClipboardList },
  { id: "payment-reviews", label: "Payment reviews", icon: ClipboardList },
  { id: "payment-qrs", label: "Payment QR", icon: QrCode },
  { id: "products", label: "Products", icon: Boxes },
];

const DEFAULT_STORE_SETTINGS: AdminStoreSettings = {
  currency: "USD",
  deliveryFee: 1.5,
  deliveryFeeCents: 150,
  checkoutSessionLifetimeMinutes: 15,
};

function formatCurrency(value: number) {
  return new Intl.NumberFormat("en-US", {
    style: "currency",
    currency: "USD",
  }).format(value);
}

function getSummary(products: AdminProduct[], orders: AdminOrder[]): DashboardSummary {
  const lowStockProducts = products.filter(
    (product) => product.track_stock && (product.stock_quantity ?? 0) <= 5,
  );
  const paidOrders = orders.filter(
    (order) => order.payment.status === "paid" || order.status === "completed",
  );
  const pendingOrders = orders.filter(
    (order) => order.payment.status !== "paid" && order.status !== "cancelled",
  );

  return {
    totalProducts: products.length,
    orderableProducts: products.filter((product) => product.is_orderable).length,
    lowStockCount: lowStockProducts.length,
    totalOrders: orders.length,
    openOrders: orders.filter(
      (order) => order.status !== "completed" && order.status !== "cancelled",
    ).length,
    paidRevenue: paidOrders.reduce((sum, order) => sum + order.total, 0),
    pendingRevenue: pendingOrders.reduce((sum, order) => sum + order.total, 0),
  };
}

function readStoredUser() {
  try {
    const stored = sessionStorage.getItem(USER_STORAGE_KEY);
    return stored ? (JSON.parse(stored) as AdminUser) : null;
  } catch {
    return null;
  }
}

export function AdminDashboard() {
  const [activePanel, setActivePanel] = useState<ActivePanel>("overview");
  const [token, setToken] = useState<string | null>(null);
  const [user, setUser] = useState<AdminUser | null>(null);
  const [hasCheckedSession, setHasCheckedSession] = useState(false);
  const [products, setProducts] = useState<AdminProduct[]>([]);
  const [orders, setOrders] = useState<AdminOrder[]>([]);
  const [paymentQrs, setPaymentQrs] = useState<AdminPaymentQr[]>([]);
  const [paymentAlerts, setPaymentAlerts] = useState<AdminTelegramPaymentAlert[]>([]);
  const [storeSettings, setStoreSettings] = useState<AdminStoreSettings>(DEFAULT_STORE_SETTINGS);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState("");

  const clearSession = useCallback(() => {
    sessionStorage.removeItem(TOKEN_STORAGE_KEY);
    sessionStorage.removeItem(USER_STORAGE_KEY);
    setToken(null);
    setUser(null);
    setProducts([]);
    setOrders([]);
        setPaymentQrs([]);
        setPaymentAlerts([]);
    setStoreSettings(DEFAULT_STORE_SETTINGS);
  }, []);

  const handleError = useCallback(
    (apiError: unknown) => {
      const message = apiError instanceof Error ? apiError.message : "The admin request failed.";

      if (
        apiError instanceof AdminApiError &&
        (apiError.status === 401 || apiError.status === 403)
      ) {
        clearSession();
        setError("Your admin session expired. Please sign in again.");
        return;
      }

      setError(message);
    },
    [clearSession],
  );

  const refreshData = useCallback(
    async (authToken = token) => {
      if (!authToken) return;

      setIsLoading(true);
      setError("");

      try {
        const [
          nextProducts,
          nextOrders,
          nextPaymentQrs,
          nextStoreSettings,
          nextPaymentAlerts,
        ] = await Promise.all([
          fetchAdminProducts(authToken),
          fetchAdminOrders(authToken),
          fetchAdminPaymentQrs(authToken),
          fetchAdminStoreSettings(authToken),
          fetchAdminPaymentAlerts(authToken),
        ]);
        setProducts(nextProducts);
        setOrders(nextOrders);
        setPaymentQrs(nextPaymentQrs);
        setStoreSettings(nextStoreSettings);
        setPaymentAlerts(nextPaymentAlerts);
      } catch (apiError) {
        handleError(apiError);
      } finally {
        setIsLoading(false);
      }
    },
    [handleError, token],
  );

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      const storedToken = sessionStorage.getItem(TOKEN_STORAGE_KEY);

      if (storedToken) {
        setToken(storedToken);
        setUser(readStoredUser());
      }

      setHasCheckedSession(true);
    }, 0);

    return () => window.clearTimeout(timeout);
  }, []);

  useEffect(() => {
    if (!token) return undefined;

    const timeout = window.setTimeout(() => {
      void refreshData(token);
    }, 0);

    return () => window.clearTimeout(timeout);
  }, [refreshData, token]);

  const summary = useMemo(() => getSummary(products, orders), [orders, products]);
  const lowStockProducts = useMemo(
    () =>
      products
        .filter((product) => product.track_stock && (product.stock_quantity ?? 0) <= 5)
        .sort((left, right) => (left.stock_quantity ?? 0) - (right.stock_quantity ?? 0))
        .slice(0, 6),
    [products],
  );
  const recentOrders = useMemo(() => orders.slice(0, 6), [orders]);

  const handleLogin = (nextToken: string, nextUser: AdminUser) => {
    sessionStorage.setItem(TOKEN_STORAGE_KEY, nextToken);
    sessionStorage.setItem(USER_STORAGE_KEY, JSON.stringify(nextUser));
    setToken(nextToken);
    setUser(nextUser);
    setHasCheckedSession(true);
    setError("");
  };

  const handleLogout = async () => {
    const currentToken = token;
    clearSession();

    if (!currentToken) return;

    try {
      await logoutAdmin(currentToken);
    } catch {
      // Local session cleanup is enough if the token was already expired.
    }
  };

  const upsertProduct = (product: AdminProduct) => {
    setProducts((current) => {
      const exists = current.some((item) => item.id === product.id);
      if (!exists) return [product, ...current];
      return current.map((item) => (item.id === product.id ? product : item));
    });
  };

  const removeProduct = (slug: string) => {
    setProducts((current) => current.filter((product) => product.slug !== slug));
  };

  const upsertOrder = (order: AdminOrder) => {
    setOrders((current) => current.map((item) => (item.order_number === order.order_number ? order : item)));
  };

  const upsertPaymentQr = (paymentQr: AdminPaymentQr) => {
    setPaymentQrs((current) => {
      const updated = current.some((item) => item.id === paymentQr.id)
        ? current.map((item) => (item.id === paymentQr.id ? paymentQr : item))
        : [paymentQr, ...current];

      if (!paymentQr.is_active) {
        return updated;
      }

      return updated.map((item) =>
        item.id !== paymentQr.id &&
        item.currency === paymentQr.currency &&
        item.amount_cents === paymentQr.amount_cents
          ? { ...item, is_active: false }
          : item,
      );
    });
  };

  const removePaymentQr = (id: number) => {
    setPaymentQrs((current) => current.filter((paymentQr) => paymentQr.id !== id));
  };

  if (!hasCheckedSession) {
    return (
      <main className={styles.loginShell}>
        <section className={styles.loginPanel}>
          <p className={styles.eyebrow}>Admin access</p>
          <h1>Loading dashboard</h1>
          <p className={styles.loginCopy}>Checking your admin session.</p>
        </section>
      </main>
    );
  }

  if (!token) {
    return <AdminLogin onLogin={handleLogin} />;
  }

  return (
    <main className={styles.adminShell}>
      <aside className={styles.sidebar} aria-label="Admin navigation">
        <div>
          <p className={styles.eyebrow}>ZYN Reserve</p>
          <h1>Admin Dashboard</h1>
        </div>
        <nav className={styles.navList}>
          {navItems.map((item) => {
            const Icon = item.icon;

            return (
              <button
                key={item.id}
                className={activePanel === item.id ? styles.activeNavButton : ""}
                type="button"
                onClick={() => setActivePanel(item.id)}
              >
                <Icon size={17} />
                {item.label}
              </button>
            );
          })}
        </nav>
        <div className={styles.sidebarFooter}>
          <div>
            <strong>{user?.name ?? "Store Admin"}</strong>
            <span>{user?.email ?? "Signed in"}</span>
          </div>
          <button type="button" title="Sign out" aria-label="Sign out" onClick={handleLogout}>
            <LogOut size={17} />
          </button>
        </div>
      </aside>

      <section className={styles.dashboardArea}>
        <header className={styles.topbar}>
          <div>
            <p className={styles.eyebrow}>Operations</p>
            <h2>Products, orders, and revenue</h2>
          </div>
          <button
            className={styles.secondaryButton}
            type="button"
            disabled={isLoading}
            onClick={() => refreshData()}
          >
            {isLoading ? <RefreshCw size={16} /> : <BarChart3 size={16} />}
            {isLoading ? "Refreshing" : "Refresh"}
          </button>
        </header>

        {error && <p className={styles.alertBanner}>{error}</p>}

        {activePanel === "overview" && (
          <AdminOverview
            summary={summary}
            lowStockProducts={lowStockProducts}
            recentOrders={recentOrders}
            formatCurrency={formatCurrency}
          />
        )}

        {activePanel === "orders" && (
          <OrdersPanel
            token={token}
            orders={orders}
            paymentAlerts={paymentAlerts}
            onOrderUpdated={upsertOrder}
            onError={handleError}
            formatCurrency={formatCurrency}
          />
        )}

        {activePanel === "payment-qrs" && (
          <PaymentQrsPanel
              key={`${storeSettings.deliveryFeeCents}-${storeSettings.checkoutSessionLifetimeMinutes}`}
            token={token}
            paymentQrs={paymentQrs}
            products={products}
            settings={storeSettings}
            onPaymentQrSaved={upsertPaymentQr}
            onPaymentQrDeleted={removePaymentQr}
            onSettingsSaved={setStoreSettings}
            onError={handleError}
            formatCurrency={formatCurrency}
          />
        )}

        {activePanel === "payment-reviews" && (
          <PaymentReviewsPanel token={token} onError={handleError} onVerified={() => void refreshData()} />
        )}

        {activePanel === "products" && (
          <ProductsPanel
            token={token}
            products={products}
            onProductSaved={upsertProduct}
            onProductDeleted={removeProduct}
            onError={handleError}
            formatCurrency={formatCurrency}
          />
        )}
      </section>
    </main>
  );
}
