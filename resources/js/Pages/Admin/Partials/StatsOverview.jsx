import ClipboardListIcon from '@/Components/Icons/ClipboardListIcon';
import UserIcon from '@/Components/Icons/UserIcon';
import PendingStatRow from './PendingStatRow';
import StatCard from './StatCard';

export default function StatsOverview({
    totalStudents = 0,
    totalFaculty = 0,
    totalOffices = 0,
    facultyPending = 0,
    officePending = 0,
    showOffices = true,
}) {
    const FacultyPendingCard = showOffices ? PendingStatRow : StatCard;

    return (
        <div
            className={`mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 ${
                showOffices ? 'lg:grid-cols-4' : 'lg:grid-cols-3'
            }`}
        >
            <StatCard
                label="Total Students"
                value={totalStudents}
                subtitle="Registered"
                icon={UserIcon}
            />
            <StatCard
                label="Total Faculty"
                value={totalFaculty}
                subtitle="In 4 college departments"
                icon={UserIcon}
            />
            {showOffices && (
                <StatCard
                    label="Total Offices"
                    value={totalOffices}
                    subtitle="Number of Offices"
                    icon={ClipboardListIcon}
                />
            )}

            <div className={showOffices ? 'flex flex-col gap-3' : 'grid'}>
                <FacultyPendingCard
                    label="Total Faculty Pending"
                    value={facultyPending}
                    subtitle="Submitted"
                    icon={ClipboardListIcon}
                />
                {showOffices && (
                    <PendingStatRow
                        label="Total Office Pending"
                        value={officePending}
                        subtitle="Submitted"
                        icon={ClipboardListIcon}
                    />
                )}
            </div>
        </div>
    );
}
