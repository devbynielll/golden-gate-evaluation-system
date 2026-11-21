import { useEffect, useRef, useState } from 'react';
import { DialogTitle } from '@headlessui/react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import Modal from './Modal';

const EMPTY = { unread: 0, items: [], page: 1, lastPage: 1 };

export default function EvaluationNotifications() {
    const [show, setShow] = useState(false);
    const [inbox, setInbox] = useState(EMPTY);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const request = useRef(null);
    const currentPage = useRef(1);
    const mutation = useRef(null);
    const load = async (page = currentPage.current) => {
        request.current?.abort();
        const controller = new AbortController();
        request.current = controller;
        setLoading(true);
        setError('');
        // Remove previously delivered values until their current release/access is verified.
        setInbox(EMPTY);
        try {
            const { data } = await axios.get(route('admin-notifications.index'), {
                params: { page }, headers: { Accept: 'application/json' }, signal: controller.signal,
            });
            if (controller.signal.aborted) return;
            currentPage.current = data.page;
            setInbox(data);
        } catch {
            if (!controller.signal.aborted) setError('Notifications could not be verified. Please try again.');
        } finally { if (!controller.signal.aborted) setLoading(false); }
    };
    useEffect(() => {
        load(1);
        const refresh = () => { if (!document.hidden && !mutation.current) load(); };
        const timer = window.setInterval(refresh, 15000);
        window.addEventListener('focus', refresh);
        document.addEventListener('visibilitychange', refresh);
        return () => {
            request.current?.abort(); mutation.current?.abort(); window.clearInterval(timer);
            window.removeEventListener('focus', refresh); document.removeEventListener('visibilitychange', refresh);
        };
    }, []);
    const changeRead = async (item, read, follow = false) => {
        if (mutation.current) return;
        const controller = new AbortController();
        mutation.current = controller;
        request.current?.abort();
        setLoading(false);
        setBusy(true); setError('');
        try {
            // Server rechecks recipient, current scope, closure and threshold before every write/link.
            await axios.patch(route('admin-notifications.update', item.id), { read }, {
                headers: { Accept: 'application/json' }, signal: controller.signal,
            });
            if (controller.signal.aborted) return;
            if (follow) { setShow(false); router.visit(item.url); }
            else await load();
        } catch {
            if (!controller.signal.aborted) {
                setInbox(EMPTY);
                setError('Notification access or release has changed, or the update failed. Refresh notifications to try again.');
            }
        } finally { if (!controller.signal.aborted) { mutation.current = null; setBusy(false); } }
    };
    return <>
        <button type="button" aria-label={`Evaluation notifications${!loading && !error && inbox.unread ? `, ${inbox.unread} unread` : ''}`}
            aria-haspopup="dialog" aria-expanded={show} onClick={() => { setShow(true); load(1); }}
            className="relative rounded-full p-2 text-white hover:bg-green-600 focus-visible:outline focus-visible:outline-2 focus-visible:outline-white">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-5 w-5"><path strokeLinecap="round" strokeLinejoin="round" d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4" /></svg>
            {!loading && !error && inbox.unread > 0 && <span aria-hidden="true" className="absolute -right-1 -top-1 rounded-full bg-yellow-300 px-1.5 text-[10px] font-bold text-green-950">{inbox.unread > 99 ? '99+' : inbox.unread}</span>}
        </button>
        <Modal show={show} onClose={() => setShow(false)} maxWidth="lg">
            <div className="max-h-[85vh] overflow-y-auto p-5" aria-busy={loading || busy}>
                <div className="flex items-start justify-between gap-3">
                    <DialogTitle className="text-lg font-bold text-gray-900">Evaluation Notifications</DialogTitle>
                    <button type="button" aria-label="Close notifications" onClick={() => setShow(false)} className="rounded px-2 text-gray-600 focus-visible:outline focus-visible:outline-green-600">✕</button>
                </div>
                <p className="mt-2 text-xs text-gray-600">These are delayed aggregate notices, not instant alerts for each submission. Faculty and Office activity is released only after official review period closure and at least five distinct students in the notification group. Staff evaluations are separate. No submission times or student details are shown.</p>
                {loading && <p role="status" className="mt-4 text-sm text-gray-600">Loading notifications…</p>}
                {error && <p role="alert" className="mt-4 text-sm text-red-700">{error}</p>}
                {!loading && !error && <>
                    <p className="mt-4 text-xs font-semibold text-green-800">{inbox.unread} unread notification groups</p>
                    {!inbox.items.length && <><p role="status" className="mt-4 text-sm text-gray-600">No released notifications are available.</p><p className="mt-2 text-xs text-gray-500">Open periods and groups below five distinct students are not listed.</p></>}
                    <ul className="mt-3 space-y-3">{inbox.items.map(item => <li key={item.id} className={`rounded-lg border p-4 ${item.read ? 'border-gray-200' : 'border-green-300 bg-green-50'}`}>
                        <h3 className="text-sm font-bold text-gray-900">{item.title} <span className="text-xs font-normal">({item.read ? 'Read' : 'Unread'})</span></h3>
                        <p className="mt-1 break-words text-sm text-gray-700">{item.subject}</p>
                        <p className="mt-1 text-xs font-semibold text-green-800">{item.period}</p>
                        <p className="mt-2 text-xs text-gray-600">{item.message}</p>
                        <div className="mt-3 flex flex-wrap gap-3">
                            <button type="button" disabled={busy} onClick={() => changeRead(item, true, true)} className="rounded bg-green-700 px-3 py-2 text-xs font-bold text-white disabled:opacity-50">View {item.kind === 'faculty' ? 'Faculty' : 'Office'} report</button>
                            <button type="button" disabled={busy} onClick={() => changeRead(item, !item.read)} aria-label={`Mark ${item.subject} ${item.period} as ${item.read ? 'unread' : 'read'}`} className="rounded border border-green-700 px-3 py-2 text-xs font-semibold text-green-800 disabled:opacity-50">Mark as {item.read ? 'unread' : 'read'}</button>
                        </div>
                    </li>)}</ul>
                    {inbox.lastPage > 1 && <div className="mt-4 flex items-center justify-between text-sm">
                        <button type="button" disabled={busy || inbox.page <= 1} onClick={() => load(inbox.page - 1)} className="rounded p-2 text-green-800 disabled:opacity-40">Previous</button>
                        <span>Page {inbox.page} of {inbox.lastPage}</span>
                        <button type="button" disabled={busy || inbox.page >= inbox.lastPage} onClick={() => load(inbox.page + 1)} className="rounded p-2 text-green-800 disabled:opacity-40">Next</button>
                    </div>}
                </>}
                <button type="button" disabled={loading || busy} onClick={() => load()} className="mt-4 rounded border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 disabled:opacity-50">Refresh notifications</button>
            </div>
        </Modal>
    </>;
}
