import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const reverbRuntime = (typeof window !== 'undefined' && window.__reverb) ? window.__reverb : {};

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY || reverbRuntime.key || '';
const reverbHost = import.meta.env.VITE_REVERB_HOST || reverbRuntime.host || (typeof window !== 'undefined' ? window.location.hostname : 'localhost');
const reverbPort = import.meta.env.VITE_REVERB_PORT || reverbRuntime.port || (typeof window !== 'undefined' && window.location.protocol === 'https:' ? 443 : 8080);
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME || reverbRuntime.scheme || (typeof window !== 'undefined' && window.location.protocol === 'https:' ? 'https' : 'http');

export const echo = new Echo({
    broadcaster: 'reverb',
    key: reverbKey,
    wsHost: reverbHost,
    wsPort: Number(reverbPort),
    wssPort: Number(reverbPort),
    forceTLS: reverbScheme === 'https',
    enabledTransports: ['ws', 'wss'],
});

window.Echo = echo;
export default echo;
