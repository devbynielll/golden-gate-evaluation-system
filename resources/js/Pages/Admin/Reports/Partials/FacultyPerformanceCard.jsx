import { useState } from 'react';
import DocumentIcon from '@/Components/Icons/DocumentIcon';
import DownloadIcon from '@/Components/Icons/DownloadIcon';
import InboxIcon from '@/Components/Icons/InboxIcon';
import Pagination from '@/Components/Pagination';
import { router } from '@inertiajs/react';
import ReportFilterBar from './ReportFilterBar';
import ReportViewer from './ReportViewer';
import useReport from './useReport';

// faculty performance summary + filters
export default function FacultyPerformanceCard({
    faculty = { data: [], meta: { current_page: 1, last_page: 1, total: 0 } },
    departments = [],
    periods = [],
    selected = { department_id: null, period_id: null },
}) {
    const [department, setDepartment] = useState(selected.department_id ?? '');
    const [periodId, setPeriodId] = useState(selected.period_id ?? null);

    const report = useReport('reports.faculty', selected);

    const visit = (params) =>
        router.get(route('reports.faculty'), params, {
            preserveState: true,
            preserveScroll: true,
            only: ['faculty', 'selectedFilters'],
        });

    const applyFilter = () =>
        visit({ department_id: department || undefined, period_id: periodId || undefined });

    const goToPage = (page) =>
        visit({
            department_id: selected.department_id || undefined,
            period_id: selected.period_id || undefined,
            page,
        });

    const rows = faculty.data ?? [];

    return (
        <div className="space-y-3">
            <ReportFilterBar
                periods={periods}
                periodId={periodId}
                onPeriodChange={setPeriodId}
                onApply={applyFilter}
            >
                <div className="flex items-center gap-2">
                    <span className="text-[10px] font-bold uppercase tracking-wide text-gray-400">
                        Department
                    </span>
                    <select
                        value={department}
                        onChange={(e) => setDepartment(e.target.value)}
                        className="rounded-md border-gray-300 text-xs shadow-sm focus:border-green-600 focus:ring-green-600"
                    >
                        <option value="">All Departments</option>
                        {departments.map((d) => (
                            <option key={d.id} value={d.id}>
                                {d.code}
                            </option>
                        ))}
                    </select>
                </div>
            </ReportFilterBar>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 bg-green-700 px-5 py-3">
                    <span className="text-xs font-bold uppercase tracking-wide text-white">
                        Faculty Performance Summary
                    </span>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="rounded-full bg-white/20 px-3 py-1 text-[11px] font-bold text-white">
                            {faculty.meta?.total ?? rows.length} Faculty Member{(faculty.meta?.total ?? rows.length) === 1 ? '' : 's'}
                        </span>
                        <button type="button" onClick={() => report.open()}
                            className="rounded-full bg-white px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-green-700 hover:bg-green-50">
                            View Summary
                        </button>
                        <button
                            type="button"
                            onClick={() => report.open(null, true)}
                            className="flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-green-700 shadow-sm transition hover:bg-green-50"
                        >
                            <DocumentIcon className="h-3.5 w-3.5" />
                            Generate Full Report
                        </button>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-gray-100 bg-gray-50">
                                <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                    Faculty Member
                                </th>
                                <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                    Department
                                </th>
                                <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                    Overall Rating (Max 4.00)
                                </th>
                                <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                    Responses
                                </th>
                                <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                    Status
                                </th>
                                <th className="px-5 py-2.5 text-right text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="px-5 py-16">
                                        <div className="flex flex-col items-center gap-2 text-gray-300">
                                            <InboxIcon className="h-8 w-8" />
                                            <p className="text-xs text-gray-400">
                                                No report generated yet.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                rows.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="border-b border-gray-50 last:border-0"
                                    >
                                        <td className="px-5 py-3">
                                            <p className="font-semibold text-gray-800">
                                                {row.name}
                                            </p>
                                            <p className="text-xs text-gray-400">
                                                {row.facultyId}
                                            </p>
                                        </td>
                                        <td className="px-5 py-3">
                                            <span className="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600">
                                                {row.department ?? '—'}
                                            </span>
                                        </td>
                                        <td className="px-5 py-3 font-bold text-green-600">
                                            {row.rating.toFixed(2)}
                                        </td>
                                        <td className="px-5 py-3 text-gray-700">
                                            {row.responses}
                                        </td>
                                        <td className="px-5 py-3">
                                            <span className="rounded-full bg-green-100 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-green-700">
                                                {row.status}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-5 py-3 text-right">
                                            <button type="button" onClick={() => report.open(row.id)}
                                                aria-label={`View report for ${row.name}`}
                                                className="mr-2 inline-flex rounded-md border border-green-700 px-3 py-1.5 text-xs font-bold text-green-700 hover:bg-green-50">
                                                View
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => report.open(row.id, true)}
                                                className="inline-flex items-center gap-1.5 rounded-md bg-green-700 px-3 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-green-800"
                                            >
                                                <DownloadIcon className="h-3.5 w-3.5" />
                                                Download
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination
                    page={faculty.meta?.current_page ?? 1}
                    totalPages={faculty.meta?.last_page ?? 1}
                    onPageChange={goToPage}
                />
            </div>
            <ReportViewer show={report.show} loading={report.loading} error={report.error} report={report.report}
                onClose={report.close} onDownload={report.downloadCurrent} />
        </div>
    );
}
