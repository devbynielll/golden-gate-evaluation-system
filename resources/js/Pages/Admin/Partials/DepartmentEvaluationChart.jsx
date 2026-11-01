import { useState } from 'react';

const FACULTY_COLOR = '#16a34a';
const OFFICE_COLOR = '#2563eb';
const CHART_HEIGHT = 180;
const BAR_WIDTH = 20;
const BAR_GAP = 2;
const GROUP_GAP = 28;

export default function DepartmentEvaluationChart({ departments = [], showOffices = true }) {
    const [hovered, setHovered] = useState(null); // { code, type, value }

    const groupWidth = showOffices ? BAR_WIDTH * 2 + BAR_GAP : BAR_WIDTH;
    const width = departments.length * (groupWidth + GROUP_GAP) + GROUP_GAP;
    const ticks = [0, 25, 50, 75, 100];

    const barHeight = (percent) => (percent / 100) * CHART_HEIGHT;

    return (
        <div className="rounded-xl bg-white p-5 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs font-bold uppercase tracking-wide text-gray-400">
                    Evaluation Completion by Department
                </p>
                <div className="flex items-center gap-4 text-xs text-gray-500">
                    <span className="flex items-center gap-1.5">
                        <span
                            className="h-2.5 w-2.5 rounded-full"
                            style={{ backgroundColor: FACULTY_COLOR }}
                        />
                        Faculty
                    </span>
                    {showOffices && (
                        <span className="flex items-center gap-1.5">
                            <span
                                className="h-2.5 w-2.5 rounded-full"
                                style={{ backgroundColor: OFFICE_COLOR }}
                            />
                            Office
                        </span>
                    )}
                </div>
            </div>

            {departments.length === 0 ? (
                <p className="mt-10 pb-10 text-center text-xs text-gray-400">
                    No department data yet.
                </p>
            ) : (
                <div className="mt-4 overflow-x-auto">
                    <svg
                        width={width}
                        height={CHART_HEIGHT + 40}
                        role="img"
                        aria-label={showOffices
                            ? 'Evaluation completion percentage by department, faculty and office'
                            : 'Faculty evaluation completion percentage by department'}
                    >
                        {ticks.map((tick) => (
                            <line
                                key={tick}
                                x1={0}
                                x2={width}
                                y1={CHART_HEIGHT - barHeight(tick) + 10}
                                y2={CHART_HEIGHT - barHeight(tick) + 10}
                                stroke="#e5e7eb"
                                strokeWidth={1}
                            />
                        ))}

                        {departments.map((dept, i) => {
                            const groupX = GROUP_GAP + i * (groupWidth + GROUP_GAP);
                            const facultyH = barHeight(dept.facultyProgress);
                            const officeH = barHeight(dept.officeProgress);

                            return (
                                <g key={dept.code}>
                                    <rect
                                        x={groupX}
                                        y={CHART_HEIGHT - facultyH + 10}
                                        width={BAR_WIDTH}
                                        height={Math.max(facultyH, 1)}
                                        rx={4}
                                        fill={FACULTY_COLOR}
                                        onMouseEnter={() =>
                                            setHovered({ code: dept.code, type: 'Faculty', value: dept.facultyProgress })
                                        }
                                        onMouseLeave={() => setHovered(null)}
                                    />
                                    {showOffices && (
                                        <rect
                                            x={groupX + BAR_WIDTH + BAR_GAP}
                                            y={CHART_HEIGHT - officeH + 10}
                                            width={BAR_WIDTH}
                                            height={Math.max(officeH, 1)}
                                            rx={4}
                                            fill={OFFICE_COLOR}
                                            onMouseEnter={() =>
                                                setHovered({ code: dept.code, type: 'Office', value: dept.officeProgress })
                                            }
                                            onMouseLeave={() => setHovered(null)}
                                        />
                                    )}
                                    <text
                                        x={groupX + groupWidth / 2}
                                        y={CHART_HEIGHT + 28}
                                        textAnchor="middle"
                                        className="fill-gray-500"
                                        fontSize={11}
                                    >
                                        {dept.code}
                                    </text>
                                </g>
                            );
                        })}
                    </svg>
                </div>
            )}

            {hovered && (
                <p className="mt-1 text-center text-xs text-gray-500">
                    {hovered.code} · {hovered.type}: {hovered.value}%
                </p>
            )}
        </div>
    );
}
