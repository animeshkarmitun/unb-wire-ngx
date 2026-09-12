import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "UNB Client Portal — Daily Star",
  description: "UNB Wire client portal — live wire feed, terminal table, media packages, search",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" className="h-full">
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        {/* eslint-disable-next-line @next/next/no-page-custom-font */}
        <link
          href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap"
          rel="stylesheet"
        />
      </head>
      <body className="min-h-full antialiased">
        <div className="brand-strip" />
        {children}
      </body>
    </html>
  );
}
