import Link from "next/link";
import { INITIAL_STORIES } from "../../../lib/mockData";

async function getStory(id: string) {
  const base = process.env.NEXT_PUBLIC_LARAVEL_URL ?? "http://localhost:8000";
  try {
    const res = await fetch(`${base}/api/v1/portal/story/${id}`, { next: { revalidate: 300 } });
    if (res.ok) {
      const j = await res.json();
      return j.data ?? j;
    }
  } catch {
    // Fall back to prototype data
  }

  const mock = INITIAL_STORIES.find((s) => String(s.id) === id || s.public_id === id);
  return mock ?? null;
}

export default async function StoryPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const s = await getStory(id);
  if (!s) {
    return (
      <div className="max-w-[760px] mx-auto px-6 py-16 text-center">
        <h1 className="font-serif text-2xl font-bold mb-3">Story not found</h1>
        <p className="text-sm text-[#7c7f8c] mb-6">This dispatch may have been retracted, archived, or is exclusive to another subscriber tier.</p>
        <Link href="/" className="inline-block px-4 py-2 rounded-lg bg-[#16204a] text-white text-xs font-semibold">
          ← Back to wire feed
        </Link>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-[#faf9f6]">
      <header className="sticky top-1 z-20 bg-white/95 backdrop-blur border-b border-[#eceae5] px-6 lg:px-10 py-3 flex items-center gap-4">
        <Link href="/" className="flex items-center gap-2">
          <div className="w-8 h-8 rounded-lg bg-[#e5484d] text-white font-bold flex items-center justify-center font-serif">U</div>
          <div>
            <div className="font-serif font-bold text-base leading-none">UNB Wire</div>
            <div className="text-[10px] text-[#b0b2bc] uppercase tracking-wider font-semibold">Client Portal</div>
          </div>
        </Link>
        <div className="ml-auto flex items-center gap-3 text-xs">
          <Link href="/" className="text-[#4b4e5c] hover:text-[#16204a] font-semibold">
            ← Back to wire feed
          </Link>
        </div>
      </header>

      <main className="max-w-[780px] mx-auto px-6 py-10">
        <div className="flex items-center gap-2 mb-3">
          <span className="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-[#fdecec] text-[#d13438]">
            {s.category}
          </span>
          <span className="text-xs text-[#b0b2bc]">
            {s.published_at ? new Date(s.published_at).toLocaleString("en-US", { timeZone: "Asia/Dhaka" }) : "Published"}
          </span>
          {s.is_breaking && (
            <span className="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-red-600 text-white animate-pulse">
              BREAKING
            </span>
          )}
        </div>

        <h1 className="font-serif text-3xl font-bold leading-snug text-[#1c1f2e]">{s.headline}</h1>
        {s.sub_head && <h2 className="text-base text-[#7c7f8c] mt-2 leading-relaxed">{s.sub_head}</h2>}

        {s.brief && (
          <div className="my-5 p-4 bg-white border border-[#eceae5] rounded-xl text-sm leading-relaxed text-[#4b4e5c] italic border-l-4 border-l-[#16204a]">
            {s.brief}
          </div>
        )}

        <div
          className="prose prose-sm max-w-none text-[#2b2e3c] leading-relaxed space-y-4 text-[14.5px]"
          dangerouslySetInnerHTML={{ __html: s.body_html || `<p>${s.brief}</p>` }}
        />

        {s.caps && s.caps.length > 0 && (
          <div className="mt-8 p-4 bg-white border border-[#eceae5] rounded-xl">
            <div className="text-[11px] font-bold uppercase tracking-wider text-[#b0b2bc] mb-3">
              Attached wire photos ({s.caps.length})
            </div>
            <ul className="space-y-2 text-xs text-[#4b4e5c]">
              {s.caps.map((c: string, idx: number) => (
                <li key={idx} className="flex items-start gap-2">
                  <span className="text-[#b0b2bc] font-mono">[{idx + 1}]</span>
                  <span>{c}</span>
                </li>
              ))}
            </ul>
          </div>
        )}

        <div className="mt-8 pt-6 border-t border-[#eceae5] flex items-center justify-between text-xs text-[#b0b2bc]">
          <div>Public ID: {s.public_id || s.id} · Dhaka time</div>
          <div>END/UNB/{String(s.id).toUpperCase()}/2026</div>
        </div>
      </main>
    </div>
  );
}
