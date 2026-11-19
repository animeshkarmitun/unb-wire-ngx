"use client";

import React, { useState, useEffect, useCallback } from "react";
import Link from "next/link";
import { portalFetch } from "../../lib/api";

interface FtpChannel {
  id: number;
  host: string | null;
  port: number;
  username: string | null;
  protocol: string;
  has_password: boolean;
  status: string;
  failure_count: number;
  last_success_at: string | null;
}

interface Profile {
  id: number;
  name: string;
  email: string;
  can_manage_delivery: boolean;
  client: { name: string; initials: string; status: string };
}

interface PortalNotification {
  id: string;
  event: string;
  title: string;
  message: string;
  public_id: string | null;
  read_at: string | null;
  created_at: string;
}

const FTP_EVENTS = new Set(["story.ftp_failed", "story.ftp_paused", "story.ftp_resumed"]);

export default function FtpPage() {
  const [profile, setProfile] = useState<Profile | null>(null);
  const [channel, setChannel] = useState<FtpChannel | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [tab, setTab] = useState<"channel" | "notifications">("channel");

  // Form state
  const [host, setHost] = useState("");
  const [port, setPort] = useState(21);
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [protocol, setProtocol] = useState("ftp");
  const [hasStoredPw, setHasStoredPw] = useState(false);
  const [saving, setSaving] = useState(false);
  const [msg, setMsg] = useState<string | null>(null);
  const [isError, setIsError] = useState(false);

  const [testing, setTesting] = useState(false);
  const [testMsg, setTestMsg] = useState<{ ok: boolean; text: string } | null>(null);

  // FTP notifications
  const [ftpNotifs, setFtpNotifs] = useState<PortalNotification[]>([]);
  const [notifsLoading, setNotifsLoading] = useState(false);

  const loadChannel = useCallback(async () => {
    const data = await portalFetch<{ channel: FtpChannel | null }>("/api/v1/portal/ftp");
    setChannel(data.channel);
    if (data.channel) {
      setHost(data.channel.host ?? "");
      setPort(data.channel.port ?? 21);
      setUsername(data.channel.username ?? "");
      setProtocol(data.channel.protocol ?? "ftp");
      setHasStoredPw(!!data.channel.has_password);
    }
  }, []);

  const loadFtpNotifs = useCallback(async () => {
    setNotifsLoading(true);
    try {
      const data = await portalFetch<{ data: PortalNotification[] }>(
        "/api/v1/portal/notifications?filter=all&per_page=50"
      );
      setFtpNotifs((data.data ?? []).filter((n) => FTP_EVENTS.has(n.event)));
    } catch {
      setFtpNotifs([]);
    } finally {
      setNotifsLoading(false);
    }
  }, []);

  useEffect(() => {
    (async () => {
      try {
        const prof = await portalFetch<Profile>("/api/v1/portal/profile");
        setProfile(prof);
        await loadChannel();
      } catch (err: any) {
        setError(err?.body?.message || "Failed to load FTP settings");
      } finally {
        setLoading(false);
      }
    })();
  }, [loadChannel]);

  useEffect(() => {
    if (tab === "notifications") loadFtpNotifs();
  }, [tab, loadFtpNotifs]);

  const save = async () => {
    setSaving(true);
    setMsg(null);
    setIsError(false);
    try {
      const body: Record<string, unknown> = { host, port, username, protocol };
      if (password) body.password = password;
      const data = await portalFetch<{ channel: FtpChannel; message: string }>("/api/v1/portal/ftp", {
        method: "PUT",
        body: JSON.stringify(body),
      });
      setChannel(data.channel);
      setHasStoredPw(!!data.channel.has_password);
      setPassword("");
      setMsg(data.message || "Saved");
      setIsError(false);
      setTimeout(() => setMsg(null), 4000);
    } catch (err: any) {
      const text = err?.body?.errors
        ? Object.values(err.body.errors).flat().join(", ")
        : err?.body?.message || "Failed to save";
      setMsg(text);
      setIsError(true);
    } finally {
      setSaving(false);
    }
  };

  const testConnection = async () => {
    setTesting(true);
    setTestMsg(null);
    try {
      const body: Record<string, unknown> = { host, port, username, protocol };
      if (password) body.password = password;
      const data = await portalFetch<{ ok: boolean; message: string }>("/api/v1/portal/ftp/test", {
        method: "POST",
        body: JSON.stringify(body),
      });
      setTestMsg({ ok: data.ok, text: data.message });
    } catch (err: any) {
      const text = err?.body?.message || (err?.status === 422 ? err?.body?.message : "Test failed");
      setTestMsg({ ok: err?.body?.ok === true, text });
    } finally {
      setTesting(false);
    }
  };

  const setStatus = async (action: "pause" | "resume") => {
    setSaving(true);
    setMsg(null);
    setIsError(false);
    try {
      const data = await portalFetch<{ channel: FtpChannel; message: string }>(
        `/api/v1/portal/ftp/${action}`,
        { method: "POST" }
      );
      setChannel(data.channel);
      setMsg(data.message);
      setIsError(false);
      setTimeout(() => setMsg(null), 4000);
    } catch (err: any) {
      setMsg(err?.body?.message || "Failed");
      setIsError(true);
    } finally {
      setSaving(false);
    }
  };

  const removeChannel = async () => {
    setSaving(true);
    setMsg(null);
    setIsError(false);
    try {
      const data = await portalFetch<{ channel: FtpChannel | null; message: string }>(
        "/api/v1/portal/ftp",
        { method: "DELETE" }
      );
      setChannel(data.channel);
      setHasStoredPw(false);
      setHost("");
      setUsername("");
      setMsg(data.message || "Removed");
      setIsError(false);
    } catch (err: any) {
      setMsg(err?.body?.message || "Failed to remove");
      setIsError(true);
    } finally {
      setSaving(false);
    }
  };

  const markRead = async (id: string) => {
    try {
      await portalFetch(`/api/v1/portal/notifications/${id}/read`, { method: "PATCH" });
      setFtpNotifs((list) => list.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n)));
    } catch {
      // non-blocking
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-[#faf9f6] flex items-center justify-center">
        <p className="text-sm text-[#7c7f8c]">Loading FTP settings…</p>
      </div>
    );
  }

  if (error || !profile) {
    return (
      <div className="min-h-screen bg-[#faf9f6] flex items-center justify-center flex-col gap-4">
        <p className="text-sm text-[#e5484d]">{error || "Not available"}</p>
        <Link href="/" className="text-xs font-semibold text-[#16204a] hover:underline">
          ← Back to wire feed
        </Link>
      </div>
    );
  }

  if (!profile.can_manage_delivery) {
    return (
      <div className="min-h-screen bg-[#faf9f6]">
        <header className="sticky top-1 z-20 bg-white/95 backdrop-blur border-b border-[#eceae5] px-6 lg:px-10 py-3 flex items-center gap-4">
          <Link href="/" className="flex items-center gap-2">
            <div className="w-8 h-8 rounded-lg bg-[#e5484d] text-white font-bold flex items-center justify-center font-serif">U</div>
            <div className="font-serif font-bold text-base leading-none">UNB Wire</div>
          </Link>
          <Link href="/" className="ml-auto text-xs font-semibold text-[#4b4e5c] hover:text-[#16204a]">
            ← Back to wire feed
          </Link>
        </header>
        <main className="max-w-[640px] mx-auto px-6 py-16 text-center">
          <h1 className="font-serif text-xl font-bold mb-3">FTP management</h1>
          <p className="text-sm text-[#7c7f8c]">
            Only your organisation's admin portal user can manage the FTP channel.
            Contact your administrator or UNB support to make changes.
          </p>
        </main>
      </div>
    );
  }

  const statusColor =
    channel?.status === "active" ? "#30a46c" : channel?.status === "paused" ? "#f0a832" : "#7c7f8c";

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
        <Link href="/" className="ml-auto text-xs font-semibold text-[#4b4e5c] hover:text-[#16204a]">
          ← Back to wire feed
        </Link>
      </header>

      <main className="max-w-[720px] mx-auto px-6 py-10">
        <h1 className="font-serif text-2xl font-bold mb-2">FTP delivery</h1>
        <p className="text-xs text-[#7c7f8c] mb-6">
          Manage how stories are pushed to your newsroom's FTP server. Changes go live
          immediately — use "Test connection" to verify before relying on them.
        </p>

        {/* Tabs */}
        <div className="flex gap-1 border-b border-[#eceae5] mb-6">
          <button
            type="button"
            onClick={() => setTab("channel")}
            className={`px-4 py-2 text-xs font-semibold border-b-2 -mb-px ${tab === "channel" ? "border-[#e5484d] text-[#16204a]" : "border-transparent text-[#7c7f8c] hover:text-[#16204a]"}`}
          >
            FTP channel
          </button>
          <button
            type="button"
            onClick={() => setTab("notifications")}
            className={`px-4 py-2 text-xs font-semibold border-b-2 -mb-px ${tab === "notifications" ? "border-[#e5484d] text-[#16204a]" : "border-transparent text-[#7c7f8c] hover:text-[#16204a]"}`}
          >
            FTP notifications
          </button>
        </div>

        {tab === "channel" && (
          <>
            {channel && (
              <div className="bg-white rounded-xl border border-[#eceae5] p-4 mb-4 flex items-center gap-4">
                <span
                  className="w-2.5 h-2.5 rounded-full shrink-0"
                  style={{ background: statusColor }}
                />
                <div className="text-xs">
                  <span className="font-semibold text-[#16204a] capitalize">{channel.status}</span>
                  {channel.last_success_at && (
                    <span className="text-[#7c7f8c]">
                      {" "}· last success{" "}
                      {new Date(channel.last_success_at).toLocaleString("en-GB", { timeZone: "Asia/Dhaka", day: "numeric", month: "short", hour: "numeric", minute: "2-digit", hour12: true })}
                    </span>
                  )}
                  {channel.failure_count > 0 && (
                    <span className="text-[#e5484d]"> · {channel.failure_count} recent failures</span>
                  )}
                </div>
              </div>
            )}

            <section className="bg-white rounded-xl border border-[#eceae5] p-6 mb-4">
              <h2 className="text-sm font-semibold text-[#16204a] mb-4">
                {channel ? "Channel settings" : "Add your FTP channel"}
              </h2>
              <div className="space-y-3">
                <div className="grid grid-cols-3 gap-3">
                  <div className="col-span-2">
                    <label className="block text-xs text-[#7c7f8c] mb-1">Host</label>
                    <input
                      type="text"
                      value={host}
                      onChange={(e) => setHost(e.target.value)}
                      maxLength={190}
                      placeholder="ftp.yournewsroom.com"
                      className="w-full border border-[#d9d7cf] rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#16204a]"
                    />
                  </div>
                  <div>
                    <label className="block text-xs text-[#7c7f8c] mb-1">Port</label>
                    <input
                      type="number"
                      value={port}
                      onChange={(e) => setPort(Number(e.target.value))}
                      min={1}
                      max={65535}
                      className="w-full border border-[#d9d7cf] rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#16204a]"
                    />
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs text-[#7c7f8c] mb-1">Username</label>
                    <input
                      type="text"
                      value={username}
                      onChange={(e) => setUsername(e.target.value)}
                      maxLength={120}
                      className="w-full border border-[#d9d7cf] rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#16204a]"
                    />
                  </div>
                  <div>
                    <label className="block text-xs text-[#7c7f8c] mb-1">
                      Password {hasStoredPw && <span className="text-[#30a46c]">(saved — leave blank to keep)</span>}
                    </label>
                    <input
                      type="password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      autoComplete="new-password"
                      className="w-full border border-[#d9d7cf] rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#16204a]"
                    />
                  </div>
                </div>
                <div>
                  <label className="block text-xs text-[#7c7f8c] mb-1">Protocol</label>
                  <select
                    value={protocol}
                    onChange={(e) => setProtocol(e.target.value)}
                    className="border border-[#d9d7cf] rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#16204a]"
                  >
                    <option value="ftp">FTP</option>
                    <option value="ftps">FTPS (FTP over TLS)</option>
                    <option value="sftp">SFTP</option>
                  </select>
                  <p className="text-[11px] text-[#b0b2bc] mt-1">
                    SFTP typically uses port 22. Wire format (JSON/XML) is configured by UNB staff.
                  </p>
                </div>

                <div className="flex flex-wrap gap-2 pt-2">
                  <button
                    type="button"
                    onClick={save}
                    disabled={saving || !host || !username}
                    className="px-4 py-2 rounded-lg bg-[#16204a] text-white text-xs font-semibold disabled:opacity-50"
                  >
                    {saving ? "Saving…" : channel ? "Save changes" : "Add channel"}
                  </button>
                  <button
                    type="button"
                    onClick={testConnection}
                    disabled={testing || !host || !username}
                    className="px-4 py-2 rounded-lg border border-[#d9d7cf] text-xs font-semibold text-[#16204a] disabled:opacity-50"
                  >
                    {testing ? "Testing…" : "Test connection"}
                  </button>
                  {channel && channel.status === "active" && (
                    <button
                      type="button"
                      onClick={() => setStatus("pause")}
                      disabled={saving}
                      className="px-4 py-2 rounded-lg border border-[#f0d9a8] bg-[#fdf6ec] text-xs font-semibold text-[#8a5410] disabled:opacity-50"
                    >
                      Pause delivery
                    </button>
                  )}
                  {channel && channel.status === "paused" && (
                    <button
                      type="button"
                      onClick={() => setStatus("resume")}
                      disabled={saving}
                      className="px-4 py-2 rounded-lg border border-[#b7e2c3] bg-[#f0fdf4] text-xs font-semibold text-[#15803d] disabled:opacity-50"
                    >
                      Resume delivery
                    </button>
                  )}
                  {channel && (
                    <button
                      type="button"
                      onClick={removeChannel}
                      disabled={saving}
                      className="px-4 py-2 rounded-lg border border-[#f0c9ca] text-xs font-semibold text-[#d13438] disabled:opacity-50"
                    >
                      Remove
                    </button>
                  )}
                </div>

                {testMsg && (
                  <p className={`text-xs ${testMsg.ok ? "text-[#30a46c]" : "text-[#e5484d]"}`}>{testMsg.text}</p>
                )}
                {msg && <p className={`text-xs ${isError ? "text-[#e5484d]" : "text-[#30a46c]"}`}>{msg}</p>}
              </div>
            </section>
          </>
        )}

        {tab === "notifications" && (
          <section className="bg-white rounded-xl border border-[#eceae5] overflow-hidden">
            {notifsLoading ? (
              <p className="p-6 text-xs text-[#7c7f8c]">Loading…</p>
            ) : ftpNotifs.length === 0 ? (
              <p className="p-6 text-xs text-[#7c7f8c]">
                No FTP notifications. Delivery failures and channel pauses will appear here.
              </p>
            ) : (
              ftpNotifs.map((n) => (
                <div
                  key={n.id}
                  className={`p-4 border-b border-[#eceae5] last:border-b-0 ${n.read_at ? "" : "bg-[#fbf7ef]"}`}
                >
                  <div className="flex items-start gap-3">
                    <span
                      className="w-2 h-2 rounded-full mt-1.5 shrink-0"
                      style={{ background: n.event === "story.ftp_failed" ? "#e5484d" : "#f0a832" }}
                    />
                    <div className="flex-1 min-w-0">
                      <div className="text-sm font-semibold text-[#16204a]">{n.title}</div>
                      <div className="text-xs text-[#5d6172] mt-0.5">{n.message}</div>
                      <div className="flex items-center gap-3 mt-1.5">
                        <span className="text-[11px] text-[#b0b2bc]">
                          {new Date(n.created_at).toLocaleString("en-GB", { timeZone: "Asia/Dhaka", day: "numeric", month: "short", hour: "numeric", minute: "2-digit", hour12: true })}
                        </span>
                        {n.public_id && (
                          <Link href={`/story/${n.public_id}`} className="text-[11px] font-semibold text-[#16204a] hover:underline">
                            Open story →
                          </Link>
                        )}
                        {!n.read_at && (
                          <button type="button" onClick={() => markRead(n.id)} className="text-[11px] text-[#7c7f8c] hover:text-[#16204a]">
                            Mark read
                          </button>
                        )}
                      </div>
                    </div>
                  </div>
                </div>
              ))
            )}
          </section>
        )}
      </main>
    </div>
  );
}
