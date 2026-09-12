import React, { useState } from 'react';

export default function LoginModal({
  isOpen,
  onClose,
  onSuccess
}: {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: (apiKey: string, clientInfo: any) => void;
}) {
  const [apiKey, setApiKeyValue] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  if (!isOpen) return null;

  const handleSubmit = async (e: React.FormEvent) => {
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
        onSuccess(apiKey, data.client);
      } else {
        throw new Error('Invalid response from server');
      }
    } catch (err: any) {
      setError(err.message || 'Failed to login');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="lightbox open" style={{ zIndex: 1000, display: 'flex', alignItems: 'center', justifyContent: 'center', backgroundColor: 'rgba(0,0,0,0.5)' }}>
      <div style={{ backgroundColor: '#fff', padding: '24px', borderRadius: '8px', width: '100%', maxWidth: '400px', color: '#000' }}>
        <h2 style={{ margin: '0 0 16px 0', fontSize: '18px', fontWeight: 600 }}>Client Login</h2>
        <form onSubmit={handleSubmit}>
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
          <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '12px' }}>
            <button type="button" className="action-btn" onClick={onClose} disabled={loading}>
              Cancel
            </button>
            <button type="submit" className="action-btn" style={{ backgroundColor: '#000', color: '#fff' }} disabled={loading}>
              {loading ? 'Logging in...' : 'Login'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
