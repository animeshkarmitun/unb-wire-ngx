import { getApiKey, authHeaders } from './auth';
const LARAVEL = process.env.NEXT_PUBLIC_LARAVEL_URL ?? 'http://localhost:8000';

export async function downloadMedia(assetId: string | number, variant = 'original'): Promise<void> {
    const key = getApiKey();
    if (!key) {
        // Fallback to gradientPng for demo/guest mode
        const { gradientPng, saveBlob } = await import('./format');
        const blob = await gradientPng(typeof assetId === 'number' ? assetId : 0);
        saveBlob(blob, `unb-${assetId}-${variant}.png`, 'image/png');
        return;
    }
    const res = await fetch(`${LARAVEL}/api/v1/media/${assetId}/download?variant=${variant}`, {
        headers: { ...authHeaders() },
    });
    if (!res.ok) throw new Error('Download failed');
    const { url } = await res.json();
    window.open(url, '_blank');
}
