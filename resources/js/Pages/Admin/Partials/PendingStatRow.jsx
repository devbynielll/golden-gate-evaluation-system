export default function PendingStatRow({ label, value, subtitle, icon: Icon }) {
    return (
        <div className="flex items-center justify-between gap-3 rounded-xl bg-white p-3 shadow-sm">
            <div>
                <p className="text-[10px] font-bold uppercase tracking-wide text-gray-400">
                    {label}
                </p>
                <p className="text-lg font-extrabold text-gray-900">
                    {value}
                </p>
                <p className="text-[10px] text-gray-400">{subtitle}</p>
            </div>

            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-green-100 text-green-600">
                <Icon className="h-4 w-4" />
            </div>
        </div>
    );
}
