"use client";

import React, { useState, useEffect, useRef, useMemo, useCallback } from "react";
import JSZip from "jszip";
import {
  WireStory,
  MediaLibraryItem,
  GalleryItem,
  CollectionItem,
  TrendingItem,
  StreamPhotoItem,
} from "./types";
import { getApiKey, setApiKey, clearApiKey, authHeaders } from "../lib/auth";
import LoginModal from "../components/LoginModal";
import {
  INITIAL_STORIES,
  buildAllMedia,
  GALLERIES,
  COLLECTIONS,
  TRENDING,
  PHOTO_STREAM,
  SAVED_SEARCHES,
  CLIENT_INFO,
  STORY_TITLES,
} from "../lib/mockData";
import { createEcho } from "../lib/echo";
import {
  timeAgo,
  esc,
  saveBlob,
  gradientPng,
  storyPlain,
  wordDoc,
  xmlBundle,
  csvHeadlines,
  CAT_CLASS,
  TW_CAT_DOT,
  GRADS,
} from "../lib/format";
import { downloadMedia } from "../lib/download";

export default function ClientPortal() {
  // ===== Master State =====
  const [clientInfo, setClientInfo] = useState<any>(null);
  const [showLogin, setShowLogin] = useState<boolean>(false);
  const [stories, setStories] = useState<WireStory[]>(INITIAL_STORIES);
  const [pendingNew, setPendingNew] = useState<WireStory[]>([]);
  const [freshIds, setFreshIds] = useState<Set<number | string>>(new Set());
  const [wsStatus, setWsStatus] = useState<"connecting" | "connected" | "disconnected">("connecting");

  // Navigation & View State
  const [tab, setTab] = useState<"wire" | "packs" | "photos">("wire");
  const [wireView, setWireView] = useState<"cards" | "grid" | "table">("cards");
  const [railHidden, setRailHidden] = useState<boolean>(true);
  const [userDropOpen, setUserDropOpen] = useState<boolean>(false);

  // Wire Filters & Search
  const [searchQuery, setSearchQuery] = useState<string>("");
  const [omniScope, setOmniScope] = useState<"all" | "headline" | "tags" | "captions">("all");
  const [selectedCat, setSelectedCat] = useState<string>("All");
  const [filterDate, setFilterDate] = useState<string>("all");
  const [filterMedia, setFilterMedia] = useState<string>("all");
  const [filterExclusive, setFilterExclusive] = useState<boolean>(false);
  const [sortOrder, setSortOrder] = useState<"new" | "old">("new");

  // Selection & Expansion
  const [selectedStories, setSelectedStories] = useState<Set<number | string>>(new Set());
  const [expandedCards, setExpandedCards] = useState<Set<number | string>>(new Set());
  const [expandedGrid, setExpandedGrid] = useState<Set<number | string>>(new Set());
  const [expandedTable, setExpandedTable] = useState<Set<number | string>>(new Set());

  // Table terminal specifics
  const [twDensity, setTwDensity] = useState<"cozy" | "compact">("cozy");
  const [twSort, setTwSort] = useState<"time" | "cat" | null>(null);
  const [twActiveIdx, setTwActiveIdx] = useState<number>(-1);

  // Media Library State
  const [allMedia] = useState<MediaLibraryItem[]>(() => buildAllMedia());
  const [mlShown, setMlShown] = useState<number>(24);
  const [mlSrc, setMlSrc] = useState<string>("all");
  const [mlSearch, setMlSearch] = useState<string>("");
  const [mlFilterOpen, setMlFilterOpen] = useState<boolean>(false);
  const [mlCat, setMlCat] = useState<string>("All");
  const [mlEnt, setMlEnt] = useState<string>("all");
  const [mlDate, setMlDate] = useState<string>("all");
  const [mlSort, setMlSort] = useState<"new" | "old">("new");
  const [mlDensity, setMlDensity] = useState<"cozy" | "compact">("cozy");
  const [selectedMedia, setSelectedMedia] = useState<Set<string>>(new Set());

  // Quotas & Balances
  const [mediaQuota, setMediaQuota] = useState<number>(0);
  const [apCredits, setApCredits] = useState<number>(20);

  useEffect(() => {
    const info = clientInfo || CLIENT_INFO;
    setMediaQuota(info.media_quota - info.media_used);
  }, [clientInfo]);

  // UNB Photos State
  const [heroRes, setHeroRes] = useState<"web" | "print">("web");
  const [phSearch, setPhSearch] = useState<string>("");
  const [collectionAlerts, setCollectionAlerts] = useState<Set<string>>(new Set());

  // Modals & Overlays
  const [activeAsset, setActiveAsset] = useState<MediaLibraryItem | null>(null);
  const [assetRes, setAssetRes] = useState<"web" | "print">("web");
  const [lightbox, setLightbox] = useState<{
    open: boolean;
    storyId?: number | string;
    items: { type: "photo" | "video"; grad: string; cap: string; i?: number }[];
    index: number;
  }>({ open: false, items: [], index: 0 });

  // Button Flash States
  const [flashStates, setFlashStates] = useState<Record<string, string>>({});

  // Clock
  const [clockText, setClockText] = useState<string>("Dhaka");

  const omniInputRef = useRef<HTMLInputElement>(null);
  const userWrapRef = useRef<HTMLDivElement>(null);
  const sentinelRef = useRef<HTMLDivElement>(null);

  // ===== Auth Hydration =====
  useEffect(() => {
    const key = getApiKey();
    if (key) {
      const base = process.env.NEXT_PUBLIC_LARAVEL_URL ?? "http://localhost:8000";
      fetch(`${base}/api/v1/portal/context`, {
        headers: { "X-API-Key": key }
      })
      .then(res => res.ok ? res.json() : null)
      .then(data => {
        if (data?.client) setClientInfo(data.client);
      })
      .catch(() => {});
    }
  }, []);

  // ===== Clock Interval =====
  useEffect(() => {
    function updateClock() {
      const now = new Date();
      const d = new Intl.DateTimeFormat("en-GB", {
        timeZone: "Asia/Dhaka",
        weekday: "short",
        day: "numeric",
        month: "short",
      }).format(now);
      const t = new Intl.DateTimeFormat("en-US", {
        timeZone: "Asia/Dhaka",
        hour: "numeric",
        minute: "2-digit",
        hour12: true,
      }).format(now);
      setClockText(`Dhaka · ${d} · ${t}`);
    }
    updateClock();
    const timer = setInterval(updateClock, 30000);
    return () => clearInterval(timer);
  }, []);

  // ===== WebSocket New Live Stories =====
  useEffect(() => {
    const echo = createEcho();
    if (echo) {
      echo.connector.pusher.connection.bind('state_change', (states: any) => {
        if (states.current === 'connected') setWsStatus('connected');
        else if (states.current === 'connecting') setWsStatus('connecting');
        else setWsStatus('disconnected');
      });

      echo.channel('wire.en').listen('StoryPublished', (e: any) => {
        const story: WireStory = {
            id: e.public_id,
            public_id: e.public_id,
            mins: 0,
            category: e.category || "Bangladesh",
            language: "en",
            published_at: e.published_at || new Date().toISOString(),
            status: "published",
            is_breaking: Boolean(e.is_breaking),
            ex: null,
            has_video: false,
            headline: e.headline || "",
            brief: e.summary || "",
            body_html: `<p>${e.summary || ""}</p>`,
            tags: ["news", "unb"],
            caps: [],
        };
        setPendingNew(prev => [story, ...prev]);
      });
    }
    return () => echo?.disconnect();
  }, []);

  // ===== Fetch live stories from Laravel backend (Hydration & Freshness) =====
  useEffect(() => {
    const base = process.env.NEXT_PUBLIC_LARAVEL_URL ?? "http://localhost:8000";
    fetch(`${base}/api/v1/portal/feed`, {
      headers: { ...authHeaders() }
    })
      .then((res) => (res.ok ? res.json() : null))
      .then((data) => {
        if (data && Array.isArray(data.data) && data.data.length > 0) {
          const liveStories: WireStory[] = data.data.map((item: any, idx: number) => ({
            id: item.public_id || `live_${idx}`,
            public_id: item.public_id || `live_${idx}`,
            mins: 5,
            category: item.category || "Bangladesh",
            language: item.language || "en",
            published_at: item.published_at || new Date().toISOString(),
            status: item.status || "published",
            is_breaking: Boolean(item.is_breaking),
            ex: null,
            has_video: Boolean(item.has_video),
            headline: item.headline || "",
            brief: item.brief || "",
            body_html: item.body_html || `<p>${item.brief || ""}</p>`,
            tags: item.tags || ["news", "unb"],
            caps: (item.media || []).map((m: any) => m.caption || "UNB Photo"),
          }));

          setStories((prev) => {
            const existingIds = new Set(prev.map((s) => s.public_id));
            const fresh = liveStories.filter((s) => !existingIds.has(s.public_id));
            return [...fresh, ...prev];
          });
        }
      })
      .catch(() => {
        // Graceful fallback to rich mock prototype stories
      });
  }, []);

  // ===== Global Click & Key Listeners =====
  useEffect(() => {
    function handleDocClick(e: MouseEvent) {
      if (userWrapRef.current && !userWrapRef.current.contains(e.target as Node)) {
        setUserDropOpen(false);
      }
    }
    function handleKeyDown(e: KeyboardEvent) {
      const activeTag = (document.activeElement?.tagName || "").toUpperCase();
      const isInput = ["INPUT", "TEXTAREA", "SELECT"].includes(activeTag);

      // Omnisearch "/" shortcut
      if (e.key === "/" && !isInput) {
        e.preventDefault();
        omniInputRef.current?.focus();
        return;
      }

      // Lightbox navigation
      if (lightbox.open) {
        if (e.key === "Escape") {
          setLightbox((l) => ({ ...l, open: false }));
        } else if (e.key === "ArrowLeft") {
          setLightbox((l) => ({
            ...l,
            index: (l.index - 1 + l.items.length) % l.items.length,
          }));
        } else if (e.key === "ArrowRight") {
          setLightbox((l) => ({
            ...l,
            index: (l.index + 1) % l.items.length,
          }));
        }
        return;
      }

      // Asset detail modal Esc
      if (activeAsset && e.key === "Escape") {
        setActiveAsset(null);
        return;
      }

      // Table View Keyboard Shortcuts (j/k/x/Enter)
      if (tab === "wire" && wireView === "table" && !isInput) {
        if (e.key === "j") {
          setTwActiveIdx((idx) => Math.min(idx + 1, filteredStories.length - 1));
          e.preventDefault();
        } else if (e.key === "k") {
          setTwActiveIdx((idx) => Math.max(idx - 1, 0));
          e.preventDefault();
        } else if (e.key === "x" && twActiveIdx >= 0) {
          const s = filteredStories[twActiveIdx];
          if (s && s.ex !== "other") {
            setSelectedStories((prev) => {
              const next = new Set(prev);
              next.has(s.id) ? next.delete(s.id) : next.add(s.id);
              return next;
            });
          }
        } else if (e.key === "Enter" && twActiveIdx >= 0) {
          const s = filteredStories[twActiveIdx];
          if (s && s.ex !== "other") {
            setExpandedTable((prev) => {
              const next = new Set(prev);
              next.has(s.id) ? next.delete(s.id) : next.add(s.id);
              return next;
            });
          }
        }
      }
    }

    document.addEventListener("click", handleDocClick);
    document.addEventListener("keydown", handleKeyDown);
    return () => {
      document.removeEventListener("click", handleDocClick);
      document.removeEventListener("keydown", handleKeyDown);
    };
  }, [lightbox.open, activeAsset, tab, wireView, twActiveIdx]);

  // ===== Infinite Scroll for Media Library =====
  useEffect(() => {
    if (!sentinelRef.current || tab !== "packs") return;
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting) {
          setMlShown((curr) => curr + 24);
        }
      },
      { rootMargin: "500px" }
    );
    observer.observe(sentinelRef.current);
    return () => observer.disconnect();
  }, [tab]);

  // ===== Helper Flash Action Handler =====
  const flash = (btnKey: string, text: string) => {
    setFlashStates((prev) => ({ ...prev, [btnKey]: text }));
    setTimeout(() => {
      setFlashStates((prev) => {
        const next = { ...prev };
        delete next[btnKey];
        return next;
      });
    }, 1500);
  };

  // ===== Filtering & Matching Logic =====
  const matchWhere = useCallback(
    (s: WireStory): string | null => {
      if (!searchQuery) return null;
      const q = searchQuery.toLowerCase();
      const inHead =
        s.headline.toLowerCase().includes(q) ||
        s.brief.toLowerCase().includes(q) ||
        (s.body_html && s.body_html.toLowerCase().includes(q));
      const inTags = s.tags.some((t) => t.toLowerCase().includes(q));
      const capHit = s.caps.find((c) => c.toLowerCase().includes(q));

      if (omniScope === "headline") return s.headline.toLowerCase().includes(q) ? "headline" : null;
      if (omniScope === "tags") return inTags ? "tag" : null;
      if (omniScope === "captions") return capHit ? capHit : null;

      if (inTags) return "tag";
      if (capHit) return capHit;
      return inHead ? "headline" : null;
    },
    [searchQuery, omniScope]
  );

  const filteredStories = useMemo(() => {
    let list = stories.filter((s) => {
      if (selectedCat !== "All" && s.category !== selectedCat) return false;
      if (filterDate !== "all" && typeof s.mins === "number" && s.mins > +filterDate) return false;
      if (filterMedia === "photo" && !s.caps.length) return false;
      if (filterMedia === "video" && !s.has_video) return false;
      if (filterMedia === "text" && (s.caps.length > 0 || s.has_video)) return false;
      if (filterExclusive && s.ex !== "you") return false;
      if (searchQuery && !matchWhere(s)) return false;
      return true;
    });

    if (twSort === "cat") {
      list.sort((a, b) => a.category.localeCompare(b.category) || (a.mins || 0) - (b.mins || 0));
    } else {
      list.sort((a, b) => {
        const aVal = typeof a.mins === "number" ? a.mins : 999;
        const bVal = typeof b.mins === "number" ? b.mins : 999;
        return sortOrder === "new" ? aVal - bVal : bVal - aVal;
      });
    }

    return list;
  }, [
    stories,
    selectedCat,
    filterDate,
    filterMedia,
    filterExclusive,
    searchQuery,
    sortOrder,
    twSort,
    matchWhere,
  ]);

  // ===== Filtered Media Items =====
  const filteredMediaList = useMemo(() => {
    let list = allMedia.filter((m) => {
      if (mlSrc !== "all" && m.src !== mlSrc) return false;
      if (mlCat !== "All" && m.cat !== mlCat) return false;
      if (mlDate !== "all" && m.d > +mlDate) return false;
      if (mlEnt === "dl" && !["inc", "ex", "addon"].includes(m.ent)) return false;
      if (mlEnt !== "all" && mlEnt !== "dl" && m.ent !== mlEnt) return false;
      if (
        mlSearch &&
        !(m.cap + " " + m.by + " " + m.loc + " " + m.cat).toLowerCase().includes(mlSearch)
      ) {
        return false;
      }
      return true;
    });

    if (mlSort === "old") {
      list = [...list].reverse();
    }
    return list;
  }, [allMedia, mlSrc, mlCat, mlDate, mlEnt, mlSearch, mlSort]);

  // ===== Action Handlers =====
  const copyStory = (s: WireStory, key: string) => {
    const txt = storyPlain(s);
    if (navigator.clipboard?.writeText) {
      navigator.clipboard.writeText(txt).catch(() => {});
    }
    flash(key, "Copied");
  };

  const downloadWordSingle = (s: WireStory, key: string) => {
    saveBlob(wordDoc([s]), `unb-${s.id}.doc`, "application/msword");
    flash(key, "Saved");
  };

  const downloadXmlSingle = (s: WireStory, key: string) => {
    saveBlob(xmlBundle([s]), `unb-${s.id}.xml`, "application/xml");
    flash(key, "Saved");
  };

  const downloadPhotoZip = async (s: WireStory, key: string) => {
    flash(key, "Downloading…");
    try {
      for (let i = 0; i < s.caps.length; i++) {
        await downloadMedia(typeof s.id === "number" ? s.id + i : i, "original");
      }
      flash(key, "Saved");
    } catch (e) {
      flash(key, "Failed");
    }
  };

  // Bulk Actions
  const getSelectedStoryObjects = () => {
    return stories.filter((s) => selectedStories.has(s.id) && s.ex !== "other");
  };

  const handleBulkWord = () => {
    const list = getSelectedStoryObjects();
    if (list.length > 0) {
      saveBlob(wordDoc(list), `unb-wire-bundle-${list.length}.doc`, "application/msword");
      flash("bulkWord", "Saved");
    }
  };

  const handleBulkXml = () => {
    const list = getSelectedStoryObjects();
    if (list.length > 0) {
      saveBlob(xmlBundle(list), `unb-wire-bundle-${list.length}.xml`, "application/xml");
      flash("bulkXml", "Saved");
    }
  };

  const handleBulkCsv = () => {
    const list = getSelectedStoryObjects();
    if (list.length > 0) {
      saveBlob(csvHeadlines(list), `unb-wire-headlines-${list.length}.csv`, "text/csv");
      flash("bulkCsv", "Saved");
    }
  };

  // Media Basket Zip
  const handleBasketZip = async () => {
    const items = allMedia.filter(
      (m) => selectedMedia.has(m.id) && m.type === "photo" && m.ent !== "lock" && m.ent !== "emb"
    );
    if (!items.length) {
      flash("basketZip", "Select downloadable photos first");
      return;
    }
    flash("basketZip", "Downloading…");
    try {
      for (let i = 0; i < items.length; i++) {
        const m = items[i];
        await downloadMedia(m.id, "original");
      }
      flash("basketZip", "Saved");
      const regularPhotos = items.filter((m) => m.ent !== "addon").length;
      setMediaQuota((q) => Math.max(0, q - regularPhotos));
    } catch (e) {
      flash("basketZip", "Failed");
    }
  };

  // Lightbox Trigger
  const openLightboxForStory = (s: WireStory, startIndex: number | "video") => {
    const items: { type: "photo" | "video"; grad: string; cap: string; i?: number }[] = s.caps.map(
      (c, i) => ({
        type: "photo",
        grad: GRADS[((typeof s.id === "number" ? s.id : 1) + i) % 8],
        cap: c,
        i,
      })
    );
    if (s.has_video) {
      items.push({
        type: "video",
        grad: GRADS[((typeof s.id === "number" ? s.id : 1) + 5) % 8],
        cap: "Video clip · 01:12 — UNB video",
      });
    }
    if (!items.length) return;
    const idx = startIndex === "video" ? items.length - 1 : startIndex;
    setLightbox({
      open: true,
      storyId: s.id,
      items,
      index: idx,
    });
  };

  // Jump to Wire & Search
  const goStorySearch = (kw: string) => {
    setTab("wire");
    setSearchQuery(kw);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  // Jump to Media Library & Search
  const goLibrarySearch = (kw: string) => {
    setTab("packs");
    setMlSearch(kw.toLowerCase());
  };

  // New Stories Pill Click
  const handleLoadNewStories = () => {
    const freshSet = new Set(pendingNew.map((s) => s.id));
    setStories((prev) => [...pendingNew, ...prev]);
    setFreshIds(freshSet);
    setPendingNew([]);
    window.scrollTo({ top: 0, behavior: "smooth" });
  };

  return (
    <>
      {/* ============ MASTHEAD ============ */}
      <header className="masthead">
        <div className="mast-inner">
          <a className="mast-brand" href="/">
            <div className="brand-mark">U</div>
            <div className="mast-name">
              UNB Wire<span>Client Portal</span>
            </div>
          </a>

          <div className="mast-right">
            <div className="mast-clock">
              <span className="live-dot" />
              <span id="clockTime">{clockText}</span>
            </div>

            <a className="admin-chip" href="http://localhost:8000/admin">
              ← Admin panel
            </a>

            <div className="user-wrap" ref={userWrapRef}>
              {clientInfo ? (
                <>
                  <button
                    className="user-chip"
                    id="userBtn"
                    type="button"
                    onClick={() => setUserDropOpen((o) => !o)}
                  >
                    <span className="user-avatar">{clientInfo.initials}</span>
                    <span className="user-meta">
                      <span className="user-name">{clientInfo.name}</span>
                      <span className="user-role">
                        <b>■</b> {clientInfo.tier} subscriber
                      </span>
                    </span>
                    <svg
                      viewBox="0 0 24 24"
                      fill="none"
                      strokeWidth="2"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                    >
                      <polyline points="6 9 12 15 18 9" />
                    </svg>
                  </button>

                  <div className={`drop ${userDropOpen ? "open" : ""}`} id="userDrop">
                    <div className="drop-account">
                      <div className="drop-tier">
                        ★ {clientInfo.tier} · renews {clientInfo.renews_at}
                      </div>
                      <div className="drop-quota">
                        <span>Wire</span>
                        <div className="dq-bar">
                          <span
                            style={{
                              width: `${(
                                (clientInfo.stories_used / clientInfo.stories_quota) *
                                100
                              ).toFixed(0)}%`,
                            }}
                          />
                        </div>
                        <b>
                          {clientInfo.stories_used}/{clientInfo.stories_quota}
                        </b>
                      </div>
                      <div className="drop-quota">
                        <span>Media</span>
                        <div className="dq-bar">
                          <span
                            className="warm"
                            id="dqMediaFill"
                            style={{
                              width: `${(
                                ((clientInfo.media_quota - mediaQuota) / clientInfo.media_quota) *
                                100
                              ).toFixed(0)}%`,
                            }}
                          />
                        </div>
                        <b id="dqMedia">
                          {clientInfo.media_quota - mediaQuota}/{clientInfo.media_quota}
                        </b>
                      </div>
                    </div>
                    <div className="drop-sep" />
                    <a className="drop-item" href="#downloads">
                      <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        strokeWidth="1.8"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                      >
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                        <polyline points="7 10 12 15 17 10" />
                        <line x1="12" y1="15" x2="12" y2="3" />
                      </svg>
                      My downloads
                    </a>
                    <a className="drop-item" href="http://localhost:8000/admin">
                      <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        strokeWidth="1.8"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                      >
                        <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4" />
                      </svg>
                      Subscription &amp; API keys
                    </a>
                    <div className="drop-sep" />
                    <button
                      className="drop-item"
                      style={{ width: "100%", textAlign: "left", background: "none", border: "none", cursor: "pointer", padding: "8px 12px", display: "flex", alignItems: "center", gap: "8px", fontSize: "13px", color: "inherit" }}
                      onClick={() => {
                        clearApiKey();
                        setClientInfo(null);
                        setUserDropOpen(false);
                      }}
                    >
                      <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        strokeWidth="1.8"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        style={{ width: "16px", height: "16px" }}
                      >
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                        <polyline points="16 17 21 12 16 7" />
                        <line x1="21" y1="12" x2="9" y2="12" />
                      </svg>
                      Log out
                    </button>
                  </div>
                </>
              ) : (
                <button className="action-btn" onClick={() => setShowLogin(true)} style={{ marginLeft: "16px" }}>
                  Login
                </button>
              )}
            </div>
          </div>
        </div>
      </header>

      {/* ============ MAIN LAYOUT ============ */}
      <div className={`page ${railHidden ? "rail-hidden" : ""}`}>
        {/* ============ LEFT RAIL ============ */}
        <aside>
          {(() => {
            const info = clientInfo || CLIENT_INFO;
            return (
              <div className="rail-card">
                <span className="sub-tier">★ {info.tier}</span>
                <div className="sub-name">{info.name}</div>
                <div className="sub-renew">
                  Renews {info.renews_at} · English wire + media pack
                </div>
                {clientInfo && (
                  <>
                    <div className="quota">
                      <div className="quota-label">
                        <span>Wire downloads</span>
                        <b>
                          {info.stories_used} / {info.stories_quota}
                        </b>
                      </div>
                      <div className="quota-bar">
                        <span
                          className="quota-fill"
                          style={{
                            width: `${(
                              (info.stories_used / info.stories_quota) *
                              100
                            ).toFixed(1)}%`,
                          }}
                        />
                      </div>
                    </div>
                    <div className="quota">
                      <div className="quota-label">
                        <span>Media downloads</span>
                        <b id="mediaQuotaLabel">
                          {info.media_quota - mediaQuota} / {info.media_quota}
                        </b>
                      </div>
                      <div className="quota-bar">
                        <span
                          className="quota-fill warm"
                          id="mediaQuotaFill"
                          style={{
                            width: `${(
                              ((info.media_quota - mediaQuota) / info.media_quota) *
                              100
                            ).toFixed(1)}%`,
                          }}
                        />
                      </div>
                    </div>
                  </>
                )}
                <a
                  className="drop-item"
                  href="http://localhost:8000/admin"
                  style={{ marginTop: "10px", paddingLeft: "0" }}
                >
                  <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    strokeWidth="1.8"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  >
                    <circle cx="12" cy="12" r="3" />
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" />
                  </svg>
                  Delivery settings (FTP · API · alerts) →
                </a>
              </div>
            );
          })()}

          <div className="rail-card" id="wireFiltersCard" hidden={tab !== "wire"}>
            <div className="rail-title">Filters</div>
            <div className="filter-group">
              <label className="filter-label">Published</label>
              <select
                className="select-input"
                id="fDate"
                value={filterDate}
                onChange={(e) => setFilterDate(e.target.value)}
              >
                <option value="all">Any time</option>
                <option value="60">Last hour</option>
                <option value="360">Last 6 hours</option>
                <option value="1440">Last 24 hours</option>
              </select>
            </div>
            <div className="filter-group">
              <label className="filter-label">Media</label>
              <select
                className="select-input"
                id="fMedia"
                value={filterMedia}
                onChange={(e) => setFilterMedia(e.target.value)}
              >
                <option value="all">All stories</option>
                <option value="photo">With photos</option>
                <option value="video">With video</option>
                <option value="text">Text only</option>
              </select>
            </div>
            <div className="filter-group">
              <label className="check-line">
                <input
                  type="checkbox"
                  id="fExclusive"
                  checked={filterExclusive}
                  onChange={(e) => setFilterExclusive(e.target.checked)}
                />
                Exclusive to Daily Star only
              </label>
            </div>
            <button
              className="clear-filters"
              id="clearFilters"
              type="button"
              onClick={() => {
                setFilterDate("all");
                setFilterMedia("all");
                setFilterExclusive(false);
                setSelectedCat("All");
              }}
            >
              Clear all filters
            </button>
          </div>

          <div className="rail-card" id="savedSearchesCard" hidden={tab !== "wire"}>
            <div className="rail-title">Saved searches</div>
            {SAVED_SEARCHES.map((item) => (
              <div
                key={item.q}
                className="saved-item"
                data-q={item.q}
                onClick={() => {
                  setSearchQuery(item.q);
                }}
              >
                <span className="term">
                  <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    strokeWidth="1.8"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  >
                    <circle cx="11" cy="11" r="8" />
                    <path d="m21 21-4.35-4.35" />
                  </svg>
                  <span>{item.name}</span>
                </span>
              </div>
            ))}
          </div>

          <div className="rail-card">
            <div className="rail-title">My recent downloads</div>
            <div className="dl-item">
              <svg
                viewBox="0 0 24 24"
                fill="none"
                strokeWidth="1.8"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
              </svg>
              <span className="dl-name">Rizvi returns to BNP&apos;s Nayapaltan… (Word)</span>
              <span className="dl-time">2m</span>
            </div>
            <div className="dl-item">
              <svg
                viewBox="0 0 24 24"
                fill="none"
                strokeWidth="1.8"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <rect x="3" y="3" width="18" height="18" rx="2" />
                <circle cx="8.5" cy="8.5" r="1.5" />
                <path d="m21 15-5-5L5 21" />
              </svg>
              <span className="dl-name">Bangla QR photos (ZIP, 2)</span>
              <span className="dl-time">18m</span>
            </div>
            <div className="dl-item">
              <svg
                viewBox="0 0 24 24"
                fill="none"
                strokeWidth="1.8"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <polyline points="16 18 22 12 16 6" />
                <polyline points="8 6 2 12 8 18" />
              </svg>
              <span className="dl-name">Morning wire bundle (XML, 12)</span>
              <span className="dl-time">3h</span>
            </div>
            <div className="dl-item">
              <svg
                viewBox="0 0 24 24"
                fill="none"
                strokeWidth="1.8"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                <polyline points="14 2 14 8 20 8" />
              </svg>
              <span className="dl-name">Bishkhali riverbanks… (Word)</span>
              <span className="dl-time">5h</span>
            </div>
          </div>
        </aside>

        {/* ============ FEED CONTENT ============ */}
        <main>
          {/* Top Control Bar */}
          <div className="feed-top">
            <button
              className={`rail-toggle ${!railHidden ? "active" : ""}`}
              id="railToggle"
              type="button"
              title={railHidden ? "Show account & filters" : "Hide account & filters"}
              onClick={() => setRailHidden((h) => !h)}
            >
              <svg
                viewBox="0 0 24 24"
                fill="none"
                strokeWidth="1.8"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <rect x="3" y="3" width="18" height="18" rx="2" />
                <line x1="9" y1="3" x2="9" y2="21" />
              </svg>
            </button>

            <div className="feed-tabs">
              <button
                className={`feed-tab ${tab === "wire" ? "active" : ""}`}
                type="button"
                data-tab="wire"
                onClick={() => setTab("wire")}
              >
                News wire
              </button>
              <button
                className={`feed-tab ${tab === "packs" ? "active" : ""}`}
                type="button"
                data-tab="packs"
                onClick={() => setTab("packs")}
              >
                Media library
              </button>
              <button
                className={`feed-tab ${tab === "photos" ? "active" : ""}`}
                type="button"
                data-tab="photos"
                onClick={() => setTab("photos")}
              >
                UNB Photos
              </button>
            </div>

            <span
              className="live-note"
              id="liveNote"
              style={{ visibility: tab === "wire" ? "visible" : "hidden" }}
            >
              <span className="live-dot" style={{ backgroundColor: wsStatus === 'connected' ? '#10b981' : wsStatus === 'connecting' ? '#f59e0b' : '#ef4444' }} />
              {wsStatus === 'connected' ? 'Live — connected' : wsStatus === 'connecting' ? 'Live — connecting...' : 'Live — disconnected'}
            </span>

            <span className="spacer" />

            {/* Wire View Switcher */}
            <div
              className="view-switch"
              id="viewSwitch"
              style={{ display: tab === "wire" ? "flex" : "none" }}
            >
              <button
                className={`view-btn ${wireView === "cards" ? "active" : ""}`}
                type="button"
                data-view="cards"
                title="Card view — full context"
                onClick={() => setWireView("cards")}
              >
                <svg viewBox="0 0 24 24" fill="none" strokeWidth="2" strokeLinecap="round">
                  <rect x="3" y="3" width="18" height="7" rx="1.5" />
                  <rect x="3" y="14" width="18" height="7" rx="1.5" />
                </svg>
                Cards
              </button>
              <button
                className={`view-btn ${wireView === "grid" ? "active" : ""}`}
                type="button"
                data-view="grid"
                title="Grid view — visual scan"
                onClick={() => setWireView("grid")}
              >
                <svg viewBox="0 0 24 24" fill="none" strokeWidth="2" strokeLinecap="round">
                  <rect x="3" y="3" width="8" height="8" rx="1.5" />
                  <rect x="13" y="3" width="8" height="8" rx="1.5" />
                  <rect x="3" y="13" width="8" height="8" rx="1.5" />
                  <rect x="13" y="13" width="8" height="8" rx="1.5" />
                </svg>
                Grid
              </button>
              <button
                className={`view-btn ${wireView === "table" ? "active" : ""}`}
                type="button"
                data-view="table"
                title="Table view — dense terminal"
                onClick={() => setWireView("table")}
              >
                <svg viewBox="0 0 24 24" fill="none" strokeWidth="2" strokeLinecap="round">
                  <rect x="3" y="4" width="18" height="16" rx="2" />
                  <line x1="3" y1="10" x2="21" y2="10" />
                  <line x1="3" y1="15" x2="21" y2="15" />
                </svg>
                Table
              </button>
            </div>

            {/* Wire Sort */}
            <div
              className="sort-wrap"
              id="sortWrap"
              style={{ display: tab === "wire" ? "flex" : "none" }}
            >
              Sort
              <select
                className="select-input"
                id="sortSel"
                value={sortOrder}
                onChange={(e) => setSortOrder(e.target.value as "new" | "old")}
              >
                <option value="new">Latest first</option>
                <option value="old">Oldest first</option>
              </select>
            </div>
          </div>

          {/* ============ TAB 1: NEWS WIRE ============ */}
          <div id="wirePanel" hidden={tab !== "wire"}>
            {/* Omnisearch */}
            <div className="omni">
              <span className="omni-icon">
                <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  strokeWidth="1.8"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  <circle cx="11" cy="11" r="8" />
                  <path d="m21 21-4.35-4.35" />
                </svg>
              </span>
              <input
                type="text"
                id="omniInput"
                ref={omniInputRef}
                value={searchQuery}
                placeholder="Search the wire — headline, keywords, tags, even image captions…"
                onChange={(e) => setSearchQuery(e.target.value)}
              />
              <select
                className="omni-scope"
                id="omniScope"
                value={omniScope}
                onChange={(e) => setOmniScope(e.target.value as any)}
              >
                <option value="all">Everywhere</option>
                <option value="headline">Headlines</option>
                <option value="tags">Tags</option>
                <option value="captions">Image captions</option>
              </select>
              <kbd>/</kbd>
            </div>

            {/* Search Meta */}
            {searchQuery ? (
              <div className="search-meta show" id="searchMeta">
                <b>{filteredStories.length}</b> result
                {filteredStories.length === 1 ? "" : "s"} for “<b>{esc(searchQuery)}</b>” in{" "}
                <b>
                  {omniScope === "all"
                    ? "Everywhere"
                    : omniScope === "headline"
                    ? "Headlines"
                    : omniScope === "tags"
                    ? "Tags"
                    : "Image captions"}
                </b>
              </div>
            ) : null}

            {/* Category Chips */}
            <div className="cat-chips" id="catChips">
              {["All", "Bangladesh", "Politics", "World", "Sports", "Business", "Environment"].map(
                (cat) => (
                  <button
                    key={cat}
                    type="button"
                    className={`cat-chip ${selectedCat === cat ? "active" : ""}`}
                    data-cat={cat}
                    onClick={() => setSelectedCat(cat)}
                  >
                    {cat}
                  </button>
                )
              )}
            </div>

            {/* New Stories Pill */}
            {pendingNew.length > 0 ? (
              <button
                className="new-pill show"
                id="newPill"
                type="button"
                onClick={handleLoadNewStories}
              >
                ↑ {pendingNew.length} new {pendingNew.length === 1 ? "story" : "stories"} on the wire —
                tap to load
              </button>
            ) : null}

            {/* Bulk Selection Bar */}
            <div className={`bulk-bar ${selectedStories.size > 0 ? "show" : ""}`} id="bulkBar">
              <span className="bulk-count" id="bulkCount">
                {selectedStories.size} selected
              </span>
              <button
                className="bulk-btn accent"
                id="bulkWord"
                type="button"
                onClick={handleBulkWord}
              >
                <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  strokeWidth="1.8"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                  <polyline points="7 10 12 15 17 10" />
                  <line x1="12" y1="15" x2="12" y2="3" />
                </svg>
                {flashStates["bulkWord"] || "Word bundle"}
              </button>
              <button className="bulk-btn" id="bulkXml" type="button" onClick={handleBulkXml}>
                {flashStates["bulkXml"] || "XML bundle"}
              </button>
              <button className="bulk-btn" id="bulkCsv" type="button" onClick={handleBulkCsv}>
                {flashStates["bulkCsv"] || "CSV (headlines)"}
              </button>
              <button
                className="bulk-clear"
                id="bulkClear"
                type="button"
                onClick={() => setSelectedStories(new Set())}
              >
                Clear selection
              </button>
            </div>

            {/* Empty State */}
            {filteredStories.length === 0 ? (
              <div className="feed-empty">
                No stories match — try a different keyword, tag, or caption search.
              </div>
            ) : null}

            {/* VIEW 1: CARDS VIEW */}
            {wireView === "cards" && filteredStories.length > 0 ? (
              <div id="feedList">
                {filteredStories.map((s) => {
                  const match = matchWhere(s);
                  const isLocked = s.ex === "other";
                  const catClass = CAT_CLASS[s.category] ? ` ${CAT_CLASS[s.category]}` : "";
                  const isSelected = selectedStories.has(s.id);
                  const isExpanded = expandedCards.has(s.id);
                  const isFresh = freshIds.has(s.id);

                  return (
                    <div
                      key={s.id}
                      className={`story${isLocked ? " locked" : ""}${isFresh ? " fresh" : ""}${
                        isSelected ? " selected" : ""
                      }`}
                      data-id={s.id}
                    >
                      <label className="st-check">
                        <input
                          type="checkbox"
                          checked={isSelected}
                          disabled={isLocked}
                          onChange={(e) => {
                            setSelectedStories((prev) => {
                              const next = new Set(prev);
                              e.target.checked ? next.add(s.id) : next.delete(s.id);
                              return next;
                            });
                          }}
                        />
                      </label>

                      <div className="st-main">
                        <div className="st-meta">
                          <span className={`st-cat${catClass}`}>{s.category}</span>
                          <span className="st-time">
                            {typeof s.mins === "number" ? timeAgo(s.mins) : timeAgo(s.published_at)}
                          </span>
                          {s.ex === "you" && <span className="st-badge you">★ Exclusive to you</span>}
                          {s.has_video && !isLocked && (
                            <span className="st-badge vid">▶ Video</span>
                          )}
                        </div>

                        <a
                          className="st-head"
                          onClick={() => {
                            if (!isLocked) {
                              setExpandedCards((prev) => {
                                const next = new Set(prev);
                                next.has(s.id) ? next.delete(s.id) : next.add(s.id);
                                return next;
                              });
                            }
                          }}
                        >
                          {s.headline}
                        </a>
                        <div className="st-brief">{s.brief}</div>

                        {/* Search Matches */}
                        {match === "tag" && (
                          <div className="st-match">
                            Matched a <b>tag</b> on this story
                          </div>
                        )}
                        {match && match !== "headline" && match !== "tag" && (
                          <div className="st-match">
                            Matched an <b>image caption</b>: “{match}”
                          </div>
                        )}

                        <div className="st-sub">
                          <div className="st-tags">
                            {s.tags.map((tag) => (
                              <span
                                key={tag}
                                className="st-tag"
                                onClick={() => setSearchQuery(tag)}
                              >
                                #{tag}
                              </span>
                            ))}
                          </div>

                          {s.caps.length > 0 && (
                            <span className="st-media-note">
                              <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                strokeWidth="1.8"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                              >
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <circle cx="8.5" cy="8.5" r="1.5" />
                                <path d="m21 15-5-5L5 21" />
                              </svg>
                              {s.caps.length} photo{s.caps.length > 1 ? "s" : ""}
                            </span>
                          )}
                          {s.has_video && (
                            <span className="st-media-note">
                              <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                strokeWidth="1.8"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                              >
                                <polygon points="23 7 16 12 23 17 23 7" />
                                <rect x="1" y="5" width="15" height="14" rx="2" />
                              </svg>
                              1 video
                            </span>
                          )}
                        </div>

                        {/* Locked Story Note */}
                        {isLocked ? (
                          <div className="lock-note">
                            <svg
                              viewBox="0 0 24 24"
                              fill="none"
                              strokeWidth="1.8"
                              strokeLinecap="round"
                              strokeLinejoin="round"
                            >
                              <rect x="3" y="11" width="18" height="11" rx="2" />
                              <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                            </svg>
                            <div className="lock-text">
                              <b>Exclusive to another subscriber.</b> Full text, photos and video of
                              this story are not in your package.
                            </div>
                            <button
                              className="lock-cta"
                              type="button"
                              onClick={(e) => {
                                (e.target as HTMLButtonElement).textContent = "✓ Request sent";
                              }}
                            >
                              Add to package
                            </button>
                          </div>
                        ) : (
                          <>
                            {/* Actions Toolbar */}
                            <div className="st-actions">
                              <button
                                className="st-btn preview"
                                type="button"
                                onClick={() => {
                                  setExpandedCards((prev) => {
                                    const next = new Set(prev);
                                    next.has(s.id) ? next.delete(s.id) : next.add(s.id);
                                    return next;
                                  });
                                }}
                              >
                                {isExpanded ? "Preview ▴" : "Preview ▾"}
                              </button>
                              <button
                                className="st-btn"
                                type="button"
                                onClick={() => copyStory(s, `copy_${s.id}`)}
                              >
                                <svg
                                  viewBox="0 0 24 24"
                                  fill="none"
                                  strokeWidth="1.8"
                                  strokeLinecap="round"
                                  strokeLinejoin="round"
                                >
                                  <rect x="9" y="9" width="13" height="13" rx="2" />
                                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                </svg>
                                {flashStates[`copy_${s.id}`] || "Copy text"}
                              </button>
                              <button
                                className="st-btn"
                                type="button"
                                onClick={() => downloadWordSingle(s, `word_${s.id}`)}
                              >
                                <svg
                                  viewBox="0 0 24 24"
                                  fill="none"
                                  strokeWidth="1.8"
                                  strokeLinecap="round"
                                  strokeLinejoin="round"
                                >
                                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                  <polyline points="7 10 12 15 17 10" />
                                  <line x1="12" y1="15" x2="12" y2="3" />
                                </svg>
                                {flashStates[`word_${s.id}`] || "Word"}
                              </button>
                              <button
                                className="st-btn"
                                type="button"
                                onClick={() => downloadXmlSingle(s, `xml_${s.id}`)}
                              >
                                <svg
                                  viewBox="0 0 24 24"
                                  fill="none"
                                  strokeWidth="1.8"
                                  strokeLinecap="round"
                                  strokeLinejoin="round"
                                >
                                  <polyline points="16 18 22 12 16 6" />
                                  <polyline points="8 6 2 12 8 18" />
                                </svg>
                                {flashStates[`xml_${s.id}`] || "XML"}
                              </button>
                              {s.caps.length > 0 && (
                                <button
                                  className="st-btn"
                                  type="button"
                                  onClick={() => downloadPhotoZip(s, `zip_${s.id}`)}
                                >
                                  <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    strokeWidth="1.8"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                  >
                                    <rect x="3" y="3" width="18" height="18" rx="2" />
                                    <circle cx="8.5" cy="8.5" r="1.5" />
                                    <path d="m21 15-5-5L5 21" />
                                  </svg>
                                  {flashStates[`zip_${s.id}`] || `Photos (${s.caps.length}) ZIP`}
                                </button>
                              )}
                            </div>

                            {/* Full Dispatch Expander */}
                            <div className={`st-body ${isExpanded ? "open" : ""}`} id={`body-${s.id}`}>
                              {(s.body_html || s.brief)
                                .replace(/<[^>]+>/g, "\n\n")
                                .split("\n\n")
                                .filter(Boolean)
                                .map((p, pIdx) => (
                                  <p key={pIdx}>{p}</p>
                                ))}
                              <p className="st-signoff">END/UNB/{s.id}</p>

                              {(s.caps.length > 0 || s.has_video) && (
                                <div className="st-photos">
                                  {s.caps.map((c, i) => (
                                    <button
                                      key={i}
                                      type="button"
                                      className={`st-photo ${
                                        GRADS[((typeof s.id === "number" ? s.id : 1) + i) % 8]
                                      }`}
                                      title={c}
                                      onClick={() => openLightboxForStory(s, i)}
                                    >
                                      <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        strokeWidth="1.5"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                      >
                                        <rect x="3" y="3" width="18" height="18" rx="2" />
                                        <circle cx="8.5" cy="8.5" r="1.5" />
                                        <path d="m21 15-5-5L5 21" />
                                      </svg>
                                    </button>
                                  ))}
                                  {s.has_video && (
                                    <button
                                      type="button"
                                      className={`st-photo ${
                                        GRADS[((typeof s.id === "number" ? s.id : 1) + 5) % 8]
                                      }`}
                                      title="Video clip"
                                      onClick={() => openLightboxForStory(s, "video")}
                                    >
                                      <svg
                                        viewBox="0 0 24 24"
                                        fill="rgba(255,255,255,0.9)"
                                        stroke="none"
                                      >
                                        <polygon points="7 4 20 12 7 20 7 4" />
                                      </svg>
                                      <span className="st-photo-dur">01:12</span>
                                    </button>
                                  )}
                                </div>
                              )}

                              {s.caps.length > 0 && (
                                <div className="caps-box" style={{ marginTop: "12px" }}>
                                  <div className="caps-title">
                                    Image captions in this story — searchable
                                  </div>
                                  {s.caps.map((c, cIdx) => (
                                    <div key={cIdx} className="cap-line">
                                      <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        strokeWidth="1.8"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                      >
                                        <rect x="3" y="3" width="18" height="18" rx="2" />
                                        <circle cx="8.5" cy="8.5" r="1.5" />
                                        <path d="m21 15-5-5L5 21" />
                                      </svg>
                                      <span>{c}</span>
                                    </div>
                                  ))}
                                </div>
                              )}
                            </div>
                          </>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            ) : null}

            {/* VIEW 2: GRID VIEW (Visual Scan) */}
            {wireView === "grid" && filteredStories.length > 0 ? (
              <div id="feedGrid">
                <div className="wire-grid">
                  {filteredStories.map((s) => {
                    const isLocked = s.ex === "other";
                    const catClass = CAT_CLASS[s.category] ? ` ${CAT_CLASS[s.category]}` : "";
                    const isSelected = selectedStories.has(s.id);
                    const isOpen = expandedGrid.has(s.id);
                    const isFresh = freshIds.has(s.id);
                    const tags = s.tags.slice(0, 2);

                    return (
                      <div
                        key={s.id}
                        className={`wg-card${isSelected ? " selected" : ""}${
                          isFresh ? " fresh" : ""
                        }${isOpen ? " open" : ""}${isLocked ? " locked" : ""}`}
                        data-id={s.id}
                      >
                        <label className="wg-check">
                          <input
                            type="checkbox"
                            checked={isSelected}
                            disabled={isLocked}
                            onChange={(e) => {
                              setSelectedStories((prev) => {
                                const next = new Set(prev);
                                e.target.checked ? next.add(s.id) : next.delete(s.id);
                                return next;
                              });
                            }}
                          />
                        </label>

                        {!isLocked && (s.caps.length > 0 || s.has_video) && (
                          <button
                            type="button"
                            className={`wg-thumb ${
                              GRADS[(typeof s.id === "number" ? s.id : 1) % 8]
                            }`}
                            title={s.caps[0] || "Video clip"}
                            onClick={() => openLightboxForStory(s, s.caps.length > 0 ? 0 : "video")}
                          >
                            {s.has_video && !s.caps.length ? (
                              <svg
                                viewBox="0 0 24 24"
                                fill="rgba(255,255,255,0.9)"
                                stroke="none"
                              >
                                <polygon points="7 4 20 12 7 20 7 4" />
                              </svg>
                            ) : (
                              <svg
                                viewBox="0 0 24 24"
                                fill="none"
                                strokeWidth="1.8"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                              >
                                <rect x="3" y="3" width="18" height="18" rx="2" />
                                <circle cx="8.5" cy="8.5" r="1.5" />
                                <path d="m21 15-5-5L5 21" />
                              </svg>
                            )}
                            <span className="wg-count">
                              {s.caps.length > 0
                                ? `${s.caps.length} photo${s.caps.length > 1 ? "s" : ""}`
                                : "video"}
                            </span>
                          </button>
                        )}

                        <div className="wg-meta">
                          <span className={`st-cat${catClass}`}>{s.category}</span>
                          <span className="st-time">
                            {typeof s.mins === "number" ? timeAgo(s.mins) : timeAgo(s.published_at)}
                          </span>
                          {s.ex === "you" && <span className="st-badge you">★ Exclusive</span>}
                          {s.has_video && !isLocked && <span className="st-badge vid">▶ Video</span>}
                        </div>

                        <a
                          className="wg-head"
                          onClick={() => {
                            if (!isLocked) {
                              setExpandedGrid((prev) => {
                                const next = new Set(prev);
                                next.has(s.id) ? next.delete(s.id) : next.add(s.id);
                                return next;
                              });
                            }
                          }}
                        >
                          {s.headline}
                        </a>
                        <div className="wg-brief">{s.brief}</div>

                        {isLocked ? (
                          <div className="wg-lock">
                            <b>Exclusive to another subscriber.</b> Full text, photos and video of
                            this story are not in your package.
                            <br />
                            <button
                              className="st-btn"
                              type="button"
                              style={{ marginTop: "7px" }}
                              onClick={(e) => {
                                (e.target as HTMLButtonElement).textContent = "✓ Request sent";
                              }}
                            >
                              Add to package
                            </button>
                          </div>
                        ) : (
                          <>
                            <div className="wg-foot">
                              <div className="st-tags">
                                {tags.map((tag) => (
                                  <span
                                    key={tag}
                                    className="st-tag"
                                    onClick={() => setSearchQuery(tag)}
                                  >
                                    #{tag}
                                  </span>
                                ))}
                                {s.tags.length > 2 && (
                                  <span className="st-tag">+{s.tags.length - 2}</span>
                                )}
                              </div>
                              <span className="wg-media">
                                {s.caps.length > 0 && (
                                  <span>
                                    <svg
                                      viewBox="0 0 24 24"
                                      fill="none"
                                      strokeWidth="1.8"
                                      strokeLinecap="round"
                                      strokeLinejoin="round"
                                    >
                                      <rect x="3" y="3" width="18" height="18" rx="2" />
                                      <circle cx="8.5" cy="8.5" r="1.5" />
                                      <path d="m21 15-5-5L5 21" />
                                    </svg>
                                    {s.caps.length}
                                  </span>
                                )}
                                {s.has_video && (
                                  <span>
                                    <svg
                                      viewBox="0 0 24 24"
                                      fill="none"
                                      strokeWidth="1.8"
                                      strokeLinecap="round"
                                      strokeLinejoin="round"
                                    >
                                      <polygon points="23 7 16 12 23 17 23 7" />
                                      <rect x="1" y="5" width="15" height="14" rx="2" />
                                    </svg>
                                    1
                                  </span>
                                )}
                              </span>
                            </div>

                            <div className="wg-acts">
                              <button
                                className="wg-read"
                                type="button"
                                onClick={() => {
                                  setExpandedGrid((prev) => {
                                    const next = new Set(prev);
                                    next.has(s.id) ? next.delete(s.id) : next.add(s.id);
                                    return next;
                                  });
                                }}
                              >
                                {isOpen ? "Close" : "Read"}
                              </button>
                              <span className="wg-grow" />
                              <button
                                className="tw-act"
                                type="button"
                                title="Copy text"
                                onClick={() => copyStory(s, `copy_${s.id}`)}
                              >
                                <svg
                                  viewBox="0 0 24 24"
                                  fill="none"
                                  strokeWidth="1.8"
                                  strokeLinecap="round"
                                  strokeLinejoin="round"
                                >
                                  <rect x="9" y="9" width="13" height="13" rx="2" />
                                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                </svg>
                              </button>
                              <button
                                className="tw-act"
                                type="button"
                                title="Download Word"
                                onClick={() => downloadWordSingle(s, `word_${s.id}`)}
                              >
                                <svg
                                  viewBox="0 0 24 24"
                                  fill="none"
                                  strokeWidth="1.8"
                                  strokeLinecap="round"
                                  strokeLinejoin="round"
                                >
                                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                  <polyline points="7 10 12 15 17 10" />
                                  <line x1="12" y1="15" x2="12" y2="3" />
                                </svg>
                              </button>
                              <button
                                className="tw-act"
                                type="button"
                                title="Download XML"
                                onClick={() => downloadXmlSingle(s, `xml_${s.id}`)}
                              >
                                <svg
                                  viewBox="0 0 24 24"
                                  fill="none"
                                  strokeWidth="1.8"
                                  strokeLinecap="round"
                                  strokeLinejoin="round"
                                >
                                  <polyline points="16 18 22 12 16 6" />
                                  <polyline points="8 6 2 12 8 18" />
                                </svg>
                              </button>
                              {s.caps.length > 0 && (
                                <button
                                  className="tw-act"
                                  type="button"
                                  title={`Photos (${s.caps.length}) ZIP`}
                                  onClick={() => downloadPhotoZip(s, `zip_${s.id}`)}
                                >
                                  <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    strokeWidth="1.8"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                  >
                                    <rect x="3" y="3" width="18" height="18" rx="2" />
                                    <circle cx="8.5" cy="8.5" r="1.5" />
                                    <path d="m21 15-5-5L5 21" />
                                  </svg>
                                </button>
                              )}
                            </div>

                            <div className="wg-body">
                              {(s.body_html || s.brief)
                                .replace(/<[^>]+>/g, "\n\n")
                                .split("\n\n")
                                .filter(Boolean)
                                .map((p, pIdx) => (
                                  <p key={pIdx}>{p}</p>
                                ))}
                              <p className="st-signoff">END/UNB/{s.id}</p>
                            </div>
                          </>
                        )}
                      </div>
                    );
                  })}
                </div>
              </div>
            ) : null}

            {/* VIEW 3: TABLE VIEW (Terminal) */}
            {wireView === "table" && filteredStories.length > 0 ? (
              <div id="feedTable">
                <div className={`tw-wrap ${twDensity === "compact" ? "tw-compact" : ""}`}>
                  <div className="tw-toolbar">
                    <span>
                      <b>{filteredStories.length}</b> stories · click a row to preview inline
                    </span>
                    <span className="kbd-hint">j / k move · x select · Enter preview</span>
                    <div className="tw-density">
                      <button
                        type="button"
                        className={twDensity === "cozy" ? "active" : ""}
                        onClick={() => setTwDensity("cozy")}
                      >
                        Comfortable
                      </button>
                      <button
                        type="button"
                        className={twDensity === "compact" ? "active" : ""}
                        onClick={() => setTwDensity("compact")}
                      >
                        Compact
                      </button>
                    </div>
                  </div>

                  <div className="tw-scroll">
                    <table className="tw">
                      <thead>
                        <tr>
                          <th className="tw-check">
                            <input
                              type="checkbox"
                              id="twCheckAll"
                              title="Select all visible"
                              checked={
                                filteredStories.filter((s) => s.ex !== "other").length > 0 &&
                                filteredStories
                                  .filter((s) => s.ex !== "other")
                                  .every((s) => selectedStories.has(s.id))
                              }
                              onChange={(e) => {
                                const chk = e.target.checked;
                                setSelectedStories((prev) => {
                                  const next = new Set(prev);
                                  filteredStories.forEach((s) => {
                                    if (s.ex !== "other") {
                                      chk ? next.add(s.id) : next.delete(s.id);
                                    }
                                  });
                                  return next;
                                });
                              }}
                            />
                          </th>
                          <th
                            className="sortable"
                            onClick={() => {
                              setTwSort(null);
                              setSortOrder((o) => (o === "new" ? "old" : "new"));
                            }}
                          >
                            Time {twSort === null ? (sortOrder === "new" ? "↓" : "↑") : ""}
                          </th>
                          <th
                            className="sortable"
                            onClick={() => {
                              setTwSort((s) => (s === "cat" ? null : "cat"));
                            }}
                          >
                            Category {twSort === "cat" ? "↓" : ""}
                          </th>
                          <th>Headline</th>
                          <th>Media</th>
                          <th title="Exclusive to you">★</th>
                          <th style={{ textAlign: "right" }}>Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        {filteredStories.map((s, idx) => {
                          const isLocked = s.ex === "other";
                          const dot = TW_CAT_DOT[s.category] || "#7c7f8c";
                          const isSelected = selectedStories.has(s.id);
                          const isExpanded = expandedTable.has(s.id);
                          const isRowActive = twActiveIdx === idx;
                          const isFresh = freshIds.has(s.id);

                          return (
                            <React.Fragment key={s.id}>
                              <tr
                                className={`tw-row${isSelected ? " selected" : ""}${
                                  isLocked ? " tw-locked" : ""
                                }${isRowActive ? " tw-active" : ""}${isFresh ? " fresh" : ""}`}
                                onClick={() => {
                                  if (!isLocked) {
                                    setTwActiveIdx(idx);
                                    setExpandedTable((prev) => {
                                      const next = new Set(prev);
                                      next.has(s.id) ? next.delete(s.id) : next.add(s.id);
                                      return next;
                                    });
                                  }
                                }}
                              >
                                <td
                                  className="tw-check"
                                  onClick={(e) => e.stopPropagation()}
                                >
                                  <input
                                    type="checkbox"
                                    checked={isSelected}
                                    disabled={isLocked}
                                    onChange={(e) => {
                                      setSelectedStories((prev) => {
                                        const next = new Set(prev);
                                        e.target.checked ? next.add(s.id) : next.delete(s.id);
                                        return next;
                                      });
                                    }}
                                  />
                                </td>
                                <td className="tw-time">
                                  {typeof s.mins === "number" ? timeAgo(s.mins) : timeAgo(s.published_at)}
                                </td>
                                <td>
                                  <span className="tw-cat">
                                    <i style={{ background: dot }} />
                                    {s.category}
                                  </span>
                                </td>
                                <td className="tw-head-cell">
                                  {s.headline}
                                  {isLocked && <span className="tw-lock-ic"> 🔒</span>}
                                </td>
                                <td>
                                  <span className="tw-media">
                                    {s.caps.length > 0 && (
                                      <span>
                                        <svg
                                          viewBox="0 0 24 24"
                                          fill="none"
                                          strokeWidth="1.8"
                                          strokeLinecap="round"
                                          strokeLinejoin="round"
                                        >
                                          <rect x="3" y="3" width="18" height="18" rx="2" />
                                          <circle cx="8.5" cy="8.5" r="1.5" />
                                          <path d="m21 15-5-5L5 21" />
                                        </svg>
                                        {s.caps.length}
                                      </span>
                                    )}
                                    {s.has_video && (
                                      <span>
                                        <svg
                                          viewBox="0 0 24 24"
                                          fill="none"
                                          strokeWidth="1.8"
                                          strokeLinecap="round"
                                          strokeLinejoin="round"
                                        >
                                          <polygon points="23 7 16 12 23 17 23 7" />
                                          <rect x="1" y="5" width="15" height="14" rx="2" />
                                        </svg>
                                        1
                                      </span>
                                    )}
                                  </span>
                                </td>
                                <td className="tw-ex">{s.ex === "you" ? "★" : ""}</td>
                                <td
                                  className="tw-acts"
                                  onClick={(e) => e.stopPropagation()}
                                >
                                  {isLocked ? (
                                    <button
                                      className="st-btn lock-cta-inline"
                                      type="button"
                                      onClick={(e) => {
                                        (e.target as HTMLButtonElement).textContent = "✓ Request sent";
                                      }}
                                    >
                                      Add to package
                                    </button>
                                  ) : (
                                    <>
                                      <button
                                        className="tw-act"
                                        type="button"
                                        title="Copy text"
                                        onClick={() => copyStory(s, `copy_${s.id}`)}
                                      >
                                        <svg
                                          viewBox="0 0 24 24"
                                          fill="none"
                                          strokeWidth="1.8"
                                          strokeLinecap="round"
                                          strokeLinejoin="round"
                                        >
                                          <rect x="9" y="9" width="13" height="13" rx="2" />
                                          <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                        </svg>
                                      </button>
                                      <button
                                        className="tw-act"
                                        type="button"
                                        title="Download Word"
                                        onClick={() => downloadWordSingle(s, `word_${s.id}`)}
                                      >
                                        <svg
                                          viewBox="0 0 24 24"
                                          fill="none"
                                          strokeWidth="1.8"
                                          strokeLinecap="round"
                                          strokeLinejoin="round"
                                        >
                                          <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                          <polyline points="7 10 12 15 17 10" />
                                          <line x1="12" y1="15" x2="12" y2="3" />
                                        </svg>
                                      </button>
                                      <button
                                        className="tw-act"
                                        type="button"
                                        title="Download XML"
                                        onClick={() => downloadXmlSingle(s, `xml_${s.id}`)}
                                      >
                                        <svg
                                          viewBox="0 0 24 24"
                                          fill="none"
                                          strokeWidth="1.8"
                                          strokeLinecap="round"
                                          strokeLinejoin="round"
                                        >
                                          <polyline points="16 18 22 12 16 6" />
                                          <polyline points="8 6 2 12 8 18" />
                                        </svg>
                                      </button>
                                    </>
                                  )}
                                </td>
                              </tr>

                              {/* Terminal Detail Row */}
                              {isExpanded && !isLocked && (
                                <tr className="tw-detail">
                                  <td colSpan={7}>
                                    <div className="tw-detail-box">
                                      <div className="tw-detail-brief">{s.brief}</div>
                                      <div
                                        className="st-tags"
                                        style={{ display: "flex", gap: "6px", flexWrap: "wrap" }}
                                      >
                                        {s.tags.map((tag) => (
                                          <span
                                            key={tag}
                                            className="st-tag"
                                            onClick={() => setSearchQuery(tag)}
                                          >
                                            #{tag}
                                          </span>
                                        ))}
                                      </div>
                                      <div className="st-actions">
                                        <button
                                          className="st-btn"
                                          type="button"
                                          onClick={() => copyStory(s, `copy_${s.id}`)}
                                        >
                                          <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            strokeWidth="1.8"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                          >
                                            <rect x="9" y="9" width="13" height="13" rx="2" />
                                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                          </svg>
                                          {flashStates[`copy_${s.id}`] || "Copy text"}
                                        </button>
                                        <button
                                          className="st-btn"
                                          type="button"
                                          onClick={() => downloadWordSingle(s, `word_${s.id}`)}
                                        >
                                          <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            strokeWidth="1.8"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                          >
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                                            <polyline points="7 10 12 15 17 10" />
                                            <line x1="12" y1="15" x2="12" y2="3" />
                                          </svg>
                                          {flashStates[`word_${s.id}`] || "Word"}
                                        </button>
                                        <button
                                          className="st-btn"
                                          type="button"
                                          onClick={() => downloadXmlSingle(s, `xml_${s.id}`)}
                                        >
                                          <svg
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            strokeWidth="1.8"
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                          >
                                            <polyline points="16 18 22 12 16 6" />
                                            <polyline points="8 6 2 12 8 18" />
                                          </svg>
                                          {flashStates[`xml_${s.id}`] || "XML"}
                                        </button>
                                        {s.caps.length > 0 && (
                                          <button
                                            className="st-btn"
                                            type="button"
                                            onClick={() => downloadPhotoZip(s, `zip_${s.id}`)}
                                          >
                                            <svg
                                              viewBox="0 0 24 24"
                                              fill="none"
                                              strokeWidth="1.8"
                                              strokeLinecap="round"
                                              strokeLinejoin="round"
                                            >
                                              <rect x="3" y="3" width="18" height="18" rx="2" />
                                              <circle cx="8.5" cy="8.5" r="1.5" />
                                              <path d="m21 15-5-5L5 21" />
                                            </svg>
                                            {flashStates[`zip_${s.id}`] ||
                                              `Photos (${s.caps.length}) ZIP`}
                                          </button>
                                        )}
                                      </div>
                                    </div>
                                  </td>
                                </tr>
                              )}
                            </React.Fragment>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            ) : null}
          </div>

          {/* ============ TAB 2: MEDIA LIBRARY ============ */}
          <div id="packsPanel" hidden={tab !== "packs"}>
            <div className="ml-filters">
              <div className="src-chips" id="srcChips">
                {["all", "unb", "ap"].map((s) => (
                  <button
                    key={s}
                    type="button"
                    className={`src-chip ${mlSrc === s ? "active" : ""}`}
                    data-src={s}
                    onClick={() => {
                      setMlSrc(s);
                      setMlShown(24);
                    }}
                  >
                    {s === "all" ? "All sources" : s.toUpperCase()}
                  </button>
                ))}
              </div>

              <div className="ml-search">
                <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  strokeWidth="1.8"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  <circle cx="11" cy="11" r="8" />
                  <path d="m21 21-4.35-4.35" />
                </svg>
                <input
                  type="text"
                  id="mlSearch"
                  value={mlSearch}
                  placeholder="Search captions, photographers, locations…"
                  onChange={(e) => {
                    setMlSearch(e.target.value.toLowerCase());
                    setMlShown(24);
                  }}
                />
              </div>

              <button
                className="ml-filter-toggle"
                id="mlFilterToggle"
                type="button"
                aria-expanded={mlFilterOpen}
                onClick={() => setMlFilterOpen((o) => !o)}
              >
                <svg viewBox="0 0 24 24" fill="none" strokeWidth="2" strokeLinecap="round">
                  <line x1="4" y1="21" x2="4" y2="14" />
                  <line x1="4" y1="10" x2="4" y2="3" />
                  <line x1="12" y1="21" x2="12" y2="12" />
                  <line x1="12" y1="8" x2="12" y2="3" />
                  <line x1="20" y1="21" x2="20" y2="16" />
                  <line x1="20" y1="12" x2="20" y2="3" />
                  <line x1="1" y1="14" x2="7" y2="14" />
                  <line x1="9" y1="8" x2="15" y2="8" />
                  <line x1="17" y1="16" x2="23" y2="16" />
                </svg>
                Filters
                <span
                  className="ml-fbadge"
                  id="mlFBadge"
                  hidden={
                    (mlCat === "All" ? 0 : 1) +
                      (mlEnt === "all" ? 0 : 1) +
                      (mlDate === "all" ? 0 : 1) ===
                    0
                  }
                >
                  {(mlCat === "All" ? 0 : 1) +
                    (mlEnt === "all" ? 0 : 1) +
                    (mlDate === "all" ? 0 : 1)}
                </span>
              </button>

              <div className="density-btns">
                <button
                  type="button"
                  className={`density-btn ${mlDensity === "cozy" ? "active" : ""}`}
                  title="Comfortable view"
                  onClick={() => setMlDensity("cozy")}
                >
                  <svg viewBox="0 0 24 24" fill="none" strokeWidth="2" strokeLinecap="round">
                    <rect x="3" y="3" width="8" height="8" rx="1.5" />
                    <rect x="13" y="3" width="8" height="8" rx="1.5" />
                    <rect x="3" y="13" width="8" height="8" rx="1.5" />
                    <rect x="13" y="13" width="8" height="8" rx="1.5" />
                  </svg>
                </button>
                <button
                  type="button"
                  className={`density-btn ${mlDensity === "compact" ? "active" : ""}`}
                  title="Compact view"
                  onClick={() => setMlDensity("compact")}
                >
                  <svg viewBox="0 0 24 24" fill="none" strokeWidth="2" strokeLinecap="round">
                    <rect x="3" y="3" width="4" height="4" rx="1" />
                    <rect x="10" y="3" width="4" height="4" rx="1" />
                    <rect x="17" y="3" width="4" height="4" rx="1" />
                    <rect x="3" y="10" width="4" height="4" rx="1" />
                    <rect x="10" y="10" width="4" height="4" rx="1" />
                    <rect x="17" y="10" width="4" height="4" rx="1" />
                    <rect x="3" y="17" width="4" height="4" rx="1" />
                    <rect x="10" y="17" width="4" height="4" rx="1" />
                    <rect x="17" y="17" width="4" height="4" rx="1" />
                  </svg>
                </button>
              </div>
            </div>

            {/* Expandable Filters Row 2 */}
            <div className="ml-filters2" id="mlFiltersRow" hidden={!mlFilterOpen}>
              <select
                className="ml-sel"
                id="mlCat"
                value={mlCat}
                onChange={(e) => {
                  setMlCat(e.target.value);
                  setMlShown(24);
                }}
              >
                <option value="All">All categories</option>
                <option>Bangladesh</option>
                <option>Politics</option>
                <option>World</option>
                <option>Sports</option>
                <option>Business</option>
                <option>Environment</option>
              </select>

              <select
                className="ml-sel"
                id="mlEnt"
                value={mlEnt}
                onChange={(e) => {
                  setMlEnt(e.target.value);
                  setMlShown(24);
                }}
              >
                <option value="all">All access levels</option>
                <option value="dl">✓ Downloadable by me</option>
                <option value="ex">★ Exclusive to me</option>
                <option value="addon">AP add-on</option>
                <option value="lock">🔒 Locked</option>
              </select>

              <select
                className="ml-sel"
                id="mlDate"
                value={mlDate}
                onChange={(e) => {
                  setMlDate(e.target.value);
                  setMlShown(24);
                }}
              >
                <option value="all">Any time</option>
                <option value="1">Last 48 hours</option>
                <option value="7">Last 7 days</option>
                <option value="20">Last 3 weeks</option>
              </select>

              <select
                className="ml-sel"
                id="mlSort"
                value={mlSort}
                onChange={(e) => setMlSort(e.target.value as any)}
              >
                <option value="new">Newest first</option>
                <option value="old">Oldest first</option>
              </select>

              {(mlCat !== "All" || mlEnt !== "all" || mlDate !== "all") && (
                <button
                  className="ml-reset"
                  id="mlResetBtn"
                  type="button"
                  onClick={() => {
                    setMlCat("All");
                    setMlEnt("all");
                    setMlDate("all");
                    setMlShown(24);
                  }}
                >
                  Reset filters
                </button>
              )}
            </div>

            {/* Counts */}
            <div className="ml-count" id="mlCount">
              {filteredMediaList.length > 0 && (
                <>
                  Showing <b>{Math.min(mlShown, filteredMediaList.length)}</b> of{" "}
                  <b>{filteredMediaList.length.toLocaleString()}</b> matching · 2,847 assets in your
                  library
                </>
              )}
            </div>

            {/* Media Basket Bar */}
            <div className={`bulk-bar ${selectedMedia.size > 0 ? "show" : ""}`} id="basketBar">
              <span className="bulk-count" id="basketCount">
                {selectedMedia.size} selected
              </span>
              <button
                className="bulk-btn accent"
                id="basketZip"
                type="button"
                onClick={handleBasketZip}
              >
                <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  strokeWidth="1.8"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                  <polyline points="7 10 12 15 17 10" />
                  <line x1="12" y1="15" x2="12" y2="3" />
                </svg>
                {flashStates["basketZip"] || "Download ZIP + license"}
              </button>
              <button
                className="bulk-clear"
                id="basketClear"
                type="button"
                onClick={() => setSelectedMedia(new Set())}
              >
                Clear selection
              </button>
            </div>

            {/* Media Cards Grid */}
            <div className={`ml-grid ${mlDensity === "compact" ? "compact" : ""}`} id="mediaGrid">
              {filteredMediaList.length === 0 ? (
                <div className="feed-empty" style={{ gridColumn: "1/-1" }}>
                  No media matches your filters — try widening the date range or clearing the
                  search.
                </div>
              ) : (
                filteredMediaList.slice(0, mlShown).map((m) => {
                  const isSelected = selectedMedia.has(m.id);
                  const entText =
                    m.ent === "inc"
                      ? "✓ Included"
                      : m.ent === "addon"
                      ? "AP add-on"
                      : m.ent === "lock"
                      ? "🔒 AP World pack"
                      : m.ent === "ex"
                      ? "★ Exclusive to you"
                      : "Embargoed";

                  return (
                    <div
                      key={m.id}
                      className={`ml-card${isSelected ? " selected" : ""}`}
                      onClick={() => setActiveAsset(m)}
                    >
                      <div className={`ml-thumb ${m.grad}`}>
                        <span className={`ml-src ${m.src === "ap" ? "ap" : ""}`}>
                          {m.src.toUpperCase()}
                        </span>
                        <input
                          type="checkbox"
                          className="ml-check"
                          checked={isSelected}
                          onClick={(e) => e.stopPropagation()}
                          onChange={(e) => {
                            setSelectedMedia((prev) => {
                              const next = new Set(prev);
                              e.target.checked ? next.add(m.id) : next.delete(m.id);
                              return next;
                            });
                          }}
                        />
                        {m.type === "video" ? (
                          <>
                            <svg
                              className="big"
                              viewBox="0 0 24 24"
                              fill="rgba(255,255,255,0.9)"
                              stroke="none"
                            >
                              <polygon points="7 4 20 12 7 20 7 4" />
                            </svg>
                            <span className="ml-dur">{m.dim.split("· ")[1] || "01:00"}</span>
                          </>
                        ) : (
                          <svg
                            className="big"
                            viewBox="0 0 24 24"
                            fill="none"
                            strokeWidth="1.4"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                          >
                            <rect x="3" y="3" width="18" height="18" rx="2" />
                            <circle cx="8.5" cy="8.5" r="1.5" />
                            <polyline points="21 15 16 10 5 21" />
                          </svg>
                        )}
                        {m.ent === "emb" && (
                          <div className="ml-embargo">
                            <svg
                              viewBox="0 0 24 24"
                              fill="none"
                              strokeWidth="1.8"
                              strokeLinecap="round"
                              strokeLinejoin="round"
                            >
                              <rect x="3" y="11" width="18" height="11" rx="2" />
                              <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                            </svg>
                            <span>Embargoed</span>
                            <span className="t">Available in 2h 14m</span>
                          </div>
                        )}
                      </div>

                      <div className="ml-body">
                        <a className="ml-cap">{m.cap}</a>
                        <div className="ml-meta">
                          {m.by} · {m.loc} · {m.date}
                        </div>
                        <span className={`ml-ent ${m.ent}`}>{entText}</span>
                      </div>
                    </div>
                  );
                })
              )}
            </div>

            <div className="ml-load" id="mlSentinel" ref={sentinelRef}>
              {mlShown < filteredMediaList.length ? (
                "Loading more as you scroll…"
              ) : filteredMediaList.length > 24 ? (
                "That's everything matching — refine filters to dig into the rest of the archive"
              ) : null}
            </div>
          </div>

          {/* ============ TAB 3: UNB PHOTOS SHOWCASE ============ */}
          <div id="photosPanel" hidden={tab !== "photos"}>
            {/* Hero: Photo of the Day */}
            <div className="ph-hero">
              <div
                className="ph-hero-img g6"
                id="phHeroImg"
                title="Click to view full size"
                onClick={() =>
                  setLightbox({
                    open: true,
                    items: [
                      {
                        type: "photo",
                        grad: "g6",
                        cap: "Fishermen unload the morning hilsa catch at Chandpur — the season peaks as river currents turn · Photo: Mahmud Hossain / UNB",
                      },
                    ],
                    index: 0,
                  })
                }
              >
                <span className="ph-hero-tag">★ Photo of the day</span>
                <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  strokeWidth="1.2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  <rect x="3" y="3" width="18" height="18" rx="2" />
                  <circle cx="8.5" cy="8.5" r="1.5" />
                  <path d="m21 15-5-5L5 21" />
                </svg>
              </div>

              <div className="ph-hero-body">
                <div className="ph-hero-cap">
                  Fishermen unload the morning hilsa catch at Chandpur — the season peaks as river
                  currents turn
                </div>
                <div className="ph-hero-meta">
                  Photo: Mahmud Hossain / UNB · Chandpur · Today, 7:55 AM · 3000 × 2000
                </div>
                <div className="ph-attach" onClick={() => goStorySearch("hilsa")}>
                  📰 Attached to: “Hilsa glut at Chandpur landing station” — grab story + photos
                  together
                </div>
                <div className="res-seg" id="phHeroRes">
                  <button
                    type="button"
                    className={`res-opt ${heroRes === "web" ? "active" : ""}`}
                    onClick={() => setHeroRes("web")}
                  >
                    Web · 1200 px
                  </button>
                  <button
                    type="button"
                    className={`res-opt ${heroRes === "print" ? "active" : ""}`}
                    onClick={() => setHeroRes("print")}
                  >
                    Print · original
                  </button>
                </div>
                <div className="quota-note" id="phHeroQuota">
                  ℹ Will use 1 of <b>{mediaQuota}</b> remaining media downloads this month
                </div>
                <button
                  className="mbtn navy am-dl"
                  id="phHeroDl"
                  type="button"
                  onClick={async () => {
                    try {
                      await downloadMedia(5, heroRes);
                      flash("heroDl", `✓ Downloaded (${heroRes === "web" ? "1200 px" : "original"})`);
                      setMediaQuota((q) => Math.max(0, q - 1));
                    } catch (e) {
                      flash("heroDl", "Failed");
                    }
                  }}
                >
                  {flashStates["heroDl"] || "Download photo"}
                </button>
                <div className="ph-lic">
                  ✓ UNB license — unlimited archival, print + web included
                </div>
              </div>
            </div>

            {/* Photo Stories */}
            <div className="ph-sec">
              <div className="ph-sec-title">
                Photo stories{" "}
                <span className="sub">
                  multi-frame narratives — browse, download all, or embed on your site
                </span>
              </div>
              <div className="gal-row" id="galRow">
                {GALLERIES.map((g, gi) => (
                  <div key={g.id} className="gal-card">
                    <div
                      className={`gal-thumb ${g.grad}`}
                      title="Browse gallery"
                      onClick={() =>
                        setLightbox({
                          open: true,
                          items: g.caps.map((c, idx) => ({
                            type: "photo",
                            grad: GRADS[(gi * 2 + idx) % 8],
                            cap: c,
                          })),
                          index: 0,
                        })
                      }
                    >
                      <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        strokeWidth="1.4"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                      >
                        <rect x="3" y="3" width="18" height="18" rx="2" />
                        <circle cx="8.5" cy="8.5" r="1.5" />
                        <path d="m21 15-5-5L5 21" />
                      </svg>
                      <span className="n">{g.n} frames</span>
                    </div>
                    <div className="gal-body">
                      <div className="gal-title">{g.title}</div>
                      <span className="gal-story" onClick={() => goStorySearch(g.story)}>
                        📰 {g.storyTitle}
                      </span>
                      <div className="gal-acts">
                        <button
                          className="gal-btn"
                          type="button"
                          data-gal-browse={gi}
                          onClick={() =>
                            setLightbox({
                              open: true,
                              items: g.caps.map((c, idx) => ({
                                type: "photo",
                                grad: GRADS[(gi * 2 + idx) % 8],
                                cap: c,
                              })),
                              index: 0,
                            })
                          }
                        >
                          <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            strokeWidth="1.8"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                          >
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                            <circle cx="12" cy="12" r="3" />
                          </svg>
                          Browse
                        </button>
                        <button
                          className="gal-btn"
                          type="button"
                          data-gal-zip={gi}
                          onClick={async () => {
                            flash(`gal_${gi}`, "Downloading…");
                            try {
                              for (let idx = 0; idx < g.caps.length; idx++) {
                                await downloadMedia(gi * 10 + idx, "original");
                              }
                              flash(`gal_${gi}`, "Saved");
                            } catch (e) {
                              flash(`gal_${gi}`, "Failed");
                            }
                          }}
                        >
                          <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            strokeWidth="1.8"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                          >
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
                            <polyline points="7 10 12 15 17 10" />
                            <line x1="12" y1="15" x2="12" y2="3" />
                          </svg>
                          {flashStates[`gal_${gi}`] || "ZIP"}
                        </button>
                        <button
                          className="gal-btn"
                          type="button"
                          data-gal-embed={gi}
                          onClick={() => {
                            const code = `<iframe src="https://photos.unbnews.org/embed/${g.id}" width="100%" height="520" frameborder="0" title="UNB Photos — ${g.title}"></iframe>`;
                            navigator.clipboard?.writeText(code).catch(() => {});
                            flash(`embed_${gi}`, "Copied");
                          }}
                        >
                          <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            strokeWidth="1.8"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                          >
                            <polyline points="16 18 22 12 16 6" />
                            <polyline points="8 6 2 12 8 18" />
                          </svg>
                          {flashStates[`embed_${gi}`] || "Embed"}
                        </button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* Collections */}
            <div className="ph-sec">
              <div className="ph-sec-title">
                Collections{" "}
                <span className="sub">
                  curated by the photo desk · 🔔 = notify me on new additions
                </span>
              </div>
              <div className="coll-row" id="collRow">
                {COLLECTIONS.map((c) => {
                  const isBellOn = collectionAlerts.has(c.id);
                  return (
                    <div
                      key={c.id}
                      className={`coll-card ${c.grad}`}
                      onClick={() => goLibrarySearch(c.kw)}
                    >
                      <button
                        className={`coll-bell ${isBellOn ? "on" : ""}`}
                        type="button"
                        title={
                          isBellOn
                            ? "Notifications on — you will be emailed"
                            : "Notify me on new additions"
                        }
                        onClick={(e) => {
                          e.stopPropagation();
                          setCollectionAlerts((prev) => {
                            const next = new Set(prev);
                            next.has(c.id) ? next.delete(c.id) : next.add(c.id);
                            return next;
                          });
                        }}
                      >
                        <svg
                          viewBox="0 0 24 24"
                          fill="none"
                          strokeWidth="1.8"
                          strokeLinecap="round"
                          strokeLinejoin="round"
                        >
                          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" />
                          <path d="M13.73 21a2 2 0 0 1-3.46 0" />
                        </svg>
                      </button>
                      <div className="coll-name">{c.name}</div>
                      <div className="coll-n">{c.n} photos</div>
                    </div>
                  );
                })}
              </div>
            </div>

            {/* Trending */}
            <div className="ph-sec">
              <div className="ph-sec-title">
                Most downloaded this week{" "}
                <span className="sub">what other newsrooms are using</span>
              </div>
              <div className="trend-list" id="trendList">
                {TRENDING.map((t, i) => (
                  <div
                    key={i}
                    className="trend-item"
                    onClick={() =>
                      setLightbox({
                        open: true,
                        items: [
                          {
                            type: "photo",
                            grad: GRADS[i % 8],
                            cap: `${t.cap} · Photo: UNB`,
                          },
                        ],
                        index: 0,
                      })
                    }
                  >
                    <span className="trend-rank">{i + 1}</span>
                    <span className="trend-cap">{t.cap}</span>
                    <span className="trend-dl">↓ {t.dl} downloads</span>
                  </div>
                ))}
              </div>
            </div>

            {/* Latest Photo Stream */}
            <div className="ph-sec">
              <div className="ph-sec-title">
                Latest from UNB photographers{" "}
                <span className="sub">every photo links back to its story</span>
              </div>
              <div className="ph-stream-top">
                <div className="ml-search">
                  <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    strokeWidth="1.8"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  >
                    <circle cx="11" cy="11" r="8" />
                    <path d="m21 21-4.35-4.35" />
                  </svg>
                  <input
                    type="text"
                    id="phSearch"
                    value={phSearch}
                    placeholder="Search captions, districts, photographers…"
                    onChange={(e) => setPhSearch(e.target.value.toLowerCase())}
                  />
                </div>
              </div>

              <div className="ml-grid" id="phStream">
                {PHOTO_STREAM.filter(
                  (p) =>
                    !phSearch ||
                    (p.cap + " " + p.by + " " + p.loc + " " + p.kw)
                      .toLowerCase()
                      .includes(phSearch)
                ).map((p, idx) => (
                  <div key={p.id} className="ml-card">
                    <div
                      className={`ml-thumb ${p.grad}`}
                      style={{ cursor: "zoom-in" }}
                      onClick={() =>
                        setLightbox({
                          open: true,
                          items: [
                            {
                              type: "photo",
                              grad: p.grad,
                              cap: `${p.cap} · Photo: ${p.by} / UNB`,
                            },
                          ],
                          index: 0,
                        })
                      }
                    >
                      <span className="ml-src">UNB</span>
                      <svg
                        className="big"
                        viewBox="0 0 24 24"
                        fill="none"
                        strokeWidth="1.4"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                      >
                        <rect x="3" y="3" width="18" height="18" rx="2" />
                        <circle cx="8.5" cy="8.5" r="1.5" />
                        <polyline points="21 15 16 10 5 21" />
                      </svg>
                    </div>

                    <div className="ml-body">
                      <a
                        className="ml-cap"
                        onClick={() =>
                          setLightbox({
                            open: true,
                            items: [
                              {
                                type: "photo",
                                grad: p.grad,
                                cap: `${p.cap} · Photo: ${p.by} / UNB`,
                              },
                            ],
                            index: 0,
                          })
                        }
                      >
                        {p.cap}
                      </a>
                      <div className="ml-meta">
                        {p.by} · {p.loc} · {p.date}
                      </div>
                      {p.story && (
                        <span
                          className="ml-story"
                          title="Open the attached story"
                          onClick={(e) => {
                            e.stopPropagation();
                            if (p.story) goStorySearch(p.story);
                          }}
                        >
                          📰 {STORY_TITLES[p.story]}
                        </span>
                      )}
                      <span className="ml-ent inc">✓ Included</span>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </main>
      </div>

      {/* ============ ASSET DETAIL MODAL ============ */}
      {activeAsset && (
        <div
          className="am-overlay open"
          id="amOverlay"
          onClick={(e) => {
            if ((e.target as HTMLElement).id === "amOverlay") {
              setActiveAsset(null);
            }
          }}
        >
          <div className="am-modal">
            <div className="am-grid">
              <div className={`am-img ${activeAsset.grad}`} id="amImg">
                {activeAsset.type === "video" ? (
                  <svg
                    className="big"
                    viewBox="0 0 24 24"
                    fill="rgba(255,255,255,0.9)"
                    stroke="none"
                  >
                    <polygon points="7 4 20 12 7 20 7 4" />
                  </svg>
                ) : (
                  <svg
                    className="big"
                    viewBox="0 0 24 24"
                    fill="none"
                    strokeWidth="1.4"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  >
                    <rect x="3" y="3" width="18" height="18" rx="2" />
                    <circle cx="8.5" cy="8.5" r="1.5" />
                    <polyline points="21 15 16 10 5 21" />
                  </svg>
                )}
              </div>

              <div className="am-info">
                <div
                  style={{
                    display: "flex",
                    justifyContent: "space-between",
                    alignItems: "flex-start",
                    gap: "12px",
                  }}
                >
                  <div className="am-title" id="amCap">
                    {activeAsset.cap}
                  </div>
                  <button
                    className="am-close"
                    id="amClose"
                    type="button"
                    title="Close (Esc)"
                    onClick={() => setActiveAsset(null)}
                  >
                    ✕
                  </button>
                </div>

                <div className="am-credit-row">
                  <span className="am-credit" id="amCredit">
                    {activeAsset.credit}
                  </span>
                  <button
                    className="credit-copy"
                    id="amCopyCredit"
                    type="button"
                    onClick={() => {
                      navigator.clipboard
                        ?.writeText(`${activeAsset.credit} — ${activeAsset.by}`)
                        .catch(() => {});
                      flash("copyCredit", "✓ Copied");
                    }}
                  >
                    {flashStates["copyCredit"] || "Copy credit line"}
                  </button>
                </div>

                <div className="am-kv" id="amKv">
                  <span className="k">Photographer</span>
                  <span className="v">{activeAsset.by}</span>
                  <span className="k">Source</span>
                  <span className="v">
                    {activeAsset.src === "ap" ? "Associated Press (via UNB)" : "UNB staff"}
                  </span>
                  <span className="k">Date</span>
                  <span className="v">{activeAsset.date}</span>
                  <span className="k">Location</span>
                  <span className="v">{activeAsset.loc}</span>
                  <span className="k">Size</span>
                  <span className="v">{activeAsset.dim}</span>
                  <span className="k">Category</span>
                  <span className="v">{activeAsset.cat}</span>
                </div>

                <div className="am-terms" id="amTerms">
                  {activeAsset.src === "ap" ? (
                    <b>
                      AP license: Editorial use only · Credit line mandatory · Single publication ·
                      No archival beyond 30 days · Not for advertising
                    </b>
                  ) : (
                    <b>
                      UNB license: Editorial use · Credit line required · Unlimited archival while
                      subscription is active
                    </b>
                  )}
                </div>

                <div className="am-dl-box" id="amDlBox">
                  {activeAsset.ent === "lock" ? (
                    <div className="am-lockbox">
                      🔒 <b>Part of the AP World photo pack</b> — not in your current subscription.
                      Ask UNB to enable it, or buy it as an add-on.
                      <button
                        className="mbtn amber"
                        id="amUpgrade"
                        type="button"
                        onClick={(e) => {
                          (e.target as HTMLElement).textContent = "✓ Request sent to UNB sales";
                        }}
                      >
                        Request AP World pack
                      </button>
                    </div>
                  ) : activeAsset.ent === "emb" ? (
                    <div className="am-lockbox blue">
                      ⏱ <b>Embargoed by AP</b> — available in <b>2h 14m</b> (today 6:00 PM Dhaka
                      time). It will appear in your library automatically.
                      <button
                        className="mbtn navy"
                        id="amNotify"
                        type="button"
                        onClick={(e) => {
                          (e.target as HTMLElement).textContent = "✓ You will be emailed";
                        }}
                      >
                        Notify me when available
                      </button>
                    </div>
                  ) : (
                    <>
                      {activeAsset.ent === "ex" && (
                        <div className="quota-note" style={{ color: "#b7791f" }}>
                          <b>★ Exclusive to Daily Star</b> — exclusive window ends tomorrow 6:00 PM,
                          then it opens to other clients.
                        </div>
                      )}
                      <div className="res-seg">
                        <button
                          type="button"
                          className={`res-opt ${assetRes === "web" ? "active" : ""}`}
                          onClick={() => setAssetRes("web")}
                        >
                          Web · 1200 px
                        </button>
                        <button
                          type="button"
                          className={`res-opt ${assetRes === "print" ? "active" : ""}`}
                          onClick={() => setAssetRes("print")}
                        >
                          Print · original
                        </button>
                      </div>

                      <div className="quota-note">
                        {activeAsset.ent === "addon"
                          ? `ℹ Will use 1 of ${apCredits} remaining AP add-on credits this month`
                          : `ℹ Will use 1 of ${mediaQuota} remaining media downloads this month`}
                      </div>

                      <button
                        className="mbtn navy am-dl"
                        id="amDownload"
                        type="button"
                        onClick={async () => {
                          try {
                            await downloadMedia(activeAsset.id, assetRes);
                            flash(
                              "amDl",
                              `✓ Downloaded (${assetRes === "web" ? "1200 px" : "original"})`
                            );
                            if (activeAsset.ent === "addon") {
                              setApCredits((c) => Math.max(0, c - 1));
                            } else {
                              setMediaQuota((q) => Math.max(0, q - 1));
                            }
                          } catch (e) {
                            flash("amDl", "Failed");
                          }
                        }}
                      >
                        {flashStates["amDl"] || `Download ${activeAsset.type}`}
                      </button>
                    </>
                  )}
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ============ PHOTO LIGHTBOX ============ */}
      {lightbox.open && lightbox.items.length > 0 && (
        <div
          className="lb open"
          id="lightbox"
          onClick={(e) => {
            if ((e.target as HTMLElement).id === "lightbox") {
              setLightbox((l) => ({ ...l, open: false }));
            }
          }}
        >
          <button
            className="lb-close"
            id="lbClose"
            type="button"
            title="Close (Esc)"
            onClick={() => setLightbox((l) => ({ ...l, open: false }))}
          >
            ✕
          </button>
          <button
            className="lb-nav prev"
            id="lbPrev"
            type="button"
            title="Previous"
            onClick={() =>
              setLightbox((l) => ({
                ...l,
                index: (l.index - 1 + l.items.length) % l.items.length,
              }))
            }
          >
            ‹
          </button>
          <div className="lb-stage">
            <div
              className={`lb-img ${lightbox.items[lightbox.index].grad}`}
              id="lbImg"
            >
              {lightbox.items[lightbox.index].type === "video" ? (
                <svg
                  viewBox="0 0 24 24"
                  fill="rgba(255,255,255,0.9)"
                  stroke="none"
                >
                  <polygon points="7 4 20 12 7 20 7 4" />
                </svg>
              ) : (
                <svg
                  viewBox="0 0 24 24"
                  fill="none"
                  strokeWidth="1.2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  <rect x="3" y="3" width="18" height="18" rx="2" />
                  <circle cx="8.5" cy="8.5" r="1.5" />
                  <path d="m21 15-5-5L5 21" />
                </svg>
              )}
            </div>
            <div className="lb-cap" id="lbCap">
              {lightbox.items[lightbox.index].cap}
            </div>
            <div className="lb-foot">
              <span className="lb-count" id="lbCount">
                {lightbox.index + 1} / {lightbox.items.length}
              </span>
              <button
                className="lb-dl"
                id="lbDl"
                type="button"
                onClick={async () => {
                  const it = lightbox.items[lightbox.index];
                  try {
                    await downloadMedia(it.i || lightbox.index, "original");
                    flash("lbDl", "Saved");
                  } catch (e) {
                    flash("lbDl", "Failed");
                  }
                }}
              >
                {flashStates["lbDl"] ||
                  (lightbox.items[lightbox.index].type === "video"
                    ? "Download video"
                    : "Download photo")}
              </button>
            </div>
          </div>
          <button
            className="lb-nav next"
            id="lbNext"
            type="button"
            title="Next"
            onClick={() =>
              setLightbox((l) => ({
                ...l,
                index: (l.index + 1) % l.items.length,
              }))
            }
          >
            ›
          </button>
        </div>
      )}

      <LoginModal
        isOpen={showLogin}
        onClose={() => setShowLogin(false)}
        onSuccess={(key, info) => {
          setApiKey(key);
          setClientInfo(info);
          setShowLogin(false);
        }}
      />
    </>
  );
}
