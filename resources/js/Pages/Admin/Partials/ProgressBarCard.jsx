export default function ProgressBarCard({ label, percent = 0 }) {
    return (
        <div className="rounded-xl bg-white p-5 shadow-sm">
            <div className="flex items-center justify-between">
                <p className="text-xs font-bold uppercase tracking-wide text-gray-400">
                    {label}
                </p>
                <p className="text-xs font-bold text-gray-500">
                    {percent}% Completed
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
