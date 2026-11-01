import { useEffect, useState } from 'react';
import axios from 'axios';
import { startImportPolling } from './importPolling';
import Modal from '@/Components/Modal';
import Spinner from '@/Components/Spinner';

// polls an ImportBatch's progress and shows a bar + percentage until it completes
export default function BatchProgressModal({
    show,
    title = 'Processing...',
    batchId,
    successLabel = 'sent',
    failureLabel = 'failed',
    onDone,
}) {
    const [status, setStatus] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        if (!show || !batchId) {
            setStatus(null);
            return;
        }

        setError('');
        return startImportPolling({fetchStatus:async()=>(await axios.get(route('import-batches.show',batchId),{timeout:15000})).data,onStatus:setStatus,onError:setError});

    }, [show, batchId]);

    const total = status?.totalRows ?? 0;
    const processed = status?.processedRows ?? 0;
    const percent = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : 0;
    const done = ['completed','failed'].includes(status?.status);

    return (
        <Modal show={show} closeable={false} maxWidth="md">
            <div className="p-6">
                {error && <p className="text-xs text-red-600">{error}</p>}
                {status?.message && <p className="text-xs text-red-600">{status.message}</p>}
                <div className="flex items-center justify-center gap-3">
                    {!done && <Spinner className="h-5 w-5 text-green-600" />}
                    <p className="text-sm font-semibold text-gray-700">
                        {done ? 'Done' : title}
                    </p>
                </div>

                <div className="mt-4 h-3 w-full overflow-hidden rounded-full bg-gray-100">
                    <div
                        className="h-full bg-green-600 transition-all"
                        style={{ width: `${percent}%` }}
                    />
                </div>

                <p className="mt-2 text-center text-xs text-gray-500">
                    {percent}% · {processed} / {total} processed
                    {' · '}
                    <span className="text-green-600">{status?.successCount ?? 0} {successLabel}</span>
                    {' · '}
                    <span className="text-red-500">{status?.failureCount ?? 0} {failureLabel}</span>
                </p>

                {status?.errors?.length > 0 && (
                    <div className="mt-3 max-h-40 overflow-y-auto rounded-lg border border-red-100 bg-red-50 p-3">
                        {status.errors.map((err, i) => (
                            <p key={i} className="text-xs text-red-600">
                                {err.row}: {err.message}
                            </p>
                        ))}
                    </div>
                )}

                {done && (
                    <div className="mt-4 flex justify-center">
                        <button
                            type="button"
                            onClick={onDone}
                            className="rounded-md bg-green-700 px-8 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800"
                        >
                            Done
                        </button>
                    </div>
                )}
            </div>
        </Modal>
    );
}
