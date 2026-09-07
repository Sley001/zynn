import type { Metadata } from "next";

import "./globals.css";

const siteUrl = "https://zyn-cambodia-store.b96004449.chatgpt.site";

export const metadata: Metadata = {
  metadataBase: new URL(siteUrl),
  title: "ZYN Reserve Cambodia",
  description:
    "A curated ZYN nicotine pouch store for adult nicotine users in Cambodia. Adults 21+ only.",
  icons: { icon: "/favicon.svg", shortcut: "/favicon.svg" },
  openGraph: {
    title: "ZYN Reserve Cambodia",
    description: "A considered nicotine pouch collection for adults 21+.",
    type: "website",
    url: siteUrl,
    images: [
      {
        url: "/og.png",
        width: 1731,
        height: 909,
        alt: "ZYN Reserve Cambodia — adults 21+ only",
      },
    ],
  },
  twitter: {
    card: "summary_large_image",
    title: "ZYN Reserve Cambodia",
    description: "A considered nicotine pouch collection for adults 21+.",
    images: ["/og.png"],
  },
  other: {
    "codex-preview": "development",
  },
};

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="km">
      <body>{children}</body>
    </html>
  );
}
