import { portalAuthHeaders } from "./auth";

const BASE_URL = process.env.NEXT_PUBLIC_LARAVEL_URL ?? "http://localhost:8000";

export async function portalFetch<T = any>(
  path: string,
  options: RequestInit = {}
): Promise<T> {
  const res = await fetch(`${BASE_URL}${path}`, {
    ...options,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...portalAuthHeaders(),
      ...(options.headers || {}),
    },
  });

  if (res.status === 401) {
    if (typeof window !== "undefined") {
      sessionStorage.removeItem("unb_portal_token");
      window.location.href = "/";
    }
    throw new Error("Unauthenticated");
  }

  if (!res.ok) {
    const body = await res.json().catch(() => null);
    throw { status: res.status, body };
  }

  if (res.status === 204) return undefined as T;

  return res.json();
}
