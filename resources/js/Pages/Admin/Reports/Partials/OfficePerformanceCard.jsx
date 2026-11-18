import { useState } from 'react';
import DocumentIcon from '@/Components/Icons/DocumentIcon';
import DownloadIcon from '@/Components/Icons/DownloadIcon';
import InboxIcon from '@/Components/Icons/InboxIcon';
import Pagination from '@/Components/Pagination';
import SearchIcon from '@/Components/Icons/SearchIcon';
import { router } from '@inertiajs/react';
import ReportFilterBar from './ReportFilterBar';
import ReportViewer from './ReportViewer';
import useReport from './useReport';
import { ratingLabel } from './reportRows';

// office performance summary + filters
export default function OfficePerformanceCard({
    offices = { data: [], meta: { current_page: 1, last_page: 1, total: 0 } },
    periods = [],
    selected = { search: '', period_id: null },
    subject = 'Office',
    reportRoute = 'reports.office',
    officeOptions = [],
    reportNotice = '',
}) {
    const isStaff = subject === 'Staff';
    const [officeId, setOfficeId] = useState(selected.office_id ?? '');
    const [search, setSearch] = useState(selected.search ?? '');
    const [periodId, setPeriodId] = useState(selected.period_id ?? null);

    const report = useReport(reportRoute, selected);

    const visit = (params) =>
        router.get(route(reportRoute), params, {
            preserveState: true,
            preserveScroll: true,
            only: [isStaff ? 'staff' : 'offices', 'selectedFilters'],
        });

    const applyFilter = () =>
        visit({ search: search || undefined, period_id: periodId || undefined, ...(isStaff ? { office_id: officeId || undefined } : {}) });

    const goToPage = (page) =>
        visit({
            search: selected.search || undefined,
            period_id: selected.period_id || undefined,
            page,
            ...(isStaff ? { office_id: selected.office_id || undefined } : {}),
        });

    const rows = offices.data ?? [];

    return (
        <div className="space-y-3">
            {reportNotice && <p className="rounded-md bg-yellow-50 p-3 text-sm text-yellow-800">{reportNotice}</p>}
            <ReportFilterBar
                periods={periods}
                periodId={periodId}
                onPeriodChange={setPeriodId}
                onApply={applyFilter}
            >
                {isStaff && officeOptions.length > 0 && <label className="text-xs font-semibold text-gray-600">Office
                    <select aria-label="Report office" value={officeId} onChange={(event) => setOfficeId(event.target.value)} className="ml-2 rounded-md border-gray-300 text-xs focus:border-green-600 focus:ring-green-600">
                        <option value="">All Offices</option>{officeOptions.map((office) => <option key={office.id} value={office.id}>{office.code} — {office.name}</option>)}
                    </select>
                </label>}
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
                            placeholder={isStaff ? 'Search staff or offices...' : 'Search offices...'}
                            className="rounded-md border-gray-300 py-1.5 pl-8 pr-3 text-xs shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                        />
                    </div>
                </div>
            </ReportFilterBar>

            <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-3 bg-green-700 px-5 py-3">
                    <span className="text-xs font-bold uppercase tracking-wide text-white">
                        {isStaff ? 'Staff Evaluation Reports' : 'Office Performance Summary'}
                    </span>
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="rounded-full bg-white/20 px-3 py-1 text-[11px] font-bold text-white">
                            {offices.meta?.total ?? rows.length} {isStaff ? 'Report Group' : 'Office'}{(offices.meta?.total ?? rows.length) === 1 ? '' : 's'}
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
                                    {isStaff ? 'Staff / Office / Review Period' : 'Office Name'}
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
                                            {isStaff && <div className="mt-1 space-y-1 text-xs font-normal text-gray-600">
                                                <p>{row.position} | {row.officeCode} — {row.officeName}</p><p>{row.reviewPeriod}</p>
                                                {row.disclosureMessage && <p className="text-yellow-800">{row.disclosureMessage}</p>}
                                            </div>}
                                        </td>
                                        <td className="px-5 py-3 font-bold text-green-600">
                                            {isStaff ? ratingLabel(row) : row.rating.toFixed(2)}
                                        </td>
                                        <td className="px-5 py-3 text-gray-700">
                                            {isStaff && row.responses === null ? 'Withheld' : row.responses}
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
                    page={offices.meta?.current_page ?? 1}
                    totalPages={offices.meta?.last_page ?? 1}
                    onPageChange={goToPage}
                />
            </div>
            <ReportViewer show={report.show} loading={report.loading} error={report.error} report={report.report}
                onClose={report.close} onDownload={report.downloadCurrent} />
        </div>
    );
}
