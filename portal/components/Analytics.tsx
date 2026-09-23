"use client";

import { usePathname } from "next/navigation";
import { useEffect, useRef } from "react";
import { GA_ID } from "../lib/ga";

declare global {
  interface Window {
    dataLayer?: unknown[];
    gtag?: (...args: unknown[]) => void;
  }
}

export default function Analytics() {
  const pathname = usePathname();
  const tracked = useRef<string | null>(null);

  useEffect(() => {
    if (!GA_ID || !window.gtag || tracked.current === pathname) return;
    tracked.current = pathname;
    window.gtag("event", "page_view", { page_path: pathname });

    const storyMatch = pathname.match(/^\/story\/([^/]+)/);
    if (storyMatch) {
      window.gtag("event", "story_view", { story_id: decodeURIComponent(storyMatch[1]) });
    }
  }, [pathname]);

  if (!GA_ID) {
    return null;
  }

  const safeId = GA_ID.replace(/[^A-Za-z0-9-]/g, "");

  return (
    <>
      <script async src={`https://www.googletagmanager.com/gtag/js?id=${safeId}`} />
      <script
        dangerouslySetInnerHTML={{
          __html: `window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}window.gtag=gtag;gtag('js',new Date());gtag('config','${safeId}');`,
        }}
      />
    </>
  );
}
