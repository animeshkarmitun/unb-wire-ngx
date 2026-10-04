import { Meilisearch } from "meilisearch";

let client: Meilisearch | null = null;
let tenantToken: string | null = null;

import { authHeaders } from "./auth";

export async function getSearchClient(): Promise<{ client: Meilisearch; token: string } | null> {
  if (client && tenantToken) return { client, token: tenantToken };
  try {
    const res = await fetch(`${process.env.NEXT_PUBLIC_LARAVEL_URL ?? "http://localhost:8000"}/api/v1/portal/search-token`, {
      method: "POST",
      headers: { "Content-Type": "application/json", ...authHeaders() },
      cache: "no-store",
    });
    if (!res.ok) return null;
    const data = await res.json();
    tenantToken = data.token;
    client = new Meilisearch({ host: data.host ?? "http://localhost:7700", apiKey: tenantToken ?? undefined });
    return { client, token: tenantToken! };
  } catch {
    return null;
  }
}

export type SearchHit = Record<string, unknown> & { from_archive: boolean };

export async function searchStories(query: string, filter?: string): Promise<SearchHit[] | null> {
  const ctx = await getSearchClient();
  if (!ctx) return null;
  try {
    const names = [process.env.NEXT_PUBLIC_MEILISARCH_INDEX ?? "main", "archive"];
    const race = await Promise.race([
      Promise.all(names.map((n) => ctx!.client.index(n).search(query, { filter, limit: 20 }))),
      new Promise<null>((_, rej) => setTimeout(() => rej(new Error("timeout")), 5000)),
    ]);
    if (!race) return null;
    const results = race as Array<{ hits?: unknown[] }>;
    const hits: SearchHit[] = [];
    results.forEach((res, i) => {
      (res.hits ?? []).forEach((h) => hits.push({ ...(h as Record<string, unknown>), from_archive: names[i] === "archive" }));
    });
    return hits;
  } catch {
    client = null; tenantToken = null;
    return null;
  }
}
