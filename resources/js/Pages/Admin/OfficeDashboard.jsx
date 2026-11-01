import AdminLayout from '@/Layouts/AdminLayout';
import ProgressBarCard from '@/Pages/Admin/Partials/ProgressBarCard';
import { Head, Link } from '@inertiajs/react';

const STATUS_STYLES = {
    'Very Satisfactory': 'bg-green-100 text-green-700',
    Satisfactory: 'bg-blue-100 text-blue-700',
    Unsatisfactory: 'bg-yellow-100 text-yellow-700',
    'Very Unsatisfactory': 'bg-red-100 text-red-600',
    'No Data': 'bg-gray-100 text-gray-500',
};

// office admin home: assigned office, active period
export default function OfficeDashboard({ summary }) {
    const stats = [
        { label: 'Average Rating', value: summary.responses > 0 ? `${summary.rating.toFixed(2)} / 4.00` : '—' },
        { label: 'Responses', value: summary.responses },
        { label: 'Expected', value: summary.expected },
        { label: 'Pending', value: summary.pending },
    ];

    return (
        <AdminLayout>
            <Head title="Office Dashboard" />

            <div className="rounded-xl border-l-4 border-green-600 bg-white p-6 shadow-sm">
                <h2 className="text-xl font-extrabold text-gray-900">{summary.officeName ?? 'Office'}</h2>
                <p className="mt-1 text-sm text-gray-500">
                    {summary.offices} {summary.offices === 1 ? 'office' : 'offices'}
                    {summary.term ? ` · ${summary.term}` : ' · No active review period'}
                </p>
            </div>

            <div className="mt-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
                {stats.map(({ label, value }) => (
                    <div key={label} className="rounded-xl bg-white p-5 shadow-sm">
                        <p className="text-[11px] font-bold uppercase tracking-wide text-gray-400">
                            {label}
                        </p>
                        <p className="mt-2 text-2xl font-extrabold text-gray-800">{value}</p>
                    </div>
                ))}
            </div>

            <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                <ProgressBarCard label="Evaluation Progress" percent={summary.progress} />

                <div className="rounded-xl bg-white p-5 shadow-sm">
                    <p className="text-[11px] font-bold uppercase tracking-wide text-gray-400">
                        Interpretation
                    </p>
                    <span
                        className={`mt-3 inline-block rounded-full px-3 py-1 text-xs font-bold ${STATUS_STYLES[summary.status] ?? STATUS_STYLES['No Data']}`}
                    >
                        {summary.status}
                    </span>
                    <div className="mt-4">
                        <Link
                            href={route('reports.office')}
                            className="text-xs font-bold uppercase tracking-wide text-green-700 hover:underline"
                        >
                            View full report →
                        </Link>
                    </div>
                </div>
            </div>

            <div className="mt-4 rounded-xl bg-white p-5 shadow-sm">
                <p className="text-[11px] font-bold uppercase tracking-wide text-gray-400">
                    Student Feedback
                </p>
                <p className="mt-2 whitespace-pre-line text-sm text-gray-600">
                    {summary.feedback || 'No feedback submitted yet.'}
                </p>
            </div>
        </AdminLayout>
    );
}
