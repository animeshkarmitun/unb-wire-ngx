"use client";

import { useEffect, useState } from "react";

export default function LocalTime({
  value,
  fallback = "",
  locale,
}: {
  value: string | null | undefined;
  fallback?: string;
  locale?: string;
}) {
  const [text, setText] = useState<string>(fallback);

  useEffect(() => {
    if (!value) return;
    const d = new Date(value);
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC";
    const loc = locale ?? "en-US";
    const local = d.toLocaleString(loc, { timeZone: tz, hour12: true });
    const dhaka = d.toLocaleTimeString(loc, { timeZone: "Asia/Dhaka", hour: "numeric", minute: "2-digit", hour12: true });
    setText(`${local} · Dhaka ${dhaka}`);
  }, [value, locale]);

  return (
    <span suppressHydrationWarning data-testid="local-time">
      {text}
    </span>
  );
}
