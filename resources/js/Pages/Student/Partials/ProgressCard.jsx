export default function ProgressCard({ completed = 0, total = 0 }) {
    const percent = total > 0 ? Math.round((completed / total) * 100) : 0;

    return (
        <div className="rounded-xl bg-white p-5 shadow-sm">
            <div className="flex items-center justify-between">
                <p className="text-xs font-bold uppercase tracking-wide text-gray-400">
                    Your Progress
                </p>
                <p className="text-xs font-bold text-green-700">
                    {completed}/{total} Completed
                </p>
            </div>

            <div className="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-200">
                <div
                    className="h-full rounded-full bg-green-600 transition-all"
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}
