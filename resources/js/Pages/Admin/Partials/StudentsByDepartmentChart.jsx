const BAR_COLOR = '#16a34a';
const BAR_MAX_WIDTH = 220;

export default function StudentsByDepartmentChart({ departments = [] }) {
    const rows = [...departments]
        .filter((d) => d.students > 0)
        .sort((a, b) => b.students - a.students);

    const max = Math.max(...rows.map((d) => d.students), 1);

    return (
        <div className="rounded-xl bg-white p-5 shadow-sm">
            <p className="text-xs font-bold uppercase tracking-wide text-gray-400">
                Students by Department
            </p>

            {rows.length === 0 ? (
                <p className="mt-10 pb-10 text-center text-xs text-gray-400">
                    No students yet.
                </p>
            ) : (
                <div className="mt-4 space-y-2.5">
                    {rows.map((dept) => {
                        const width = (dept.students / max) * BAR_MAX_WIDTH;

                        return (
                            <div key={dept.code} className="flex items-center gap-3">
                                <span className="w-14 shrink-0 text-xs font-semibold text-gray-600">
                                    {dept.code}
                                </span>
                                <svg width={BAR_MAX_WIDTH + 4} height={16}>
                                    <rect
                                        x={0}
                                        y={0}
                                        width={Math.max(width, 4)}
                                        height={16}
                                        rx={4}
                                        fill={BAR_COLOR}
                                    />
                                </svg>
                                <span className="text-xs font-semibold text-gray-700">
                                    {dept.students}
                                </span>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}
