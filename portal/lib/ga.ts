export const GA_ID: string = process.env.NEXT_PUBLIC_GA_MEASUREMENT_ID ?? "";

export function gaEvent(name: string, params: Record<string, unknown> = {}): void {
  if (!GA_ID || typeof window === "undefined") return;
  const gtag = (window as unknown as { gtag?: (...args: unknown[]) => void }).gtag;
  gtag?.("event", name, params);
}
