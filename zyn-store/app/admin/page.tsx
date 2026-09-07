import type { Metadata } from "next";

import { AdminDashboard } from "@/components/admin/AdminDashboard";

export const metadata: Metadata = {
  title: "Admin Dashboard | ZYN Reserve Cambodia",
  description: "Manage ZYN Reserve Cambodia products, orders, inventory, and payments.",
};

export default function AdminPage() {
  return <AdminDashboard />;
}
