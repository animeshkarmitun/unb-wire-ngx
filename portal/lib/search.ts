import { Meilisearch } from "meilisearch";

let client: Meilisearch | null = null;
let tenantToken: string | null = null;

export async function getSearchClient(): Promise<{ client: Meilisearch; token: string } | null> {
  if (client && tenantToken) return { client, token: tenantToken };
  try {
    const res = await fetch(`${process.env.NEXT_PUBLIC_LARAVEL_URL ?? "http://localhost:8000"}/api/v1/portal/search-token`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
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

export async function searchStories(query: string, filter?: string) {
  const ctx = await getSearchClient();
  if (!ctx) return null;
  try {
    const index = ctx.client.index(process.env.NEXT_PUBLIC_MEILISARCH_INDEX ?? "main");
    const res = await Promise.race([
      index.search(query, { filter, limit: 20 }),
      new Promise<null>((_, rej) => setTimeout(() => rej(new Error("timeout")), 5000)),
    ]) as any;
    return res;
  } catch {
    client = null; tenantToken = null;
    return null;
  }
}
