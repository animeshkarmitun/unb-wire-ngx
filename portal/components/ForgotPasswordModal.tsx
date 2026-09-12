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
    <div className="lightbox open" style={{ zIndex: 1000, display: 'flex', alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(0,0,0,0.5)' }}>
      <div style={{ backgroundColor: '#fff', padding: '24px', borderRadius: '8px', width: '100%', maxWidth: '400px', color: '#000' }}>
        <h2 style={{ margin: '0 0 16px 0', fontSize: '18px', fontWeight: 600 }}>Reset Password</h2>

        {sent ? (
          <div>
            <p style={{ fontSize: '14px', color: '#555', marginBottom: '16px' }}>
              If the email exists, a reset link has been sent. Please check your inbox.
            </p>
            <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
              <button
                type="button"
                className="action-btn"
                style={{ backgroundColor: '#000', color: '#fff' }}
                onClick={onBackToLogin}
              >
                Back to login
              </button>
            </div>
          </div>
        ) : (
          <form onSubmit={handleSubmit}>
            <p style={{ fontSize: '14px', color: '#555', marginBottom: '16px' }}>
              Enter your email address and we&apos;ll send you a link to reset your password.
            </p>
            <div style={{ marginBottom: '16px' }}>
              <label style={{ display: 'block', marginBottom: '8px', fontSize: '14px', fontWeight: 500 }}>Email</label>
              <input
                type="email"
                className="search-input"
                style={{ width: '100%', padding: '8px', boxSizing: 'border-box' }}
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="Enter your email"
                disabled={loading}
                autoFocus
              />
            </div>
            {error && <div style={{ color: '#dc2626', marginBottom: '16px', fontSize: '14px' }}>{error}</div>}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <button
                type="button"
                style={{ background: 'none', border: 'none', color: '#0066cc', cursor: 'pointer', fontSize: '13px', padding: 0 }}
                onClick={onBackToLogin}
              >
                Back to login
              </button>
              <div style={{ display: 'flex', gap: '12px' }}>
                <button type="button" className="action-btn" onClick={onClose} disabled={loading}>
                  Cancel
                </button>
                <button type="submit" className="action-btn" style={{ backgroundColor: '#000', color: '#fff' }} disabled={loading}>
                  {loading ? 'Sending...' : 'Send reset link'}
                </button>
              </div>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}
