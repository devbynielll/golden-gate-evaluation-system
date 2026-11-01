export default function StatCard({ label, value, subtitle, icon: Icon }) {
    return (
        <div className="flex items-center justify-between gap-3 rounded-xl bg-white p-5 shadow-sm">
            <div>
                <p className="text-xs font-bold uppercase tracking-wide text-gray-400">
                    {label}
                </p>
                <p className="mt-1 text-3xl font-extrabold text-gray-900">
                    {value}
                </p>
                <p className="mt-1 text-xs text-gray-400">{subtitle}</p>
            </div>

            <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-green-100 text-green-600">
                <Icon className="h-6 w-6" />
            </div>
        </div>
    );
}
