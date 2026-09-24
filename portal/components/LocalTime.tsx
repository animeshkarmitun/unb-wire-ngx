"use client";

import { useEffect, useState } from "react";

export default function LocalTime({ value, fallback = "" }: { value: string | null | undefined; fallback?: string }) {
  const [text, setText] = useState<string>(fallback);

  useEffect(() => {
    if (!value) return;
    const d = new Date(value);
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC";
    const local = d.toLocaleString("en-US", { timeZone: tz, hour12: true });
    const dhaka = d.toLocaleTimeString("en-US", { timeZone: "Asia/Dhaka", hour: "numeric", minute: "2-digit", hour12: true });
    setText(`${local} · Dhaka ${dhaka}`);
  }, [value]);

  return (
    <span suppressHydrationWarning data-testid="local-time">
      {text}
    </span>
  );
}
