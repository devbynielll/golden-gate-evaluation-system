import { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import downloadReport from './downloadReport';

export default function useReport(routeName, selected) {
    const [state, setState] = useState({ open: false, loading: false, report: null, error: '' });
    const request = useRef(null);
    const lastTarget = useRef(null);
    const refreshing = useRef(false);
    // Capture only applied server filters, never the editable dropdown/search state or page.
    const filters = routeName === 'reports.faculty'
        ? { department_id: selected.department_id || undefined, period_id: selected.period_id || undefined }
        : { search: selected.search || undefined, period_id: selected.period_id || undefined, ...(routeName === 'reports.staff' ? { office_id: selected.office_id || undefined } : {}) };
    const filterKey = JSON.stringify(filters);
    const close = () => {
        request.current?.abort();
        setState({ open: false, loading: false, report: null, error: '' });
    };
    useEffect(() => {
        close();
        return () => request.current?.abort();
    }, [routeName, filterKey]);

    const open = async (targetId = null, download = false) => {
        request.current?.abort();
        lastTarget.current = targetId;
        const controller = new AbortController();
        request.current = controller;
        setState({ open: true, loading: true, report: null, error: '' });
        try {
            const { data } = await axios.get(route(routeName), {
                params: { ...filters, report: targetId === null ? 'summary' : 'individual', target_id: targetId ?? undefined },
                headers: { Accept: 'application/json' }, signal: controller.signal,
            });
            if (controller.signal.aborted) return;
            setState({ open: true, loading: download, report: data, error: '' });
            if (download) {
                await downloadReport(data, controller.signal);
                if (!controller.signal.aborted) close();
            }
        } catch (error) {
            if (controller.signal.aborted) return;
            const message = [401, 403, 419].includes(error.response?.status)
                ? 'Your session or report access has changed. Refresh the page and sign in again if needed.'
                : 'The report could not be loaded or downloaded. Please try again.';
            setState({ open: true, loading: false, report: null, error: message });
        }
    };
    const downloadCurrent = async () => {
        if (!state.report || state.loading) return;
        if (routeName === 'reports.staff') return open(lastTarget.current, true);
        const controller = request.current;
        setState((previous) => ({ ...previous, loading: true, error: '' }));
        try {
            await downloadReport(state.report, controller?.signal);
            if (!controller?.signal.aborted) setState((previous) => ({ ...previous, loading: false }));
        } catch {
            if (!controller?.signal.aborted) setState((previous) => ({ ...previous, loading: false, error: 'The PDF could not be generated. Please try again.' }));
        }
    };
    useEffect(() => {
        if (routeName !== 'reports.staff' || !state.open || !state.report || typeof window === 'undefined') return;
        const refresh = async () => {
            const controller = request.current;
            if (document.hidden || controller?.signal.aborted || refreshing.current) return;
            refreshing.current = true;
            try {
                const { data } = await axios.get(route(routeName), { params: { ...filters,
                    report: lastTarget.current === null ? 'summary' : 'individual', target_id: lastTarget.current ?? undefined },
                    headers: { Accept: 'application/json' }, signal: controller.signal });
                if (!controller.signal.aborted) setState((previous) => ({ ...previous, report: data, error: '' }));
            } catch {
                if (!controller.signal.aborted) setState((previous) => ({ ...previous, report: null, error: 'Staff report access or release could not be verified. Close and reopen the report to try again.' }));
            } finally { refreshing.current = false; }
        };
        const timer = window.setInterval(refresh, 5000);
        window.addEventListener('focus', refresh);
        document.addEventListener('visibilitychange', refresh);
        return () => { window.clearInterval(timer); window.removeEventListener('focus', refresh); document.removeEventListener('visibilitychange', refresh); };
    }, [routeName, filterKey, state.open, Boolean(state.report)]);

    return { ...state, open, show: state.open, close, downloadCurrent };
}
