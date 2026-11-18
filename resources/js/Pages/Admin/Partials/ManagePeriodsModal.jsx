import { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import CalendarIcon from '@/Components/Icons/CalendarIcon';
import InboxIcon from '@/Components/Icons/InboxIcon';
import PlusIcon from '@/Components/Icons/PlusIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import XIcon from '@/Components/Icons/XIcon';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal';
import Modal from '@/Components/Modal';

const TABLE_COLUMNS = ['Period Name', 'Duration', 'Status', 'Action'];

// e.g. "2026-01-12" -> "January 12"
const formatMonthDay = (isoDate) =>
    new Date(`${isoDate}T00:00:00`).toLocaleDateString('en-US', {
        month: 'long',
        day: 'numeric',
    });

const formatDuration = (start, end) => {
    // Legacy/partial Inertia payloads must not crash the entire dashboard.
    if (typeof start !== 'string' || typeof end !== 'string'
        || !/^\d{4}-\d{2}-\d{2}/.test(start) || !/^\d{4}-\d{2}-\d{2}/.test(end)) return 'Dates unavailable';
    start = start.slice(0, 10);
    end = end.slice(0, 10);
    const startYear = start.slice(0, 4);
    const endYear = end.slice(0, 4);
    const startLabel = formatMonthDay(start);
    const endLabel = formatMonthDay(end);

    return startYear === endYear
        ? `${startLabel} - ${endLabel}, ${endYear}`
        : `${startLabel}, ${startYear} - ${endLabel}, ${endYear}`;
};

// manage periods + set active modal
export default function ManagePeriodsModal({ show, onClose, periods = [] }) {
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        academic_year: '',
        semester: '',
        start_date: '',
        end_date: '',
    });
    const [pendingDeleteId, setPendingDeleteId] = useState(null);
    const [editingPeriod, setEditingPeriod] = useState(null);
    const [confirmingSave, setConfirmingSave] = useState(false);
    const identityLocked = Boolean(editingPeriod?.evaluations_exists || editingPeriod?.staff_evaluations_exists);
    const currentYear = new Date().getFullYear();
    // Roll forward automatically and retain exact historical labels without rewriting them.
    const academicYears = [...new Set([
        ...Array.from({ length: 8 }, (_, index) => {
            const year = currentYear - 2 + index;
            return `${year}-${year + 1}`;
        }),
        ...periods.map((period) => period.academic_year).filter(Boolean),
    ])].sort();

    const setField = (field) => (e) => setData(field, e.target.value);

    const cancelEdit = () => {
        setEditingPeriod(null);
        setConfirmingSave(false);
        reset();
        clearErrors();
    };

    const editPeriod = (period) => {
        if (processing) return;
        clearErrors();
        setEditingPeriod(period);
        setData({
            academic_year: period.academic_year,
            semester: period.semester,
            start_date: period.start_date?.slice(0, 10) ?? '',
            end_date: period.end_date?.slice(0, 10) ?? '',
        });
    };

    const saveChanges = () => {
        if (processing) return;
        setConfirmingSave(false);
        put(route('review-periods.update', editingPeriod.id), {
            preserveScroll: true,
            onSuccess: cancelEdit,
        });
    };

    const addPeriod = (e) => {
        e.preventDefault();
        if (processing) return;
        if (editingPeriod) {
            setConfirmingSave(true);
            return;
        }
        post(route('review-periods.store'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const setActive = (id) => {
        router.patch(route('review-periods.activate', id), {}, { preserveScroll: true });
    };

    const confirmDelete = () => {
        router.delete(route('review-periods.destroy', pendingDeleteId), {
            preserveScroll: true,
            onFinish: () => setPendingDeleteId(null),
        });
    };

    const close = () => {
        if (processing) return;
        cancelEdit();
        onClose();
    };

    return (
        <Modal show={show} onClose={close} maxWidth="4xl">
            <div className="p-6">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-extrabold uppercase tracking-wide text-gray-900">
                        Manage Periods
                    </h2>
                    <button
                        type="button"
                        onClick={close}
                        className="text-gray-400 transition hover:text-gray-600"
                    >
                        <XIcon className="h-5 w-5" />
                    </button>
                </div>

                <div className="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <p className="text-xs font-extrabold uppercase tracking-wide text-green-700">
                        {editingPeriod ? 'Edit Review Period' : 'Create New Period'}
                    </p>
                    {editingPeriod && (
                        <p className="mt-2 text-xs text-gray-600">
                            {identityLocked && 'Academic Year and Semester are locked because this period has linked evaluations. '}
                            Date changes do not open or close evaluations. Reopening remains a separate action.
                        </p>
                    )}

                    <form
                        onSubmit={addPeriod}
                        className="mt-3 grid grid-cols-1 items-end gap-3 sm:grid-cols-2 lg:grid-cols-[1.2fr_1fr_1fr_1fr_auto]"
                    >
                        <div>
                            <label htmlFor="academic_year" className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                Academic Year
                            </label>
                            <select
                                id="academic_year"
                                disabled={identityLocked || processing}
                                value={data.academic_year}
                                onChange={setField('academic_year')}
                                className="mt-1 block w-full rounded-md border-gray-300 text-sm text-gray-600 shadow-sm focus:border-green-600 focus:ring-green-600"
                            >
                                <option value="">Choose Academic Year</option>
                                {academicYears.map((year) => (
                                    <option key={year} value={year}>{year}</option>
                                ))}
                            </select>
                            {errors.academic_year && (
                                <p className="mt-1 text-xs text-red-500">{errors.academic_year}</p>
                            )}
                        </div>

                        <div>
                            <label className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                Semester
                            </label>
                            <select
                                aria-label="Semester"
                                disabled={identityLocked || processing}
                                value={data.semester}
                                onChange={setField('semester')}
                                className="mt-1 block w-full rounded-md border-gray-300 text-sm text-gray-600 shadow-sm focus:border-green-600 focus:ring-green-600"
                            >
                                <option value="">Choose Semester</option>
                                <option value="1st Semester">
                                    1st Semester
                                </option>
                                <option value="2nd Semester">
                                    2nd Semester
                                </option>
                                <option value="Summer">Summer</option>
                            </select>
                            {errors.semester && (
                                <p className="mt-1 text-xs text-red-500">{errors.semester}</p>
                            )}
                        </div>

                        <div>
                            <label className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                Start
                            </label>
                            <div className="relative mt-1">
                                <input
                                    type="date"
                                    aria-label="Start Date"
                                    disabled={processing}
                                    value={data.start_date}
                                    onChange={setField('start_date')}
                                    className="block w-full rounded-md border-gray-300 py-2 pl-3 pr-9 text-sm text-gray-600 shadow-sm focus:border-green-600 focus:ring-green-600"
                                />
                                <CalendarIcon className="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            </div>
                            {errors.start_date && (
                                <p className="mt-1 text-xs text-red-500">{errors.start_date}</p>
                            )}
                        </div>

                        <div>
                            <label className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                End
                            </label>
                            <div className="relative mt-1">
                                <input
                                    type="date"
                                    aria-label="End Date"
                                    disabled={processing}
                                    value={data.end_date}
                                    onChange={setField('end_date')}
                                    className="block w-full rounded-md border-gray-300 py-2 pl-3 pr-9 text-sm text-gray-600 shadow-sm focus:border-green-600 focus:ring-green-600"
                                />
                                <CalendarIcon className="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            </div>
                            {errors.end_date && (
                                <p className="mt-1 text-xs text-red-500">{errors.end_date}</p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="flex items-center justify-center gap-1.5 rounded-md bg-green-700 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800 disabled:opacity-60"
                        >
                            {editingPeriod ? <PencilIcon className="h-3.5 w-3.5" /> : <PlusIcon className="h-3.5 w-3.5" />}
                            {editingPeriod ? 'Save' : 'Add'}
                        </button>
                    </form>
                    {editingPeriod && (
                        <button type="button" disabled={processing} onClick={cancelEdit}
                            className="mt-3 text-xs font-semibold text-gray-500 hover:text-gray-800 disabled:opacity-50">
                            Cancel editing
                        </button>
                    )}
                </div>

                <div className="mt-5 overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-gray-200">
                                {TABLE_COLUMNS.map((column) => (
                                    <th
                                        key={column}
                                        className="pb-2 text-[11px] font-bold uppercase tracking-wide text-gray-400"
                                    >
                                        {column}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {periods.length === 0 ? (
                                <tr>
                                    <td colSpan={TABLE_COLUMNS.length}>
                                        <div className="flex flex-col items-center justify-center gap-2 py-12 text-gray-300">
                                            <InboxIcon className="h-8 w-8" />
                                            <p className="text-xs text-gray-400">
                                                There are no periods
                                                available to review.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                periods.map((period) => (
                                    <tr
                                        key={period.id}
                                        className="border-b border-gray-100"
                                    >
                                        <td className="whitespace-nowrap py-3 text-sm">
                                            <span className="font-bold text-gray-800">
                                                {period.semester}
                                            </span>{' '}
                                            <span className="text-gray-400">
                                                {period.academic_year}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap py-3 text-sm text-gray-500">
                                            {formatDuration(period.start_date, period.end_date)}
                                        </td>
                                        <td className="py-3">
                                            {period.is_active ? (
                                                <span className="rounded-full bg-green-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-green-700">
                                                    Active
                                                </span>
                                            ) : (
                                                <button
                                                    type="button"
                                                    onClick={() => setActive(period.id)}
                                                    className="whitespace-nowrap rounded-full border border-green-600 px-3 py-1 text-xs font-bold uppercase tracking-wide text-green-700 transition hover:bg-green-50"
                                                >
                                                    Set Active
                                                </button>
                                            )}
                                        </td>
                                        <td className="py-3">
                                            <div className="flex items-center gap-3">
                                                <button type="button" disabled={processing} onClick={() => editPeriod(period)}
                                                    className="inline-flex items-center gap-1 text-xs font-semibold text-green-700 hover:text-green-800 disabled:opacity-50"
                                                    aria-label={`Edit ${period.academic_year} ${period.semester}`}>
                                                    <PencilIcon className="h-4 w-4" />
                                                    Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setPendingDeleteId(
                                                            period.id,
                                                        )
                                                    }
                                                    className="text-gray-400 transition hover:text-red-500"
                                                    aria-label="Remove period"
                                                >
                                                    <TrashIcon className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <ConfirmDeleteModal
                show={confirmingSave}
                title="Save period changes?"
                description={editingPeriod ? `Change ${editingPeriod.academic_year} ${editingPeriod.semester} (${editingPeriod.start_date?.slice(0, 10)} to ${editingPeriod.end_date?.slice(0, 10)}) to ${data.academic_year} ${data.semester} (${data.start_date} to ${data.end_date})? Submission status will not change. Closed evaluations stay closed.` : ''}
                confirmLabel="Save changes"
                onCancel={() => setConfirmingSave(false)}
                onConfirm={saveChanges}
            />
            <ConfirmDeleteModal
                show={pendingDeleteId !== null}
                title="Delete Period?"
                onCancel={() => setPendingDeleteId(null)}
                onConfirm={confirmDelete}
            />
        </Modal>
    );
}
