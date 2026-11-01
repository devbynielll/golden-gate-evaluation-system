import AdminLayout from '@/Layouts/AdminLayout';
import CriteriaBoard from '@/Pages/Admin/Criteria/Partials/CriteriaBoard';
import { Head } from '@inertiajs/react';

// faculty criteria page
export default function CriteriaFaculty({ label, categories, totalQuestions }) {
    return (
        <AdminLayout>
            <Head title="Faculty Criteria" />
            <CriteriaBoard
                type="faculty"
                label={label}
                categories={categories}
                totalQuestions={totalQuestions}
            />
        </AdminLayout>
    );
}
