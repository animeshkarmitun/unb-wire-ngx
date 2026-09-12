export function getApiKey(): string | null {
  return typeof window !== 'undefined' ? sessionStorage.getItem('unb_api_key') : null;
}

export function setApiKey(key: string): void {
  sessionStorage.setItem('unb_api_key', key);
}

export function clearApiKey(): void {
  sessionStorage.removeItem('unb_api_key');
}

export function authHeaders(): Record<string, string> {
  const key = getApiKey();
  return key ? { 'X-API-Key': key } : {};
}

export function getPortalToken(): string | null {
  return typeof window !== 'undefined' ? sessionStorage.getItem('unb_portal_token') : null;
}

export function setPortalToken(token: string): void {
  sessionStorage.setItem('unb_portal_token', token);
}

export function clearPortalToken(): void {
  sessionStorage.removeItem('unb_portal_token');
}

export function portalAuthHeaders(): Record<string, string> {
  const token = getPortalToken();
  return token ? { 'Authorization': `Bearer ${token}` } : {};
}
