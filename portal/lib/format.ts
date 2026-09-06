import { WireStory } from "../app/types";

export function timeAgo(input: string | number | undefined): string {
  if (input === undefined || input === null) return "Just now";
  if (typeof input === "number") {
    if (input < 1) return "Just now";
    if (input < 60) return `${input} min ago`;
    const h = Math.round(input / 60);
    return `${h} ${h === 1 ? "hr ago" : "hrs ago"}`;
  }

  try {
    const d = new Date(input);
    const now = new Date();
    const diffMin = Math.floor((now.getTime() - d.getTime()) / 60000);
    if (diffMin < 1) return "Just now";
    if (diffMin < 60) return `${diffMin} min ago`;
    const diffHr = Math.round(diffMin / 60);
    if (diffHr < 24) return `${diffHr} ${diffHr === 1 ? "hr ago" : "hrs ago"}`;
    const diffDays = Math.round(diffHr / 24);
    return `${diffDays} ${diffDays === 1 ? "day ago" : "days ago"}`;
  } catch {
    return "Recently";
  }
}

export function esc(s: string): string {
  return (s || "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

export function saveBlob(content: BlobPart, name: string, type: string) {
  if (typeof window === "undefined") return;
  const blob = content instanceof Blob ? content : new Blob([content], { type });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = name;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  setTimeout(() => URL.revokeObjectURL(url), 2000);
}

export function gradientPng(i: number): Promise<Blob> {
  if (typeof window === "undefined") return Promise.resolve(new Blob());
  const c = document.createElement("canvas");
  c.width = 1200;
  c.height = 800;
  const ctx = c.getContext("2d");
  const cols = [
    ["#3b6fe0", "#16204a"],
    ["#e5484d", "#7a1f2b"],
    ["#16a34a", "#0b3d24"],
    ["#f0a832", "#8a5410"],
    ["#7c3aed", "#2e1065"],
    ["#0ea5e9", "#0c4a6e"],
    ["#db2777", "#831843"],
    ["#64748b", "#1e293b"],
  ][Math.abs(i) % 8];

  if (ctx) {
    const g = ctx.createLinearGradient(0, 0, 1200, 800);
    g.addColorStop(0, cols[0]);
    g.addColorStop(1, cols[1]);
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, 1200, 800);
  }

  return new Promise((resolve) => {
    c.toBlob((b) => resolve(b || new Blob()), "image/png");
  });
}

export function storyPlain(s: WireStory): string {
  const timeStr = typeof s.mins === "number" ? timeAgo(s.mins) : timeAgo(s.published_at);
  const capsText = s.caps && s.caps.length > 0 ? `\n\nPhotos:\n${s.caps.map((c) => `- ${c}`).join("\n")}` : "";
  const signoff = `\n\nEND/UNB/${String(s.id).toUpperCase()}/2026`;
  const body = s.body_html ? s.body_html.replace(/<[^>]+>/g, "\n\n").replace(/\n{3,}/g, "\n\n").trim() : (s.brief || "");

  return `${s.headline}\n\n${s.brief}\n\nUNB Wire · ${timeStr}\n\n${body}${capsText}${signoff}`;
}

export function wordDoc(list: WireStory[]): string {
  return (
    '<html xmlns:o="urn:schemas-microsoft-com:office:office"><head><meta charset="utf-8"></head><body>' +
    list
      .map((s) => {
        const bodyParagraphs = (s.body_html ? s.body_html.replace(/<[^>]+>/g, "\n\n") : s.brief)
          .split("\n\n")
          .filter(Boolean)
          .map((p) => `<p>${esc(p)}</p>`)
          .join("");
        const capsList =
          s.caps && s.caps.length > 0
            ? `<p><b>Photos:</b></p><ul>${s.caps.map((c) => `<li>${esc(c)}</li>`).join("")}</ul>`
            : "";
        return `<h2>${esc(s.headline)}</h2><p><i>${esc(s.brief)}</i></p>${bodyParagraphs}${capsList}<p><small>END/UNB/${s.id}</small></p><hr>`;
      })
      .join("") +
    "</body></html>"
  );
}

export function xmlBundle(list: WireStory[]): string {
  return (
    '<?xml version="1.0" encoding="UTF-8"?>\n<wire client="daily-star" count="' +
    list.length +
    '">' +
    list
      .map((s) => {
        const timeStr = typeof s.mins === "number" ? timeAgo(s.mins) : timeAgo(s.published_at);
        const bodyParagraphs = (s.body_html ? s.body_html.replace(/<[^>]+>/g, "\n\n") : s.brief)
          .split("\n\n")
          .filter(Boolean)
          .map((p) => `\n      <p>${esc(p)}</p>`)
          .join("");
        const tagsXml = s.tags ? s.tags.map((t) => `<tag>${esc(t)}</tag>`).join("") : "";
        const photosCount = s.caps ? s.caps.length : 0;
        const videoCount = s.has_video ? 1 : 0;
        return (
          `\n  <story id="${s.id}" category="${esc(s.category)}" published="${timeStr}">` +
          `\n    <headline>${esc(s.headline)}</headline>` +
          `\n    <brief>${esc(s.brief)}</brief>` +
          `\n    <body>${bodyParagraphs}\n    </body>` +
          `\n    <tags>${tagsXml}</tags>` +
          `\n    <media photos="${photosCount}" video="${videoCount}"/>` +
          `\n  </story>`
        );
      })
      .join("") +
    "\n</wire>"
  );
}

export function csvHeadlines(list: WireStory[]): string {
  const rows = list.map((s) => {
    const timeStr = typeof s.mins === "number" ? timeAgo(s.mins) : timeAgo(s.published_at);
    const head = `"${(s.headline || "").replace(/"/g, '""')}"`;
    const tags = `"${(s.tags || []).join(" ")}"`;
    const photos = s.caps ? s.caps.length : 0;
    const video = s.has_video ? 1 : 0;
    return [s.id, s.category, timeStr, head, tags, photos, video].join(",");
  });

  return "id,category,published,headline,tags,photos,video\n" + rows.join("\n");
}

export const CAT_CLASS: Record<string, string> = {
  World: "world",
  Sports: "sports",
  Business: "business",
  Environment: "env",
  Politics: "",
  Bangladesh: "",
  Weather: "",
};

export const TW_CAT_DOT: Record<string, string> = {
  Politics: "#e5484d",
  Business: "#f0a832",
  World: "#3b6fe0",
  Sports: "#16a34a",
  Environment: "#0d9488",
  Bangladesh: "#16204a",
  Weather: "#0ea5e9",
};

export const GRADS = ["g1", "g2", "g3", "g4", "g5", "g6", "g7", "g8"];
