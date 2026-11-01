import InfoCircleIcon from '@/Components/Icons/InfoCircleIcon';

export default function WelcomeCard({ showOffices = true }) {
    return (
        <div className="rounded-xl border-l-4 border-green-600 bg-white p-6 shadow-sm">
            <h2 className="text-xl font-extrabold text-gray-900">
                Welcome, Administrator!
            </h2>
            <p className="mt-2 max-w-3xl text-sm text-gray-500">
                {showOffices ? (
                    <>
                        This administrative dashboard provides an overview of the
                        faculty and office evaluation processes. It allows
                        administrators to manage college departments, faculty
                        members, subjects, offices, and evaluation criteria; monitor
                        evaluation progress; and generate comprehensive reports on
                        evaluation results.
                    </>
                ) : (
                    <>
                        This administrative dashboard provides an overview of
                        faculty evaluation across all departments. It allows administrators
                        to manage faculty members, subjects, classes, and students; monitor
                        faculty evaluation progress; and generate faculty evaluation reports.
                    </>
                )}
            </p>

            <div className="mt-4 flex items-center gap-2 rounded-lg bg-green-50 px-4 py-2.5">
                <InfoCircleIcon className="h-4 w-4 shrink-0 text-green-600" />
                <span className="text-xs font-semibold text-green-700">
                    Access is restricted to authorized personnel only.
                </span>
            </div>
        </div>
    );
}
