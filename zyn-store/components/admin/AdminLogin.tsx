"use client";

import { LockKeyhole, ShieldCheck } from "lucide-react";
import { FormEvent, useState } from "react";

import { isApiConfigured } from "@/lib/api";

import { loginAdmin } from "./admin-api";
import styles from "./admin.module.css";
import type { AdminUser } from "./types";

type AdminLoginProps = {
  onLogin: (token: string, user: AdminUser) => void;
};

export function AdminLogin({ onLogin }: AdminLoginProps) {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  const submitLogin = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError("");
    setIsSubmitting(true);

    try {
      const result = await loginAdmin(email, password);
      onLogin(result.token, result.user);
    } catch (loginError) {
      setError(loginError instanceof Error ? loginError.message : "Unable to sign in.");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <main className={styles.loginShell}>
      <form className={styles.loginPanel} onSubmit={submitLogin}>
        <div className={styles.loginMark}>
          <ShieldCheck size={30} />
        </div>
        <p className={styles.eyebrow}>Admin access</p>
        <h1>ZYN Reserve Control</h1>
        <p className={styles.loginCopy}>
          Sign in to manage inventory, orders, product visibility, and payment follow-up.
        </p>
        <label>
          <span>Email</span>
          <input
            type="email"
            autoComplete="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            required
          />
        </label>
        <label>
          <span>Password</span>
          <input
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            required
          />
        </label>
        {!isApiConfigured && (
          <p className={styles.formError}>NEXT_PUBLIC_API_URL must point to the Laravel API.</p>
        )}
        {error && <p className={styles.formError}>{error}</p>}
        <button className={styles.primaryButton} type="submit" disabled={!isApiConfigured || isSubmitting}>
          <LockKeyhole size={16} />
          {isSubmitting ? "Signing in" : "Sign in"}
        </button>
      </form>
    </main>
  );
}
