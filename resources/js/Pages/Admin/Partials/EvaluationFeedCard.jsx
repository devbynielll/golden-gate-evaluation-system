import { useState } from 'react';
import axios from 'axios';
import { router } from '@inertiajs/react';
import ChevronDownIcon from '@/Components/Icons/ChevronDownIcon';
import FilterIcon from '@/Components/Icons/FilterIcon';
import InboxIcon from '@/Components/Icons/InboxIcon';
import MailIcon from '@/Components/MailIcon';
import Pagination from '@/Components/Pagination';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal';
import BatchProgressModal from '@/Components/BatchProgressModal';
import Spinner from '@/Components/Spinner';

const TABLE_COLUMNS = [
    'Student Name',
    'Submission Details',
    'Status',
    'Actions',
];

const STATUS_STYLES = {
    completed: {
        badge: 'bg-green-100 text-green-700',
        label: 'Completed',
        action: 'bg-gray-100 text-gray-400',
    },
    pending: {
        badge: 'bg-red-100 text-red-500',
        label: 'Pending',
        action: 'bg-red-500 text-white hover:bg-red-600',
    },
};

// evaluation feed, filterable by department/program
export default function EvaluationFeedCard({
    evaluations = { data: [], meta: { current_page: 1, last_page: 1 } },
    filterOptions = { departments: [], programs: [] },
    selectedFilters = { department_id: null, program_id: null },
}) {
    const [department, setDepartment] = useState(
        selectedFilters.department_id ?? '',
    );
    const [program, setProgram] = useState(selectedFilters.program_id ?? '');
    const [loading, setLoading] = useState(false);
    const [sendingKey, setSendingKey] = useState(null);
    const [pendingReminder, setPendingReminder] = useState(null); // { studentId, type, key, studentName, label }
    const [confirmBulkRemind, setConfirmBulkRemind] = useState(false);
    const [preparingBulk, setPreparingBulk] = useState(false);
    const [bulkBatchId, setBulkBatchId] = useState(null);

    const sendAllReminders = async () => {
        setConfirmBulkRemind(false);
        setPreparingBulk(true);
        try {
            const { data } = await axios.post(route('evaluations.remind-all'), {
                department_id: selectedFilters.department_id || undefined,
                program_id: selectedFilters.program_id || undefined,
            });
            setBulkBatchId(data.batchId);
        } finally {
            setPreparingBulk(false);
        }
    };

    const finishBulkRemind = () => {
        setBulkBatchId(null);
        router.reload({ only: ['evaluations'] });
    };

    const askSendReminder = (student, submission, key) => {
        setPendingReminder({
            studentId: student.id,
            type: submission.type,
            key,
            studentName: student.name,
            label: submission.label,
        });
    };

    const confirmSendReminder = () => {
        const { studentId, type, key } = pendingReminder;
        setPendingReminder(null);
        setSendingKey(key);
        router.post(
            route('students.remind', studentId),
            { type },
            {
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setSendingKey(null),
            },
        );
    };

    const visit = (params) => {
        setLoading(true);
        router.get(route('dashboard'), params, {
            only: ['evaluations', 'selectedFilters'],
            preserveState: true,
            preserveScroll: true,
            onFinish: () => setLoading(false),
        });
    };

    const applyFilter = () => {
        visit({ department_id: department || undefined, program_id: program || undefined });
    };

    const goToPage = (page) => {
        visit({
            department_id: selectedFilters.department_id || undefined,
            program_id: selectedFilters.program_id || undefined,
            page,
        });
    };

    const programsInDepartment = department
        ? filterOptions.programs.filter(
              (p) => String(p.department_id) === String(department),
          )
        : filterOptions.programs;

    const rows = evaluations.data ?? [];

    return (
        <div className="overflow-hidden rounded-xl bg-white shadow-sm">
            <div className="bg-green-700 px-5 py-3">
                <h3 className="text-sm font-extrabold uppercase tracking-wide text-white">
                    Evaluation Feed
                </h3>
            </div>

            <div className="flex flex-wrap items-center gap-3 border-b border-gray-100 px-5 py-3">
                <span className="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-gray-500">
                    <FilterIcon className="h-4 w-4" />
                    Filter Evaluation
                </span>

                <div className="relative">
                    <select
                        value={department}
                        onChange={(e) => {
                            setDepartment(e.target.value);
                            setProgram('');
                        }}
                        className="appearance-none rounded-md border border-gray-200 bg-white py-1.5 pl-3 pr-8 text-xs text-gray-600 focus:border-green-600 focus:ring-green-600"
                    >
                        <option value="">All Departments</option>
                        {filterOptions.departments.map((d) => (
                            <option key={d.id} value={d.id}>
                                {d.code}
                            </option>
                        ))}
                    </select>
                    <ChevronDownIcon className="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                </div>

                <div className="relative">
                    <select
                        value={program}
                        onChange={(e) => setProgram(e.target.value)}
                        className="appearance-none rounded-md border border-gray-200 bg-white py-1.5 pl-3 pr-8 text-xs text-gray-600 focus:border-green-600 focus:ring-green-600"
                    >
                        <option value="">Select Program</option>
                        {programsInDepartment.map((p) => (
                            <option key={p.id} value={p.id}>
                                {p.code}
                            </option>
                        ))}
                    </select>
                    <ChevronDownIcon className="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                </div>

                <button
                    type="button"
                    onClick={applyFilter}
                    disabled={loading}
                    className="rounded-full bg-green-600 px-4 py-1.5 text-xs font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-700 disabled:opacity-60"
                >
                    {loading ? 'Loading...' : 'Apply Filter'}
                </button>

                <button
                    type="button"
                    onClick={() => setConfirmBulkRemind(true)}
                    disabled={preparingBulk}
                    className="ms-auto flex items-center gap-2 rounded-full bg-red-500 px-4 py-1.5 text-xs font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-red-600 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {preparingBulk && <Spinner className="h-3.5 w-3.5" />}
                    {preparingBulk ? 'Preparing...' : 'Send All Pending Reminders'}
                </button>
            </div>

            <div className="max-h-[420px] overflow-y-auto overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <thead className="sticky top-0 bg-white">
                        <tr className="border-b border-gray-100">
                            {TABLE_COLUMNS.map((column) => (
                                <th
                                    key={column}
                                    className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400"
                                >
                                    {column}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 ? (
                            <tr>
                                <td colSpan={TABLE_COLUMNS.length}>
                                    <div className="flex flex-col items-center justify-center gap-2 py-14 text-gray-300">
                                        <InboxIcon className="h-8 w-8" />
                                        <p className="text-xs text-gray-400">
                                            There are no evaluations
                                            available to review.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        ) : (
                            rows.map((student) =>
                                student.submissions.map(
                                    (submission, index) => {
                                        const style =
                                            STATUS_STYLES[submission.status];

                                        return (
                                            <tr
                                                key={`${student.id}-${index}`}
                                                className="border-b border-gray-50 last:border-0"
                                            >
                                                {index === 0 && (
                                                    <td
                                                        rowSpan={
                                                            student.submissions
                                                                .length
                                                        }
                                                        className="px-5 py-3 align-top"
                                                    >
                                                        <p className="font-bold text-gray-900">
                                                            {student.name}
                                                        </p>
                                                        <p className="text-xs text-gray-400">
                                                            {
                                                                student.studentId
                                                            }
                                                        </p>
                                                    </td>
                                                )}

                                                <td className="px-5 py-3 text-gray-600">
                                                    {submission.label}
                                                </td>

                                                <td className="px-5 py-3">
                                                    <span
                                                        className={`inline-block rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide ${style.badge}`}
                                                    >
                                                        {style.label}
                                                    </span>
                                                </td>

                                                <td className="px-5 py-3">
                                                    <button
                                                        type="button"
                                                        title={
                                                            submission.status ===
                                                            'pending'
                                                                ? 'Send reminder email'
                                                                : 'Submission completed'
                                                        }
                                                        disabled={
                                                            submission.status !==
                                                                'pending' ||
                                                            sendingKey ===
                                                                `${student.id}-${index}`
                                                        }
                                                        onClick={() =>
                                                            askSendReminder(
                                                                student,
                                                                submission,
                                                                `${student.id}-${index}`,
                                                            )
                                                        }
                                                        className={`flex h-8 w-8 items-center justify-center rounded-md transition disabled:cursor-not-allowed disabled:opacity-60 ${style.action}`}
                                                    >
                                                        <MailIcon className="h-4 w-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        );
                                    },
                                ),
                            )
                        )}
                    </tbody>
                </table>
            </div>

            <Pagination
                page={evaluations.meta?.current_page ?? 1}
                totalPages={evaluations.meta?.last_page ?? 1}
                onPageChange={goToPage}
            />

            <ConfirmDeleteModal
                show={pendingReminder !== null}
                title="Send Reminder Email?"
                description={
                    pendingReminder
                        ? `This will email ${pendingReminder.studentName} about their pending evaluation (${pendingReminder.label}).`
                        : ''
                }
                confirmLabel="Yes, send it"
                cancelLabel="Cancel"
                onCancel={() => setPendingReminder(null)}
                onConfirm={confirmSendReminder}
            />

            <ConfirmDeleteModal
                show={confirmBulkRemind}
                title="Send Reminders to Everyone Pending?"
                description="This will email every student in the current filter who still has a pending evaluation."
                confirmLabel="Yes, send it"
                cancelLabel="Cancel"
                onCancel={() => setConfirmBulkRemind(false)}
                onConfirm={sendAllReminders}
            />

            <BatchProgressModal
                show={bulkBatchId !== null}
                title="Sending reminders..."
                batchId={bulkBatchId}
                successLabel="sent"
                failureLabel="failed"
                onDone={finishBulkRemind}
            />
        </div>
    );
}
