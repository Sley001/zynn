import { AlertTriangle, Boxes, ClipboardList, DollarSign, PackageCheck } from "lucide-react";

import styles from "./admin.module.css";
import type { AdminOrder, AdminProduct, DashboardSummary } from "./types";

type AdminOverviewProps = {
  summary: DashboardSummary;
  lowStockProducts: AdminProduct[];
  recentOrders: AdminOrder[];
  formatCurrency: (value: number) => string;
};

const metricItems = [
  { key: "paidRevenue", label: "Paid revenue", icon: DollarSign },
  { key: "openOrders", label: "Open orders", icon: ClipboardList },
  { key: "orderableProducts", label: "Orderable products", icon: PackageCheck },
  { key: "lowStockCount", label: "Low stock", icon: AlertTriangle },
] as const;

export function AdminOverview({
  summary,
  lowStockProducts,
  recentOrders,
  formatCurrency,
}: AdminOverviewProps) {
  return (
    <section className={styles.panelStack} aria-labelledby="admin-overview-title">
      <div className={styles.sectionTitle}>
        <div>
          <p className={styles.eyebrow}>Overview</p>
          <h2 id="admin-overview-title">Today&apos;s control room</h2>
        </div>
        <p>
          {summary.totalOrders} total orders · {summary.totalProducts} catalog products ·{" "}
          {formatCurrency(summary.pendingRevenue)} pending revenue
        </p>
      </div>

      <div className={styles.metricGrid}>
        {metricItems.map((item) => {
          const Icon = item.icon;
          const value =
            item.key === "paidRevenue"
              ? formatCurrency(summary.paidRevenue)
              : summary[item.key].toLocaleString();

          return (
            <article className={styles.metricCard} key={item.key}>
              <span>
                <Icon size={18} />
              </span>
              <p>{item.label}</p>
              <strong>{value}</strong>
            </article>
          );
        })}
      </div>

      <div className={styles.overviewGrid}>
        <section className={styles.dataPanel} aria-labelledby="low-stock-title">
          <div className={styles.panelHeading}>
            <h3 id="low-stock-title">Low stock watch</h3>
            <Boxes size={18} />
          </div>
          {lowStockProducts.length === 0 ? (
            <p className={styles.emptyState}>No tracked products are below the low-stock threshold.</p>
          ) : (
            <div className={styles.compactRows}>
              {lowStockProducts.map((product) => (
                <div className={styles.compactRow} key={product.id}>
                  <div>
                    <strong>{product.name}</strong>
                    <span>{product.status}</span>
                  </div>
                  <p>{product.stock_quantity ?? 0} left</p>
                </div>
              ))}
            </div>
          )}
        </section>

        <section className={styles.dataPanel} aria-labelledby="recent-orders-title">
          <div className={styles.panelHeading}>
            <h3 id="recent-orders-title">Recent orders</h3>
            <ClipboardList size={18} />
          </div>
          {recentOrders.length === 0 ? (
            <p className={styles.emptyState}>Orders will appear here after checkout submissions.</p>
          ) : (
            <div className={styles.compactRows}>
              {recentOrders.map((order) => (
                <div className={styles.compactRow} key={order.order_number}>
                  <div>
                    <strong>{order.order_number}</strong>
                    <span>
                      {order.customer.name} · {order.status}
                    </span>
                  </div>
                  <p>{formatCurrency(order.total)}</p>
                </div>
              ))}
            </div>
          )}
        </section>
      </div>
    </section>
  );
}
