import { useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import OfficePerformanceCard from './Partials/OfficePerformanceCard';

export default function StaffReports({ staff, filterOptions, selectedFilters, reportNotice }) {
    // Recheck release state on focus and while the page remains visible; no notifications.
    useEffect(() => {
        const refresh = () => { if (!document.hidden) router.reload({ only: ['staff'], preserveScroll: true }); };
        const timer = window.setInterval(refresh, 5000);
        window.addEventListener('focus', refresh);
        document.addEventListener('visibilitychange', refresh);
        return () => { window.clearInterval(timer); window.removeEventListener('focus', refresh); document.removeEventListener('visibilitychange', refresh); };
    }, []);
    return (
        <AdminLayout>
            <Head title="Staff Evaluation Reports" />
            <div className="mb-5 rounded-2xl border-l-4 border-green-600 bg-white p-5 shadow-sm">
                <h1 className="text-lg font-extrabold text-gray-800">Individual Staff Evaluation Reports</h1>
                <p className="mt-1 text-xs text-gray-500">Staff results are separate from overall Office ratings. Each report covers one staff assignment and review period.</p>
            </div>
            <OfficePerformanceCard subject="Staff" reportRoute="reports.staff" offices={staff} officeOptions={filterOptions.offices}
                periods={filterOptions.periods} selected={selectedFilters} reportNotice={reportNotice} />
        </AdminLayout>
    );
}
