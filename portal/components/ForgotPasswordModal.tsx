import React, { useState } from 'react';

export default function ForgotPasswordModal({
  isOpen,
  onClose,
  onBackToLogin
}: {
  isOpen: boolean;
  onClose: () => void;
  onBackToLogin: () => void;
}) {
  const [email, setEmail] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [sent, setSent] = useState(false);

  if (!isOpen) return null;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.trim()) return;

    setLoading(true);
    setError('');

    try {
      const baseUrl = process.env.NEXT_PUBLIC_LARAVEL_URL ?? 'http://localhost:8000';
      const res = await fetch(`${baseUrl}/api/v1/portal/forgot-password`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ email })
      });

      if (!res.ok) {
        const data = await res.json();
        throw new Error(data.message || 'Failed to send reset link');
      }

      setSent(true);
    } catch (err: any) {
      setError(err.message || 'Failed to send reset link');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 overflow-y-auto">
      <div className="relative w-full max-w-md bg-white rounded-2xl shadow-2xl border border-gray-100 p-6 md:p-8 text-[#16204a] animate-in fade-in zoom-in-95 duration-150">
        <button
          type="button"
          onClick={onClose}
          className="absolute top-4 right-4 text-gray-400 hover:text-gray-600 p-2 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer"
          aria-label="Close"
        >
          <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>

        <div className="flex items-center gap-3 mb-6">
          <div className="w-10 h-10 rounded-xl bg-[#16204a] text-white flex items-center justify-center font-serif text-xl font-bold shadow-sm">
            U
          </div>
          <div>
            <h2 className="text-lg font-bold text-[#16204a]">Reset Password</h2>
            <p className="text-xs text-[#7c7f8c]">UNB Wire Media Partner Portal</p>
          </div>
        </div>

        {sent ? (
          <div className="space-y-4">
            <div className="p-4 rounded-xl bg-green-50 border border-green-200 text-xs text-green-700 leading-relaxed">
              If an account with that email exists, we have dispatched password reset instructions. Please check your inbox.
            </div>
            <button
              type="button"
              onClick={onBackToLogin}
              className="w-full py-3 px-4 rounded-xl bg-[#16204a] hover:bg-[#202c65] text-white text-xs font-semibold transition-all shadow hover:shadow-md cursor-pointer"
            >
              Back to Sign In
            </button>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            <p className="text-xs text-[#7c7f8c] leading-relaxed">
              Enter your registered newsroom email address and we&apos;ll send you instructions to reset your password.
            </p>

            {error && (
              <div className="p-3 rounded-xl bg-red-50 border border-red-200 text-xs text-red-600 font-medium flex items-center gap-2">
                <svg className="w-4 h-4 shrink-0 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                  <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                </svg>
                <span>{error}</span>
              </div>
            )}

            <div>
              <label className="block text-xs font-semibold uppercase tracking-wider text-[#7c7f8c] mb-1.5">Email Address</label>
              <input
                type="email"
                className="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm focus:outline-none focus:ring-2 focus:ring-[#16204a]/20 focus:border-[#16204a] transition-all bg-white text-gray-900 placeholder:text-gray-400"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="newsdesk@client.com"
                disabled={loading}
                autoFocus
              />
            </div>

            <div className="pt-2 flex items-center justify-between">
              <button
                type="button"
                onClick={onBackToLogin}
                className="text-xs text-[#16204a] hover:underline cursor-pointer font-medium"
              >
                ← Back to Sign In
              </button>
              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={onClose}
                  disabled={loading}
                  className="px-4 py-2.5 rounded-xl border border-gray-200 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={loading}
                  className="px-5 py-2.5 rounded-xl bg-[#16204a] hover:bg-[#202c65] text-white text-xs font-semibold transition-all shadow hover:shadow-md cursor-pointer disabled:opacity-50 flex items-center gap-2"
                >
                  {loading ? (
                    <>
                      <div className="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin" />
                      <span>Sending…</span>
                    </>
                  ) : (
                    'Send Reset Link'
                  )}
                </button>
              </div>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}
