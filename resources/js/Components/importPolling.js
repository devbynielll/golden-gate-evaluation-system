// Keep polling after transient network errors. Never retry the import POST itself.
export function startImportPolling({ fetchStatus, onStatus, onError, schedule = setTimeout, cancel = clearTimeout }) {
    let stopped = false;
    let timer;
    let failures = 0;
    const tick = async () => {
        try {
            const data = await fetchStatus();
            if (stopped) return;
            if (!data || !['pending', 'processing', 'completed', 'failed'].includes(data.status)) throw new Error('Invalid progress response');
            failures = 0;
            onError('');
            onStatus(data);
            if (['completed', 'failed'].includes(data.status)) return;
            timer = schedule(tick, 2000);
        } catch (error) {
            if (stopped) return;
            const code = error.response?.status;
            if ([401, 403, 419].includes(code)) {
                onError('Your session expired or access was denied. Sign in again to check the saved records. Do not repeat the import blindly.');
                return;
            }
            onError('Progress connection interrupted. Retrying automatically; the queued import may still be running.');
            timer = schedule(tick, Math.min(15000, 2000 * 2 ** Math.min(++failures, 3)));
        }
    };
    tick();
    return () => { stopped = true; cancel(timer); };
}
