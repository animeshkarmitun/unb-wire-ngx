import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

if (typeof window !== 'undefined') {
    (window as any).Pusher = Pusher;
}

export function createEcho(): Echo<any> | null {
    if (typeof window === 'undefined') return null;
    return new Echo({
        broadcaster: 'reverb',
        key: process.env.NEXT_PUBLIC_REVERB_APP_KEY ?? 'local-key',
        wsHost: process.env.NEXT_PUBLIC_REVERB_HOST ?? 'localhost',
        wsPort: Number(process.env.NEXT_PUBLIC_REVERB_PORT ?? 8080),
        forceTLS: false,
        enabledTransports: ['ws'],
    });
}
