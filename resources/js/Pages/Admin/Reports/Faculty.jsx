import AdminLayout from '@/Layouts/AdminLayout';
import FacultyPerformanceCard from '@/Pages/Admin/Reports/Partials/FacultyPerformanceCard';
import { Head } from '@inertiajs/react';

const EMPTY_PAGE = { data: [], meta: { current_page: 1, last_page: 1, total: 0 } };

// faculty performance reports page
export default function ReportsFaculty({
    faculty = EMPTY_PAGE,
    filterOptions = { departments: [], periods: [] },
    selectedFilters = {},
}) {
    return (
        <AdminLayout>
            <Head title="Faculty Reports" />

            <div className="rounded-2xl border-l-4 border-green-600 bg-white p-5 shadow-sm">
                <h1 className="text-lg font-extrabold text-gray-800">
                    Faculty Performance Reports
                </h1>
                <p className="mt-1 text-xs text-gray-400">
                    Evaluation reports and analytics for faculty performance assessment.
                </p>
            </div>

            <div className="mt-6">
                <FacultyPerformanceCard
                    faculty={faculty}
                    departments={filterOptions.departments}
                    periods={filterOptions.periods}
                    selected={selectedFilters}
                />
            </div>
        </AdminLayout>
    );
}
