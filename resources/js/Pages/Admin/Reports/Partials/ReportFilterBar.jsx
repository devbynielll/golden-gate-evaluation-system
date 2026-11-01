import FilterIcon from '@/Components/Icons/FilterIcon';

// filter row above the summary table
export default function ReportFilterBar({ children, periods = [], periodId, onPeriodChange, onApply }) {
    return (
        <div className="flex flex-wrap items-center gap-4 rounded-xl bg-white p-4 shadow-sm">
            <span className="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-gray-600">
                <FilterIcon className="h-4 w-4 text-green-600" />
                Filter Reports
            </span>

            {children}

            <div className="flex items-center gap-2">
                <span className="text-[10px] font-bold uppercase tracking-wide text-gray-400">
                    Semester
                </span>
                <select
                    value={periodId ?? ''}
                    onChange={(e) => onPeriodChange(e.target.value || null)}
                    className="rounded-md border-gray-300 text-xs shadow-sm focus:border-green-600 focus:ring-green-600"
                >
                    <option value="">All Semesters</option>
                    {periods.map((period) => (
                        <option key={period.id} value={period.id}>
                            {period.label}
                        </option>
                    ))}
                </select>
            </div>

            <button
                type="button"
                onClick={onApply}
                className="ml-auto rounded-md bg-green-700 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800"
            >
                Apply Filter
            </button>
        </div>
    );
}
