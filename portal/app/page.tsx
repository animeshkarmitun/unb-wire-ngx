import Link from "next/link";

async function getStories() {
  const base = process.env.NEXT_PUBLIC_LARAVEL_URL ?? "http://localhost:8000";
  try {
    const res = await fetch(`${base}/api/v1/portal/feed`, { next: { revalidate: 60, tags: ["feed"] } });
    if (!res.ok) return [];
    const j = await res.json();
    return j.data ?? [];
  } catch {
    return [];
  }
}

export default async function Home() {
  const stories = await getStories();

  return (
    <div className="min-h-screen">
      <header className="sticky top-0 z-20 bg-white border-b border-[#eceae5] px-6 lg:px-10 py-3 flex items-center gap-4">
        <div className="flex items-center gap-2">
          <div className="w-8 h-8 rounded-lg bg-[#e5484d] text-white font-bold flex items-center justify-center">U</div>
          <div>
            <div className="font-bold leading-none">UNB Wire</div>
            <div className="text-[11px] text-[#667099] uppercase tracking-wide">Client Portal</div>
          </div>
        </div>
        <div className="ml-auto flex items-center gap-2 text-xs text-[#7c7f8c]">
          <span className="w-2 h-2 rounded-full bg-[#16a34a]" /> Live
          <Link href="http://localhost:8000/admin" className="ml-3 px-3 py-1.5 rounded-lg border text-sm">Newsroom →</Link>
        </div>
      </header>

      <main className="max-w-[1100px] mx-auto px-6 lg:px-10 py-8">
        <div className="flex items-center gap-3 mb-4">
          <h1 className="font-serif text-2xl font-bold">Wire feed</h1>
          <span className="text-xs bg-[#f3f1ee] px-2 py-1 rounded-full">{stories.length} stories</span>
        </div>

        <div className="flex gap-2 mb-4">
          <input
            placeholder="Search stories… (Meilisearch tenant-token)"
            className="flex-1 border border-[#eceae5] rounded-lg px-3 py-2 text-sm bg-white"
            id="portalSearch"
          />
          <span className="text-xs text-[#b0b2bc] py-2">Entitlement filter baked into tenant token — never hits Laravel</span>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-[1.7fr_0.9fr] gap-6 items-start">
          <div className="bg-white border border-[#eceae5] rounded-xl overflow-hidden">
            <div className="px-4 py-2 bg-[#faf9f6] border-b text-xs font-bold uppercase tracking-wide text-[#b0b2bc]">Latest</div>
            {stories.length === 0 ? (
              <div className="p-8 text-center text-sm text-[#7c7f8c]">No stories yet. Publish from the newsroom to populate the feed. (ISR + tenant token)</div>
            ) : (
              stories.map((s: any) => (
                <Link key={s.public_id} href={`/story/${s.public_id}`} className="block px-4 py-4 border-b last:border-0 hover:bg-[#fcfbf8]">
                  <div className="font-serif font-semibold leading-tight">{s.headline}</div>
                  <div className="text-xs text-[#7c7f8c] mt-1">{s.category} · {s.published_at ? new Date(s.published_at).toLocaleString() : s.status} {s.is_breaking ? "· BREAKING" : ""}</div>
                  <div className="text-sm text-[#1c1f2e] mt-1 line-clamp-2">{s.brief}</div>
                </Link>
              ))
            )}
          </div>

          <div className="space-y-4">
            <div className="bg-white border border-[#eceae5] rounded-xl p-4">
              <div className="text-xs font-bold uppercase text-[#b0b2bc] mb-2">How search works</div>
              <p className="text-xs leading-relaxed text-[#7c7f8c]">
                Browser-direct Meilisearch with short-lived tenant token from <code className="bg-[#f3f1ee] px-1 rounded">POST /api/v1/portal/search-token</code>. Delivery, portal visibility, and search filter share one <code className="bg-[#f3f1ee] px-1 rounded">entitlement_filter</code>.
              </p>
            </div>
            <div className="bg-white border border-[#eceae5] rounded-xl p-4">
              <div className="text-xs font-bold uppercase text-[#b0b2bc] mb-2">Presigned downloads</div>
              <p className="text-xs text-[#7c7f8c]">Media originals are S3 + CDN, served via presigned URLs with download ledger.</p>
            </div>
          </div>
        </div>
      </main>

      <script
        dangerouslySetInnerHTML={{
          __html: `document.getElementById('portalSearch')?.addEventListener('keydown', e=>{ if(e.key==='Enter'){ const q=e.target.value; if(!q) return; fetch('/api/search?q='+encodeURIComponent(q)).catch(()=>{}); }});`,
        }}
      />
    </div>
  );
}
