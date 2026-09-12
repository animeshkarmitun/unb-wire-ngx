import React, { useState } from 'react';
import ForgotPasswordModal from './ForgotPasswordModal';

export default function LoginModal({
  isOpen,
  onClose,
  onSuccess
}: {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (token: string, clientInfo: any, authType: 'portal' | 'apikey') => void;
}) {
  const [mode, setMode] = useState<'email' | 'apikey'>('email');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [apiKey, setApiKeyValue] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [showForgotPassword, setShowForgotPassword] = useState(false);

  if (!isOpen) return null;

  const handleEmailLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.trim() || !password.trim()) return;

    setLoading(true);
    setError('');

    try {
      const baseUrl = process.env.NEXT_PUBLIC_LARAVEL_URL ?? 'http://localhost:8000';
      const res = await fetch(`${baseUrl}/api/v1/portal/login`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json'
        },
        body: JSON.stringify({ email, password })
      });

      if (!res.ok) {
        const data = await res.json();
        throw new Error(data.message || 'Invalid credentials');
      }

      const data = await res.json();
      onSuccess(data.token, data.client_user, 'portal');
    } catch (err: any) {
      setError(err.message || 'Failed to login');
    } finally {
      setLoading(false);
    }
  };

  const handleApiKeyLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!apiKey.trim()) return;

    setLoading(true);
    setError('');

    try {
      const baseUrl = process.env.NEXT_PUBLIC_LARAVEL_URL ?? 'http://localhost:8000';
      const res = await fetch(`${baseUrl}/api/v1/portal/context`, {
        headers: {
          'X-API-Key': apiKey,
          'Accept': 'application/json'
        }
      });

      if (!res.ok) {
        throw new Error('Invalid API key');
      }

      const data = await res.json();
      if (data && data.client) {
        onSuccess(apiKey, data.client, 'apikey');
      } else {
        throw new Error('Invalid response from server');
      }
    } catch (err: any) {
      setError(err.message || 'Failed to login');
    } finally {
      setLoading(false);
    }
  };

  if (showForgotPassword) {
    return (
      <ForgotPasswordModal
        isOpen={true}
        onClose={() => setShowForgotPassword(false)}
        onBackToLogin={() => setShowForgotPassword(false)}
      />
    );
  }

  return (
    <div className="lightbox open" style={{ zIndex: 1000, display: 'flex', alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(0,0,0,0.5)' }}>
      <div style={{ backgroundColor: '#fff', padding: '24px', borderRadius: '8px', width: '100%', maxWidth: '400px', color: '#000' }}>
        <h2 style={{ margin: '0 0 16px 0', fontSize: '18px', fontWeight: 600 }}>Client Login</h2>

        {mode === 'email' ? (
          <form onSubmit={handleEmailLogin}>
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
            <div style={{ marginBottom: '8px' }}>
              <label style={{ display: 'block', marginBottom: '8px', fontSize: '14px', fontWeight: 500 }}>Password</label>
              <input
                type="password"
                className="search-input"
                style={{ width: '100%', padding: '8px', boxSizing: 'border-box' }}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Enter your password"
                disabled={loading}
              />
            </div>
            <div style={{ marginBottom: '16px', textAlign: 'right' }}>
              <button
                type="button"
                style={{ background: 'none', border: 'none', color: '#0066cc', cursor: 'pointer', fontSize: '13px', padding: 0 }}
                onClick={() => setShowForgotPassword(true)}
              >
                Forgot password?
              </button>
            </div>
            {error && <div style={{ color: '#dc2626', marginBottom: '16px', fontSize: '14px' }}>{error}</div>}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <button
                type="button"
                style={{ background: 'none', border: 'none', color: '#0066cc', cursor: 'pointer', fontSize: '13px', padding: 0 }}
                onClick={() => { setMode('apikey'); setError(''); }}
              >
                Use API key
              </button>
              <div style={{ display: 'flex', gap: '12px' }}>
                <button type="button" className="action-btn" onClick={onClose} disabled={loading}>
                  Cancel
                </button>
                <button type="submit" className="action-btn" style={{ backgroundColor: '#000', color: '#fff' }} disabled={loading}>
                  {loading ? 'Logging in...' : 'Login'}
                </button>
              </div>
            </div>
          </form>
        ) : (
          <form onSubmit={handleApiKeyLogin}>
            <div style={{ marginBottom: '16px' }}>
              <label style={{ display: 'block', marginBottom: '8px', fontSize: '14px', fontWeight: 500 }}>API Key</label>
              <input
                type="password"
                className="search-input"
                style={{ width: '100%', padding: '8px', boxSizing: 'border-box' }}
                value={apiKey}
                onChange={(e) => setApiKeyValue(e.target.value)}
                placeholder="Enter your API key"
                disabled={loading}
                autoFocus
              />
            </div>
            {error && <div style={{ color: '#dc2626', marginBottom: '16px', fontSize: '14px' }}>{error}</div>}
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
              <button
                type="button"
                style={{ background: 'none', border: 'none', color: '#0066cc', cursor: 'pointer', fontSize: '13px', padding: 0 }}
                onClick={() => { setMode('email'); setError(''); }}
              >
                Use email &amp; password
              </button>
              <div style={{ display: 'flex', gap: '12px' }}>
                <button type="button" className="action-btn" onClick={onClose} disabled={loading}>
                  Cancel
                </button>
                <button type="submit" className="action-btn" style={{ backgroundColor: '#000', color: '#fff' }} disabled={loading}>
                  {loading ? 'Logging in...' : 'Login'}
                </button>
              </div>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}
