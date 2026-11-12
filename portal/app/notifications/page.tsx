"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { portalFetch } from "../../lib/api";

interface NotificationItem {
  id: string;
  event: string;
  title: string;
  message: string;
  story_id: number | null;
  public_id: string | null;
  category: string | null;
  urgency: string;
  read_at: string | null;
  created_at: string;
}

interface NotificationResponse {
  data: NotificationItem[];
  unread_count: number;
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export default function NotificationsPage() {
  const [notifications, setNotifications] = useState<NotificationItem[]>([]);
  const [unreadCount, setUnreadCount] = useState<number>(0);
  const [loading, setLoading] = useState<boolean>(true);
  const [filter, setFilter] = useState<"all" | "unread">("all");
  const [actionMsg, setActionMsg] = useState<string | null>(null);

  const fetchNotifications = async (currentFilter: "all" | "unread") => {
    try {
      const res = await portalFetch<NotificationResponse>(
        `/api/v1/portal/notifications?filter=${currentFilter}`
      );
      setNotifications(res.data || []);
      setUnreadCount(res.unread_count || 0);
    } catch (err) {
      console.error("Failed to load notifications", err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    setLoading(true);
    fetchNotifications(filter);
  }, [filter]);

  const showToast = (msg: string) => {
    setActionMsg(msg);
    setTimeout(() => setActionMsg(null), 3000);
  };

  const handleMarkAsRead = async (id: string, e?: React.MouseEvent) => {
    if (e) e.stopPropagation();
    try {
      const res = await portalFetch<{ unread_count: number }>(
        `/api/v1/portal/notifications/${id}/read`,
        { method: "PATCH" }
      );
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n))
      );
      setUnreadCount(res.unread_count);
      showToast("Notification marked as read");
    } catch {
      showToast("Failed to mark notification as read");
    }
  };

  const handleMarkAllRead = async () => {
    try {
      await portalFetch("/api/v1/portal/notifications/mark-all-read", {
        method: "POST",
      });
      setNotifications((prev) =>
        prev.map((n) => ({ ...n, read_at: new Date().toISOString() }))
      );
      setUnreadCount(0);
      showToast("All notifications marked as read");
    } catch {
      showToast("Failed to mark all as read");
    }
  };

  const handleDelete = async (id: string, e?: React.MouseEvent) => {
    if (e) e.stopPropagation();
    try {
      const res = await portalFetch<{ unread_count: number }>(
        `/api/v1/portal/notifications/${id}`,
        { method: "DELETE" }
      );
      setNotifications((prev) => prev.filter((n) => n.id !== id));
      setUnreadCount(res.unread_count);
      showToast("Notification deleted");
    } catch {
      showToast("Failed to delete notification");
    }
  };

  const handleClearAll = async () => {
    if (!confirm("Are you sure you want to clear all notifications?")) return;
    try {
      await portalFetch("/api/v1/portal/notifications", {
        method: "DELETE",
      });
      setNotifications([]);
      setUnreadCount(0);
      showToast("All notifications cleared");
    } catch {
      showToast("Failed to clear notifications");
    }
  };

  const formatTime = (isoString: string) => {
    try {
      const date = new Date(isoString);
      const now = new Date();
      const diffSec = Math.floor((now.getTime() - date.getTime()) / 1000);

      if (diffSec < 60) return "Just now";
      if (diffSec < 3600) return `${Math.floor(diffSec / 60)}m ago`;
      if (diffSec < 86400) return `${Math.floor(diffSec / 3600)}h ago`;

      return (
        date.toLocaleDateString("en-US", { month: "short", day: "numeric" }) +
        " · " +
        date.toLocaleTimeString("en-US", { hour: "numeric", minute: "2-digit" })
      );
    } catch {
      return isoString;
    }
  };

  return (
    <div className="min-h-screen bg-[#faf9f6] text-[#16204a]">
      {/* Header */}
      <header className="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-[#eceae5] px-6 lg:px-10 py-3 flex items-center gap-4">
        <Link href="/" className="flex items-center gap-2">
          <div className="w-8 h-8 rounded-lg bg-[#e5484d] text-white font-bold flex items-center justify-center font-serif">
            U
          </div>
          <div>
            <div className="font-serif font-bold text-base leading-none">UNB Wire</div>
            <div className="text-[10px] text-[#b0b2bc] uppercase tracking-wider font-semibold">
              Client Portal
            </div>
          </div>
        </Link>
        <div className="ml-auto flex items-center gap-4 text-xs font-semibold">
          <Link href="/account" className="text-[#4b4e5c] hover:text-[#16204a]">
            My Account
          </Link>
          <Link href="/" className="text-[#4b4e5c] hover:text-[#16204a]">
            ← Back to wire feed
          </Link>
        </div>
      </header>

      {/* Main Container */}
      <main className="max-w-[760px] mx-auto px-6 py-8">
        {/* Title and Top Actions */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
          <div className="flex items-center gap-3">
            <h1 className="font-serif text-2xl font-bold">Notifications</h1>
            {unreadCount > 0 && (
              <span className="px-2.5 py-0.5 rounded-full text-xs font-bold bg-[#e5484d] text-white">
                {unreadCount} unread
              </span>
            )}
          </div>

          <div className="flex items-center gap-2">
            {unreadCount > 0 && (
              <button
                type="button"
                onClick={handleMarkAllRead}
                className="px-3 py-1.5 rounded-lg border border-[#d9d7cf] bg-white text-xs font-semibold text-[#16204a] hover:bg-[#f4f2ee] transition-colors"
              >
                Mark all read
              </button>
            )}
            {notifications.length > 0 && (
              <button
                type="button"
                onClick={handleClearAll}
                className="px-3 py-1.5 rounded-lg border border-[#d9d7cf] bg-white text-xs font-semibold text-[#7c7f8c] hover:text-[#e5484d] hover:border-[#e5484d] transition-colors"
              >
                Clear all
              </button>
            )}
          </div>
        </div>

        {/* Filter Tabs */}
        <div className="flex items-center gap-2 border-b border-[#eceae5] pb-3 mb-6">
          <button
            type="button"
            onClick={() => setFilter("all")}
            className={`px-3 py-1 rounded-full text-xs font-semibold transition-colors ${
              filter === "all"
                ? "bg-[#16204a] text-white"
                : "text-[#7c7f8c] hover:text-[#16204a] hover:bg-white"
            }`}
          >
            All Notifications
          </button>
          <button
            type="button"
            onClick={() => setFilter("unread")}
            className={`px-3 py-1 rounded-full text-xs font-semibold transition-colors flex items-center gap-1.5 ${
              filter === "unread"
                ? "bg-[#16204a] text-white"
                : "text-[#7c7f8c] hover:text-[#16204a] hover:bg-white"
            }`}
          >
            Unread Only
            {unreadCount > 0 && (
              <span
                className={`w-2 h-2 rounded-full ${
                  filter === "unread" ? "bg-[#e5484d]" : "bg-[#e5484d]"
                }`}
              />
            )}
          </button>
        </div>

        {/* Toast / Action Message */}
        {actionMsg && (
          <div className="mb-4 px-4 py-2 bg-[#16204a] text-white text-xs font-semibold rounded-lg shadow transition-all">
            {actionMsg}
          </div>
        )}

        {/* Notification List */}
        {loading ? (
          <div className="py-16 text-center text-sm text-[#7c7f8c]">
            Loading notifications…
          </div>
        ) : notifications.length === 0 ? (
          <div className="py-16 text-center bg-white rounded-xl border border-[#eceae5] p-8">
            <div className="w-12 h-12 mx-auto mb-3 rounded-full bg-[#f4f2ee] flex items-center justify-center text-[#7c7f8c] text-xl">
              🔔
            </div>
            <h3 className="font-semibold text-sm text-[#16204a] mb-1">
              No notifications
            </h3>
            <p className="text-xs text-[#7c7f8c]">
              {filter === "unread"
                ? "You have caught up with all your notifications."
                : "Breaking news, story updates, and advisory notices will appear here."}
            </p>
          </div>
        ) : (
          <div className="space-y-3">
            {notifications.map((item) => {
              const isUnread = !item.read_at;
              const isBreaking =
                item.urgency === "breaking" || item.event === "story.breaking";
              const isKilled = item.event === "story.killed";

              return (
                <div
                  key={item.id}
                  className={`group relative bg-white rounded-xl border transition-all p-4 ${
                    isUnread
                      ? "border-[#16204a]/30 shadow-sm bg-gradient-to-r from-[#fffbfb] to-white"
                      : "border-[#eceae5] hover:border-[#d9d7cf]"
                  }`}
                >
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex items-start gap-3 flex-1 min-w-0">
                      {/* Status indicator dot */}
                      <div className="pt-1 flex-shrink-0">
                        {isUnread ? (
                          <span className="block w-2.5 h-2.5 rounded-full bg-[#e5484d]" />
                        ) : (
                          <span className="block w-2.5 h-2.5 rounded-full bg-transparent" />
                        )}
                      </div>

                      {/* Content */}
                      <div className="flex-1 min-w-0">
                        {/* Badges & Meta */}
                        <div className="flex flex-wrap items-center gap-2 mb-1">
                          {isBreaking && (
                            <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-[#e5484d] text-white">
                              🚨 Breaking
                            </span>
                          )}
                          {isKilled && (
                            <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-[#d9383a] text-white">
                              ⚠️ Story Killed
                            </span>
                          )}
                          {item.event === "story.updated" && (
                            <span className="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-[#1070ca] text-white">
                              🔄 Updated
                            </span>
                          )}
                          {item.category && (
                            <span className="text-[11px] font-semibold text-[#7c7f8c]">
                              {item.category}
                            </span>
                          )}
                          <span className="text-[11px] text-[#b0b2bc]">·</span>
                          <span className="text-[11px] text-[#7c7f8c]">
                            {formatTime(item.created_at)}
                          </span>
                        </div>

                        {/* Title */}
                        <h4
                          className={`text-sm mb-1 leading-snug ${
                            isUnread ? "font-bold text-[#16204a]" : "font-medium text-[#2d3142]"
                          }`}
                        >
                          {item.title}
                        </h4>

                        {/* Summary / Message */}
                        {item.message && (
                          <p className="text-xs text-[#7c7f8c] line-clamp-2 mb-3">
                            {item.message}
                          </p>
                        )}

                        {/* Story link */}
                        {item.public_id && (
                          <div className="flex items-center gap-2">
                            <Link
                              href={`/story/${item.public_id}`}
                              className="inline-flex items-center gap-1 text-xs font-semibold text-[#16204a] hover:underline"
                            >
                              Read full story →
                            </Link>
                          </div>
                        )}
                      </div>
                    </div>

                    {/* Actions right */}
                    <div className="flex items-center gap-1 flex-shrink-0">
                      {isUnread && (
                        <button
                          type="button"
                          title="Mark as read"
                          onClick={(e) => handleMarkAsRead(item.id, e)}
                          className="p-1.5 text-xs rounded-lg text-[#7c7f8c] hover:text-[#16204a] hover:bg-[#f4f2ee] transition-colors"
                        >
                          <svg
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            className="w-4 h-4"
                          >
                            <polyline points="20 6 9 17 4 12" />
                          </svg>
                        </button>
                      )}

                      <button
                        type="button"
                        title="Delete notification"
                        onClick={(e) => handleDelete(item.id, e)}
                        className="p-1.5 text-xs rounded-lg text-[#7c7f8c] hover:text-[#e5484d] hover:bg-[#f4f2ee] transition-colors"
                      >
                        <svg
                          viewBox="0 0 24 24"
                          fill="none"
                          stroke="currentColor"
                          strokeWidth="2"
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          className="w-4 h-4"
                        >
                          <polyline points="3 6 5 6 21 6" />
                          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </main>
    </div>
  );
}
