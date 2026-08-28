import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "UNB Wire — Client Portal",
  description: "UNB Wire client portal — wire feed, search, downloads",
};

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" className="h-full">
      <head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
        {/* eslint-disable-next-line @next/next/no-page-custom-font */}
        <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@6..72,400&display=swap" rel="stylesheet" />
      </head>
      <body className="min-h-full flex flex-col antialiased">
        <div className="h-1 w-full bg-[linear-gradient(90deg,#e5484d_0%,#f0a832_45%,#16204a_100%)]" />
        {children}
      </body>
    </html>
  );
}
