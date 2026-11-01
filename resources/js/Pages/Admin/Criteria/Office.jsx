import AdminLayout from '@/Layouts/AdminLayout';
import CriteriaBoard from '@/Pages/Admin/Criteria/Partials/CriteriaBoard';
import { Head, router, usePage } from '@inertiajs/react';

// office criteria page
export default function CriteriaOffice({ label, categories, totalQuestions, selectedOfficeId, criteriaOfficeName, criteriaOffices = [] }) {
    const isSuper = usePage().props.auth.user.role === 'super_admin';
    return (
        <AdminLayout>
            <Head title="Office Criteria" />
            {isSuper && (
                <label className="mb-4 block text-xs font-bold text-gray-600">
                    Criteria ownership
                    <select aria-label="Criteria ownership" value={selectedOfficeId ?? ''} onChange={(e) => router.get(route('criteria.office'), e.target.value ? { office_id: e.target.value } : {})} className="mt-1 block rounded-md border-gray-300 text-sm shadow-sm focus:border-green-600 focus:ring-green-600">
                        <option value="">Shared — all offices</option>
                        {criteriaOffices.map((office) => <option key={office.id} value={office.id}>{office.code} — {office.name}</option>)}
                    </select>
                </label>
            )}
            <p className="mb-4 text-xs text-gray-500">{criteriaOfficeName ? `${criteriaOfficeName} — shared questions are managed by Super Admin.` : 'Shared questions apply to all offices.'}</p>
            <CriteriaBoard
                key={selectedOfficeId ?? 'shared'}
                type="office"
                label={label}
                categories={categories}
                totalQuestions={totalQuestions}
            />
        </AdminLayout>
    );
}
