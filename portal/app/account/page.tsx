"use client";

import React, { useState, useEffect } from "react";
import Link from "next/link";
import { portalFetch } from "../../lib/api";

interface Profile {
  id: number;
  name: string;
  email: string;
  client_role: { name: string | null };
  client: { name: string; initials: string; status: string };
  last_login_at: string | null;
}

export default function AccountPage() {
  const [profile, setProfile] = useState<Profile | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Edit state
  const [editing, setEditing] = useState(false);
  const [editName, setEditName] = useState("");
  const [editEmail, setEditEmail] = useState("");
  const [saving, setSaving] = useState(false);
  const [saveMsg, setSaveMsg] = useState<string | null>(null);

  // Password state
  const [currentPw, setCurrentPw] = useState("");
  const [newPw, setNewPw] = useState("");
  const [confirmPw, setConfirmPw] = useState("");
  const [pwSaving, setPwSaving] = useState(false);
  const [pwMsg, setPwMsg] = useState<string | null>(null);
  const [pwError, setPwError] = useState<string | null>(null);

  useEffect(() => {
    portalFetch<Profile>("/api/v1/portal/profile")
      .then((data) => {
        setProfile(data);
        setEditName(data.name);
        setEditEmail(data.email);
      })
      .catch(() => setError("Failed to load profile"))
      .finally(() => setLoading(false));
  }, []);

  const handleSaveProfile = async () => {
    setSaving(true);
    setSaveMsg(null);
    try {
      const updated = await portalFetch<Profile>("/api/v1/portal/profile", {
        method: "PATCH",
        body: JSON.stringify({ name: editName, email: editEmail }),
      });
      setProfile(updated);
      setEditing(false);
      setSaveMsg("Profile updated");
      setTimeout(() => setSaveMsg(null), 3000);
    } catch (err: any) {
      const msg = err?.body?.errors
        ? Object.values(err.body.errors).flat().join(", ")
        : "Failed to update profile";
      setSaveMsg(msg);
    } finally {
      setSaving(false);
    }
  };

  const handleChangePassword = async () => {
    setPwSaving(true);
    setPwMsg(null);
    setPwError(null);
    try {
      const res = await portalFetch<{ message: string }>("/api/v1/portal/password", {
        method: "PATCH",
        body: JSON.stringify({
          current_password: currentPw,
          password: newPw,
          password_confirmation: confirmPw,
        }),
      });
      setPwMsg(res.message);
      setCurrentPw("");
      setNewPw("");
      setConfirmPw("");
      setTimeout(() => setPwMsg(null), 3000);
    } catch (err: any) {
      const msg = err?.body?.errors
        ? Object.values(err.body.errors).flat().join(", ")
        : err?.body?.message || "Failed to change password";
      setPwError(msg);
    } finally {
      setPwSaving(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-[#faf9f6] flex items-center justify-center">
        <p className="text-sm text-[#7c7f8c]">Loading profile…</p>
      </div>
    );
  }

  if (error || !profile) {
    return (
      <div className="min-h-screen bg-[#faf9f6] flex items-center justify-center flex-col gap-4">
        <p className="text-sm text-[#e5484d]">{error || "Profile not found"}</p>
        <Link href="/" className="text-xs font-semibold text-[#16204a] hover:underline">
          ← Back to wire feed
        </Link>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-[#faf9f6]">
      <header className="sticky top-1 z-20 bg-white/95 backdrop-blur border-b border-[#eceae5] px-6 lg:px-10 py-3 flex items-center gap-4">
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
        <div className="ml-auto flex items-center gap-3 text-xs">
          <Link href="/" className="text-[#4b4e5c] hover:text-[#16204a] font-semibold">
            ← Back to wire feed
          </Link>
        </div>
      </header>

      <main className="max-w-[640px] mx-auto px-6 py-10">
        <h1 className="font-serif text-2xl font-bold mb-8">My Account</h1>

        {/* Profile Card */}
        <section className="bg-white rounded-xl border border-[#eceae5] p-6 mb-6">
          <div className="flex items-center gap-4 mb-6">
            <div className="w-12 h-12 rounded-full bg-[#16204a] text-white font-bold flex items-center justify-center text-lg">
              {profile.client.initials}
            </div>
            <div>
              <div className="font-semibold text-[#16204a]">{profile.client.name}</div>
              <div className="text-xs text-[#7c7f8c]">
                {profile.client_role?.name || "Client"} · {profile.client.status}
              </div>
            </div>
          </div>

          <div className="border-t border-[#eceae5] pt-4">
            <div className="flex items-center justify-between mb-3">
              <h2 className="text-sm font-semibold text-[#16204a]">Profile details</h2>
              {!editing && (
                <button
                  type="button"
                  className="text-xs font-semibold text-[#16204a] hover:underline"
                  onClick={() => {
                    setEditing(true);
                    setSaveMsg(null);
                  }}
                >
                  Edit
                </button>
              )}
            </div>

            {editing ? (
              <div className="space-y-3">
                <div>
                  <label className="block text-xs text-[#7c7f8c] mb-1">Name</label>
                  <input
                    type="text"
                    value={editName}
                    onChange={(e) => setEditName(e.target.value)}
                    maxLength={120}
                    className="w-full border border-[#d9d7cf] rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#16204a]"
                  />
                </div>
                <div>
                  <label className="block text-xs text-[#7c7f8c] mb-1">Email</label>
                  <input
                    type="email"
                    value={editEmail}
                    onChange={(e) => setEditEmail(e.target.value)}
                    className="w-full border border-[#d9d7cf] rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#16204a]"
                  />
                </div>
                <div className="flex gap-2 pt-1">
                  <button
                    type="button"
                    onClick={handleSaveProfile}
                    disabled={saving}
                    className="px-4 py-2 rounded-lg bg-[#16204a] text-white text-xs font-semibold disabled:opacity-50"
                  >
                    {saving ? "Saving…" : "Save changes"}
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      setEditing(false);
                      setEditName(profile.name);
                      setEditEmail(profile.email);
                    }}
                    className="px-4 py-2 rounded-lg border border-[#d9d7cf] text-xs font-semibold text-[#4b4e5c]"
                  >
                    Cancel
                  </button>
                </div>
                {saveMsg && (
                  <p className={`text-xs mt-2 ${saveMsg.includes("updated") ? "text-[#30a46c]" : "text-[#e5484d]"}`}>
                    {saveMsg}
                  </p>
                )}
              </div>
            ) : (
              <div className="space-y-2">
                <div className="flex justify-between text-sm">
                  <span className="text-[#7c7f8c]">Name</span>
                  <span className="font-medium">{profile.name}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-[#7c7f8c]">Email</span>
                  <span className="font-medium">{profile.email}</span>
                </div>
                <div className="flex justify-between text-sm">
                  <span className="text-[#7c7f8c]">Last login</span>
                  <span className="font-medium">
                    {profile.last_login_at
                      ? new Date(profile.last_login_at).toLocaleString()
                      : "—"}
                  </span>
                </div>
                {saveMsg && (
                  <p className="text-xs text-[#30a46c] mt-2">{saveMsg}</p>
                )}
              </div>
            )}
          </div>
        </section>

        {/* Password Card */}
        <section className="bg-white rounded-xl border border-[#eceae5] p-6">
          <h2 className="text-sm font-semibold text-[#16204a] mb-4">Change password</h2>
          <div className="space-y-3">
            <div>
              <label className="block text-xs text-[#7c7f8c] mb-1">Current password</label>
              <input
                type="password"
                value={currentPw}
                onChange={(e) => setCurrentPw(e.target.value)}
                autoComplete="current-password"
                className="w-full border border-[#d9d7cf] rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#16204a]"
              />
            </div>
            <div>
              <label className="block text-xs text-[#7c7f8c] mb-1">New password</label>
              <input
                type="password"
                value={newPw}
                onChange={(e) => setNewPw(e.target.value)}
                autoComplete="new-password"
                className="w-full border border-[#d9d7cf] rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#16204a]"
              />
            </div>
            <div>
              <label className="block text-xs text-[#7c7f8c] mb-1">Confirm new password</label>
              <input
                type="password"
                value={confirmPw}
                onChange={(e) => setConfirmPw(e.target.value)}
                autoComplete="new-password"
                className="w-full border border-[#d9d7cf] rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#16204a]"
              />
            </div>
            <button
              type="button"
              onClick={handleChangePassword}
              disabled={pwSaving || !currentPw || !newPw || !confirmPw}
              className="px-4 py-2 rounded-lg bg-[#16204a] text-white text-xs font-semibold disabled:opacity-50"
            >
              {pwSaving ? "Updating…" : "Change password"}
            </button>
            {pwMsg && <p className="text-xs text-[#30a46c]">{pwMsg}</p>}
            {pwError && <p className="text-xs text-[#e5484d]">{pwError}</p>}
          </div>
        </section>
      </main>
    </div>
  );
}
