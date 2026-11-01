import { useRef, useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import Modal from '@/Components/Modal';
import Spinner from '@/Components/Spinner';
import XIcon from '@/Components/Icons/XIcon';

const columnLetter = (index) => {
    let n = index;
    let letters = '';
    do {
        letters = String.fromCharCode(65 + (n % 26)) + letters;
        n = Math.floor(n / 26) - 1;
    } while (n >= 0);
    return letters;
};

// The normal Student roster workflow never sends subject-enrollment mappings or update flags.
export const importRequestPayload = ({ token, mapping, startRow, updateExisting, extraData = {}, studentRosterOnly = false }) => ({
    token,
    mapping: studentRosterOnly
        ? Object.fromEntries(Object.entries(mapping).filter(([key]) => ['studentId', 'email', 'firstName', 'middleName', 'lastName', 'suffix', 'year', 'block', 'isIrregular'].includes(key)))
        : mapping,
    startRow,
    ...(!studentRosterOnly && { updateExisting }),
    ...extraData,
});

// upload -> map -> progress
export default function ImportModal({
    show,
    title = 'Import',
    previewUrl,
    importUrl,
    validateUrl = null,
    updateExistingLabel = null,
    fields = [],
    extraData = {},
    fileLabel = 'Excel / CSV / Word File',
    accept = '.xlsx,.xls,.csv,.doc,.docx',
    helpText = null,
    studentRosterOnly = false,
    onClose,
}) {
    const [step, setStep] = useState('upload');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const fileRef = useRef(null);

    const [headerRow, setHeaderRow] = useState(1);
    const [noHeader, setNoHeader] = useState(false);

    const [token, setToken] = useState(null);
    const [totalRows, setTotalRows] = useState(0);
    const [previewRows, setPreviewRows] = useState([]);
    const [startRow, setStartRow] = useState(2);
    const [mapping, setMapping] = useState({});
    const [updateExisting, setUpdateExisting] = useState(false);
    const [review, setReview] = useState(null);
    const [reviewFilter, setReviewFilter] = useState('all');

    const [batchId, setBatchId] = useState(null);
    const [status, setStatus] = useState(null);

    const reset = () => {
        setStep('upload');
        setBusy(false);
        setError('');
        setHeaderRow(1);
        setNoHeader(false);
        setToken(null);
        setTotalRows(0);
        setPreviewRows([]);
        setStartRow(2);
        setMapping({});
        setUpdateExisting(false);
        setReview(null);
        setReviewFilter('all');
        setBatchId(null);
        setStatus(null);
        if (fileRef.current) fileRef.current.value = '';
    };

    const close = () => {
        reset();
        onClose();
    };

    const columnCount = previewRows.reduce((max, row) => Math.max(max, row.length), 0);

    const columnLabel = (index) => {
        if (!noHeader) {
            const header = previewRows[headerRow - 1]?.[index];
            if (header) return header;
        }
        return `Column ${columnLetter(index)}`;
    };

    const uploadFile = async (e) => {
        e.preventDefault();
        const file = fileRef.current?.files?.[0];
        if (!file) return;

        setBusy(true);
        setError('');
        try {
            const form = new FormData();
            form.append('file', file);
            Object.entries(extraData).forEach(([key, value]) => { if (value != null) form.append(key, value); });
            const { data } = await axios.post(previewUrl, form);
            setToken(data.token);
            setTotalRows(data.totalRows);
            setPreviewRows(data.previewRows);
            setStartRow(noHeader ? headerRow : headerRow + 1);
            setStep('map');
        } catch (err) {
            setError(err.response?.data?.message ?? 'Could not read that file.');
        } finally {
            setBusy(false);
        }
    };

    const missingRequired = fields.filter((f) => f.required && mapping[f.key] === undefined);

    const runReview = async () => {
        setBusy(true);
        setError('');
        try {
            const { data } = await axios.post(validateUrl, importRequestPayload({ token, mapping, startRow, updateExisting, extraData, studentRosterOnly }));
            setReview(data);
            setReviewFilter('all');
            setStep('review');
        } catch (err) {
            setError(err.response?.data?.message ?? 'Could not check the file.');
        } finally {
            setBusy(false);
        }
    };

    const submitMapping = async () => {
        setBusy(true);
        setError('');
        try {
            const { data } = await axios.post(importUrl, importRequestPayload({ token, mapping, startRow, updateExisting, extraData, studentRosterOnly }));
            setBatchId(data.batchId);
            setStep('progress');
            poll(data.batchId);
        } catch (err) {
            setError(err.response?.data?.message ?? 'Could not start the import.');
        } finally {
            setBusy(false);
        }
    };

    const poll = (id) => {
        const tick = async () => {
            const { data } = await axios.get(route('import-batches.show', id));
            setStatus(data);
            if (data.status !== 'completed') {
                setTimeout(tick, 1200);
            }
        };
        tick();
    };

    const finish = () => {
        close();
        router.reload();
    };

    const sampleRows = previewRows.slice(startRow - 1, startRow + 2);

    return (
        <Modal show={show} onClose={close} maxWidth="3xl">
            <div className="p-6">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-extrabold uppercase tracking-wide text-green-700">
                        {title}
                    </h2>
                    <button type="button" onClick={close} className="text-gray-400 hover:text-gray-600">
                        <XIcon className="h-5 w-5" />
                    </button>
                </div>

                {helpText && <p className="mt-3 text-xs text-gray-500">{helpText}</p>}

                {error && (
                    <p className="mt-3 rounded-md bg-red-50 px-3 py-2 text-xs text-red-600">{error}</p>
                )}

                {step === 'upload' && (
                    <form onSubmit={uploadFile} className="mt-4 space-y-4">
                        <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <label className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                {fileLabel}
                            </label>
                            <input
                                ref={fileRef}
                                type="file"
                                accept={accept}
                                required
                                className="mt-1 block w-full text-sm"
                            />
                            {accept.includes('.doc') && (
                                <p className="mt-1 text-xs text-gray-400">
                                    Word files are parsed automatically — a table in the
                                    document is detected, or columns are inferred from
                                    its text.
                                </p>
                            )}

                            <div className="mt-4 flex flex-wrap items-center gap-4">
                                <label className="flex items-center gap-2 text-sm text-gray-600">
                                    <input
                                        type="checkbox"
                                        checked={noHeader}
                                        onChange={(e) => setNoHeader(e.target.checked)}
                                    />
                                    This file has no header row
                                </label>

                                {!noHeader && (
                                    <label className="flex items-center gap-2 text-sm text-gray-600">
                                        Header row
                                        <input
                                            type="number"
                                            min={1}
                                            value={headerRow}
                                            onChange={(e) => setHeaderRow(Number(e.target.value) || 1)}
                                            className="w-20 rounded-md border-gray-300 text-sm shadow-sm focus:border-green-600 focus:ring-green-600"
                                        />
                                    </label>
                                )}
                            </div>
                        </div>

                        <div className="flex justify-center">
                            <button
                                type="submit"
                                disabled={busy}
                                className="flex items-center gap-2 rounded-md bg-green-700 px-8 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {busy && <Spinner className="h-4 w-4" />}
                                {busy ? 'Reading file...' : 'Preview'}
                            </button>
                        </div>
                    </form>
                )}

                {step === 'map' && (
                    <div className="mt-4 space-y-4">
                        <p className="text-xs text-gray-500">
                            {totalRows} row{totalRows === 1 ? '' : 's'} detected. Map each field to a
                            column and confirm where the data starts.
                        </p>

                        <label className="flex items-center gap-2 text-sm text-gray-600">
                            Data starts at row
                            <input
                                type="number"
                                min={1}
                                value={startRow}
                                onChange={(e) => setStartRow(Number(e.target.value) || 1)}
                                className="w-20 rounded-md border-gray-300 text-sm shadow-sm focus:border-green-600 focus:ring-green-600"
                            />
                        </label>

                        <div className="max-h-64 overflow-y-auto rounded-lg border border-gray-100">
                            <table className="w-full text-left text-sm">
                                <tbody>
                                    {fields.map((field) => (
                                        <tr key={field.key} className="border-b border-gray-50 last:border-0">
                                            <td className="px-4 py-2 font-semibold text-gray-700">
                                                {field.label}
                                                {field.required && <span className="text-red-500"> *</span>}
                                            </td>
                                            <td className="px-4 py-2">
                                                <select
                                                    value={mapping[field.key] ?? ''}
                                                    onChange={(e) =>
                                                        setMapping((prev) => ({
                                                            ...prev,
                                                            [field.key]:
                                                                e.target.value === ''
                                                                    ? undefined
                                                                    : Number(e.target.value),
                                                        }))
                                                    }
                                                    className="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-green-600 focus:ring-green-600"
                                                >
                                                    <option value="">Not mapped</option>
                                                    {Array.from({ length: columnCount }).map((_, index) => (
                                                        <option key={index} value={index}>
                                                            {columnLabel(index)}
                                                        </option>
                                                    ))}
                                                </select>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {!studentRosterOnly && updateExistingLabel && (
                            <label className="flex items-start gap-2 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600">
                                <input
                                    type="checkbox"
                                    className="mt-0.5"
                                    checked={updateExisting}
                                    onChange={(e) => setUpdateExisting(e.target.checked)}
                                />
                                <span>{updateExistingLabel}</span>
                            </label>
                        )}

                        {sampleRows.length > 0 && (
                            <div>
                                <p className="text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                    Preview of data rows
                                </p>
                                <div className="mt-1 overflow-x-auto rounded-lg border border-gray-100">
                                    <table className="w-full text-left text-xs">
                                        <tbody>
                                            {sampleRows.map((row, i) => (
                                                <tr key={i} className="border-b border-gray-50 last:border-0">
                                                    {row.map((cell, j) => (
                                                        <td key={j} className="px-3 py-1.5 text-gray-600">
                                                            {cell}
                                                        </td>
                                                    ))}
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}

                        <div className="flex justify-center gap-3">
                            <button
                                type="button"
                                onClick={() => setStep('upload')}
                                className="rounded-md border border-gray-300 px-6 py-2.5 text-sm font-bold uppercase tracking-wide text-gray-600 hover:bg-gray-50"
                            >
                                Back
                            </button>
                            <button
                                type="button"
                                onClick={validateUrl ? runReview : submitMapping}
                                disabled={busy || missingRequired.length > 0}
                                title={missingRequired.length ? `Map: ${missingRequired.map((f) => f.label).join(', ')}` : undefined}
                                className="flex items-center gap-2 rounded-md bg-green-700 px-8 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {busy && <Spinner className="h-4 w-4" />}
                                {validateUrl ? (busy ? 'Checking...' : 'Review') : busy ? 'Starting...' : 'Import'}
                            </button>
                        </div>
                    </div>
                )}

                {step === 'review' && review && (
                    <div className="mt-4 space-y-4">
                        <p className="text-xs text-gray-500">
                            Nothing has been saved yet. Review what will happen, then confirm.
                        </p>
                        {!studentRosterOnly && review.term !== undefined && (
                            <p className={`rounded-md px-3 py-2 text-xs ${review.term ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-600'}`}>
                                {review.term
                                    ? `Subject enrollments will be recorded for ${review.term} (the active term).`
                                    : 'No active review period. Activate a term before importing subject enrollments.'}
                            </p>
                        )}

                        <div className={`grid grid-cols-2 gap-2 text-center ${studentRosterOnly ? 'sm:grid-cols-3' : 'sm:grid-cols-5'}`}>
                            {[
                                ['create', 'New students', review.summary.create, 'text-green-700'],
                                ...(!studentRosterOnly ? [['enroll', 'Existing, subjects added', review.summary.enroll, 'text-blue-700']] : []),
                                ['skip', 'Skipped', review.summary.skip, 'text-amber-600'],
                                ['error', 'Errors', review.summary.error, 'text-red-600'],
                            ].map(([key, label, value, color]) => (
                                <button
                                    key={key}
                                    type="button"
                                    onClick={() => setReviewFilter(reviewFilter === key ? 'all' : key)}
                                    className={`rounded-lg border p-2 ${reviewFilter === key ? 'border-green-600 bg-green-50' : 'border-gray-200'}`}
                                >
                                    <p className={`text-xl font-extrabold ${color}`}>{value}</p>
                                    <p className="text-[10px] font-bold uppercase tracking-wide text-gray-400">{label}</p>
                                </button>
                            ))}
                            {!studentRosterOnly && (
                                <div className="rounded-lg border border-gray-200 p-2">
                                    <p className="text-xl font-extrabold text-gray-700">{review.summary.subjectsToAdd}</p>
                                    <p className="text-[10px] font-bold uppercase tracking-wide text-gray-400">Enrollments</p>
                                </div>
                            )}
                        </div>

                        <div className="max-h-72 overflow-y-auto rounded-lg border border-gray-100">
                            <table className="w-full text-left text-xs">
                                <thead className="sticky top-0 bg-gray-50 text-[10px] uppercase tracking-wide text-gray-400">
                                    <tr>
                                        <th className="px-3 py-2">Row</th>
                                        <th className="px-3 py-2">Student</th>
                                        <th className="px-3 py-2">Result</th>
                                        <th className="px-3 py-2">{studentRosterOnly ? 'Notes' : 'Subjects / notes'}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {review.rows
                                        .filter((r) => reviewFilter === 'all' || r.action === reviewFilter)
                                        .map((r, i) => (
                                            <tr key={i} className="border-t border-gray-50 align-top">
                                                <td className="px-3 py-1.5 text-gray-500">{r.rows.join(', ')}</td>
                                                <td className="px-3 py-1.5 text-gray-700">
                                                    <span className="font-semibold">{r.studentId || '—'}</span>
                                                    <br />
                                                    {r.name}
                                                </td>
                                                <td className="px-3 py-1.5 font-semibold">
                                                    {{ create: 'Add student', enroll: 'Add subjects', skip: 'Skip', error: 'Error' }[r.action]}
                                                </td>
                                                <td className="px-3 py-1.5">
                                                    {!studentRosterOnly && r.subjects.length > 0 && (
                                                        <p className="text-gray-700">{r.subjects.join(', ')}</p>
                                                    )}
                                                    {r.messages.map((m, j) => (
                                                        <p key={j} className={r.action === 'error' ? 'text-red-600' : 'text-amber-600'}>{m}</p>
                                                    ))}
                                                    {r.warnings.map((m, j) => (
                                                        <p key={`w${j}`} className="text-amber-600">{m}</p>
                                                    ))}
                                                </td>
                                            </tr>
                                        ))}
                                </tbody>
                            </table>
                        </div>
                        {review.truncated && (
                            <p className="text-xs text-gray-400">
                                Showing the first {review.rows.length} students (errors and skips first). The counts cover the whole file.
                            </p>
                        )}

                        <div className="flex justify-center gap-3">
                            <button
                                type="button"
                                onClick={() => setStep('map')}
                                className="rounded-md border border-gray-300 px-6 py-2.5 text-sm font-bold uppercase tracking-wide text-gray-600 hover:bg-gray-50"
                            >
                                Back
                            </button>
                            <button
                                type="button"
                                onClick={submitMapping}
                                disabled={busy || review.summary.create + (studentRosterOnly ? 0 : review.summary.enroll) === 0}
                                className="flex items-center gap-2 rounded-md bg-green-700 px-8 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {busy && <Spinner className="h-4 w-4" />}
                                {busy ? 'Starting...' : `Confirm import${review.summary.error ? ' (errors are skipped)' : ''}`}
                            </button>
                        </div>
                    </div>
                )}

                {step === 'progress' && (
                    <div className="mt-6 space-y-4">
                        <div className="flex items-center justify-center gap-3">
                            {status?.status !== 'completed' && <Spinner className="h-5 w-5 text-green-600" />}
                            <p className="text-sm font-semibold text-gray-700">
                                {status?.status === 'completed' ? 'Import complete' : 'Importing...'}
                            </p>
                        </div>

                        <div className="h-3 w-full overflow-hidden rounded-full bg-gray-100">
                            <div
                                className="h-full bg-green-600 transition-all"
                                style={{
                                    width: `${status && status.totalRows ? Math.min(100, (status.processedRows / status.totalRows) * 100) : 0}%`,
                                }}
                            />
                        </div>

                        <p className="text-center text-xs text-gray-500">
                            {status?.processedRows ?? 0} / {status?.totalRows ?? totalRows} rows processed
                            {' · '}
                            <span className="text-green-600">{status?.successCount ?? 0} imported</span>
                            {!studentRosterOnly && status?.enrollmentsCount > 0 && (
                                <>
                                    {' · '}
                                    <span className="text-blue-600">{status.enrollmentsCount} subject enrollments</span>
                                </>
                            )}
                            {' · '}
                            <span className="text-amber-600">{status?.skippedCount ?? 0} skipped</span>
                            {' · '}
                            <span className="text-red-500">{status?.failureCount ?? 0} failed</span>
                        </p>

                        {status?.skipped?.length > 0 && (
                            <div className="max-h-32 overflow-y-auto rounded-lg border border-amber-100 bg-amber-50 p-3">
                                {status.skipped.map((s, i) => (
                                    <p key={i} className="text-xs text-amber-700">
                                        Row {s.row}: {s.message}
                                    </p>
                                ))}
                            </div>
                        )}

                        {status?.errors?.length > 0 && (
                            <div className="max-h-40 overflow-y-auto rounded-lg border border-red-100 bg-red-50 p-3">
                                {status.errors.map((err, i) => (
                                    <p key={i} className="text-xs text-red-600">
                                        Row {err.row}: {err.message}
                                    </p>
                                ))}
                            </div>
                        )}

                        {status?.status === 'completed' && (
                            <div className="flex justify-center">
                                <button
                                    type="button"
                                    onClick={finish}
                                    className="rounded-md bg-green-700 px-8 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800"
                                >
                                    Done
                                </button>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </Modal>
    );
}
