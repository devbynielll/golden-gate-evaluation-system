import { useState } from 'react';
import { router } from '@inertiajs/react';
import CalendarIcon from '@/Components/Icons/CalendarIcon';
import ManagePeriodsModal from './ManagePeriodsModal';

export default function ReviewPeriodCard({
    isOpen = false,
    periods = [],
    canManage = false,
}) {
    const [showManagePeriods, setShowManagePeriods] = useState(false);
    const [toggling, setToggling] = useState(false);

    const activePeriod = periods.find((period) => period.is_active) ?? null;

    const toggleSubmission = () => {
        if (!activePeriod) return;
        setToggling(true);
        router.patch(
            route('review-periods.toggle-submission', activePeriod.id),
            {},
            { preserveScroll: true, onFinish: () => setToggling(false) },
        );
    };

    return (
        <div className="rounded-xl bg-white p-5 shadow-sm">
            <div className="flex items-center gap-2 text-green-700">
                <CalendarIcon className="h-4 w-4" />
                <h3 className="text-sm font-extrabold uppercase tracking-wide">
                    Review Period
                </h3>
            </div>

            <p className="mt-4 text-xs font-bold uppercase tracking-wide text-gray-400">
                Active Semester
            </p>
            <div className="mt-1 flex items-center justify-between rounded-md border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm">
                {activePeriod ? (
                    <span className="font-semibold text-gray-800">
                        {activePeriod.semester} | {activePeriod.academic_year}
                    </span>
                ) : (
                    <span className="italic text-gray-400">
                        No active period
                    </span>
                )}
                <span
                    className={`h-2 w-2 shrink-0 rounded-full ${isOpen ? 'bg-green-500' : 'bg-red-400'}`}
                />
            </div>

            <div className="mt-4 rounded-lg bg-red-50 px-4 py-3 text-center">
                <p className="text-[10px] font-bold uppercase tracking-wide text-red-400">
                    Review Status
                </p>
                <p className="mt-0.5 text-sm font-extrabold text-red-600">
                    {isOpen ? 'Evaluation is open' : 'Evaluation is closed'}
                </p>
            </div>

            {canManage && (
                <>
                <button
                    type="button"
                    onClick={toggleSubmission}
                    disabled={!activePeriod || toggling}
                    className={`mt-4 w-full rounded-md py-2 text-xs font-bold uppercase tracking-wide transition disabled:cursor-not-allowed disabled:opacity-50 ${
                        isOpen
                            ? 'border border-red-500 text-red-600 hover:bg-red-50'
                            : 'bg-green-700 text-white hover:bg-green-800'
                    }`}
                >
                    {!activePeriod
                        ? 'No Active Period'
                        : isOpen
                          ? 'Close Evaluations'
                          : 'Open Evaluations'}
                </button>

                <button
                    type="button"
                    onClick={() => setShowManagePeriods(true)}
                    className="mt-3 w-full rounded-md border border-green-600 py-2 text-xs font-bold uppercase tracking-wide text-green-700 transition hover:bg-green-50"
                >
                    Manage Periods
                </button>
                </>
            )}

            <ManagePeriodsModal
                show={showManagePeriods}
                onClose={() => setShowManagePeriods(false)}
                periods={periods}
            />
        </div>
    );
}
