async function getStory(id: string) {
  const base = process.env.NEXT_PUBLIC_LARAVEL_URL ?? "http://localhost:8000";
  try {
    const res = await fetch(`${base}/api/v1/portal/story/${id}`, { next: { revalidate: 300 } });
    if (!res.ok) return null;
    return res.json();
  } catch { return null; }
}

export default async function StoryPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const data = await getStory(id);
  if (!data) return <div className="p-10 text-center">Story not found</div>;
  const s = data.data ?? data;
  return (
    <div className="max-w-[760px] mx-auto px-6 py-8">
      <a href="/" className="text-xs text-[#7c7f8c]">← Back to feed</a>
      <div className="text-xs text-[#b0b2bc] mt-3">{s.category} · {s.published_at ? new Date(s.published_at).toLocaleString() : s.status}</div>
      <h1 className="font-serif text-3xl font-bold mt-1 leading-tight">{s.headline}</h1>
      {s.sub_head && <h2 className="text-lg text-[#7c7f8c] mt-2">{s.sub_head}</h2>}
      {s.brief && <div className="mt-4 p-3 bg-[#faf9f6] border rounded-lg text-sm italic">{s.brief}</div>}
      <div className="prose prose-sm max-w-none mt-4" dangerouslySetInnerHTML={{ __html: s.body_html ?? "" }} />
      <div className="mt-6 text-xs text-[#b0b2bc]">Public ID {s.public_id} · Dhaka time · Immutable after publish</div>
    </div>
  );
}
