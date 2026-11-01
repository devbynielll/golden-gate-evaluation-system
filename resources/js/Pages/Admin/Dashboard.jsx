import AdminLayout from '@/Layouts/AdminLayout';
import DepartmentEvaluationChart from '@/Pages/Admin/Partials/DepartmentEvaluationChart';
import EvaluationFeedCard from '@/Pages/Admin/Partials/EvaluationFeedCard';
import ProgressBarCard from '@/Pages/Admin/Partials/ProgressBarCard';
import ReviewPeriodCard from '@/Pages/Admin/Partials/ReviewPeriodCard';
import StatsOverview from '@/Pages/Admin/Partials/StatsOverview';
import StudentsByDepartmentChart from '@/Pages/Admin/Partials/StudentsByDepartmentChart';
import WelcomeCard from '@/Pages/Admin/Partials/WelcomeCard';
import { Head } from '@inertiajs/react';

export default function AdminDashboard({
    totalStudents = 0,
    totalFaculty = 0,
    totalOffices = 0,
    facultyPending = 0,
    officePending = 0,
    facultyProgress = 0,
    officeProgress = 0,
    isReviewOpen = false,
    showOffices = true,
    departmentBreakdown = [],
    evaluations,
    filterOptions,
    selectedFilters,
    periods = [],
    canManagePeriods = false,
}) {
    return (
        <AdminLayout>
            <Head title="Admin Dashboard" />

            <WelcomeCard showOffices={showOffices} />

            <StatsOverview
                totalStudents={totalStudents}
                totalFaculty={totalFaculty}
                totalOffices={totalOffices}
                facultyPending={facultyPending}
                officePending={officePending}
                showOffices={showOffices}
            />

            <div className={`mt-4 grid grid-cols-1 gap-4 ${showOffices ? 'lg:grid-cols-2' : ''}`}>
                <ProgressBarCard
                    label="Faculty Evaluation Progress"
                    percent={facultyProgress}
                />
                {showOffices && (
                    <ProgressBarCard
                        label="Office Evaluation Progress"
                        percent={officeProgress}
                    />
                )}
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <DepartmentEvaluationChart
                    departments={departmentBreakdown}
                    showOffices={showOffices}
                />
                <StudentsByDepartmentChart departments={departmentBreakdown} />
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_2fr]">
                <ReviewPeriodCard
                    isOpen={isReviewOpen}
                    periods={periods}
                    canManage={canManagePeriods}
                />
                <EvaluationFeedCard
                    evaluations={evaluations}
                    filterOptions={filterOptions}
                    selectedFilters={selectedFilters}
                />
            </div>
        </AdminLayout>
    );
}
