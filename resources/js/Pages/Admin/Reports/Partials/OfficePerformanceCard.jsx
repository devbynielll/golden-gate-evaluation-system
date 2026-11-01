import { useState } from 'react';
import DocumentIcon from '@/Components/Icons/DocumentIcon';
import DownloadIcon from '@/Components/Icons/DownloadIcon';
import InboxIcon from '@/Components/Icons/InboxIcon';
import Pagination from '@/Components/Pagination';
import SearchIcon from '@/Components/Icons/SearchIcon';
import { router } from '@inertiajs/react';
import ReportFilterBar from './ReportFilterBar';
import downloadReport from './downloadReport';

// office performance summary + filters
export default function OfficePerformanceCard({
    offices = { data: [], meta: { current_page: 1, last_page: 1, total: 0 } },
    periods = [],
    selected = { search: '', period_id: null },
}) {
    const [search, setSearch] = useState(selected.search ?? '');
    const [periodId, setPeriodId] = useState(selected.period_id ?? null);

    const reviewPeriodLabel = () =>
        periods.find((p) => String(p.id) === String(periodId))?.label ?? 'All Semesters';

    const visit = (params) =>
        router.get(route('reports.office'), params, {
            preserveState: true,
            preserveScroll: true,
            only: ['offices', 'selectedFilters'],
        });

    const applyFilter = () =>
        visit({ search: search || undefined, period_id: periodId || undefined });

    const goToPage = (page) =>
        visit({
            search: selected.search || undefined,
            period_id: selected.period_id || undefined,
            page,
        });

    const rows = offices.data ?? [];

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
                        Search
                    </span>
                    <div className="relative">
                        <SearchIcon className="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search offices..."
                            className="rounded-md border-gray-300 py-1.5 pl-8 pr-3 text-xs shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                        />
                    </div>
                </div>
            </ReportFilterBar>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                <div className="flex items-center justify-between gap-3 bg-green-700 px-5 py-3">
                    <span className="text-xs font-bold uppercase tracking-wide text-white">
                        Office Performance Summary
                    </span>
                    <div className="flex items-center gap-2">
                        <span className="rounded-full bg-white/20 px-3 py-1 text-[11px] font-bold text-white">
                            {offices.meta?.total ?? rows.length} Office{(offices.meta?.total ?? rows.length) === 1 ? '' : 's'}
                        </span>
                        <button
                            type="button"
                            onClick={() =>
                                downloadReport({
                                    subject: 'Office',
                                    reviewPeriod: reviewPeriodLabel(),
                                    summaryRows: rows,
                                })
                            }
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
                                    Office Name
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
                                    <td colSpan={5} className="px-5 py-16">
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
                                        <td className="px-5 py-3 font-semibold text-gray-800">
                                            {row.name}
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
                                        <td className="px-5 py-3 text-right">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    downloadReport({
                                                        subject: 'Office',
                                                        reviewPeriod: reviewPeriodLabel(),
                                                        detail: row,
                                                    })
                                                }
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
                    page={offices.meta?.current_page ?? 1}
                    totalPages={offices.meta?.last_page ?? 1}
                    onPageChange={goToPage}
                />
            </div>
        </div>
    );
}
