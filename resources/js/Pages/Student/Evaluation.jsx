import officesCardBg from '@/Assets/offices-card-bg.png';
import teacherCardBg from '@/Assets/teacher-card-bg.png';
import Pagination from '@/Components/Pagination';
import StudentLayout from '@/Layouts/StudentLayout';
import EvaluationCard from '@/Pages/Student/Partials/EvaluationCard';
import EvaluationFormModal from '@/Pages/Student/Partials/EvaluationFormModal';
import ProgressCard from '@/Pages/Student/Partials/ProgressCard';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

const EMPTY_PAGE = { data: [], meta: { current_page: 1, last_page: 1 } };

export default function Evaluation({
    faculties = EMPTY_PAGE,
    offices = EMPTY_PAGE,
    progress = { completed: 0, total: 0 },
    isSubmissionOpen = false,
    warnings = [],
    officeStaff = EMPTY_PAGE,
    staffProgress = { completed: 0, total: 0 },
}) {
    const [activeEvaluation, setActiveEvaluation] = useState(null); // { type, item }

    const goToPage = (key, page) =>
        router.get(route('evaluation.index'), { [key]: page }, {
            preserveState: true,
            preserveScroll: true,
            only: ['faculties', 'offices', 'officeStaff', 'staffProgress', 'isSubmissionOpen'],
        });

    const submit = ({ ratings, comments }, { onSuccess, onError }) => {
        if (!isSubmissionOpen) {
            onError?.({ evaluation: 'Evaluations are currently closed. Reload the evaluation page.' });
            return;
        }
        const { type, item } = activeEvaluation;
        const url = type === 'faculty'
            ? route('evaluation.faculty.store', item.id)
            : type === 'staff' ? route('evaluation.staff.store', item.id) : route('evaluation.office.store', item.id);

        router.post(url, { ratings, comments }, {
            preserveScroll: true,
            only: ['faculties', 'offices', 'progress', 'officeStaff', 'staffProgress', 'isSubmissionOpen', 'errors', 'flash'],
            onSuccess,
            onError,
        });
    };

    return (
        <StudentLayout>
            <Head title="Evaluation" />

            <ProgressCard completed={progress.completed} total={progress.total} />

            {warnings.map((warning) => (
                <p key={warning} className="mt-4 rounded-md bg-yellow-50 px-4 py-2.5 text-sm text-yellow-700">
                    {warning}
                </p>
            ))}

            {!isSubmissionOpen && (
                <p className="mt-4 rounded-md bg-yellow-50 px-4 py-2.5 text-sm text-yellow-700">
                    Evaluations are currently closed. Check back once the review period opens.
                </p>
            )}

            <h2 className="mt-8 text-sm font-extrabold uppercase tracking-wide text-green-700">
                Faculties
            </h2>
            <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {faculties.data.map((faculty) => (
                    <EvaluationCard
                        key={faculty.id}
                        image={teacherCardBg}
                        tint="bg-green-700/50"
                        title={faculty.cardTitle}
                        subtitle={faculty.subtitle}
                        completed={faculty.completed}
                        disabled={!isSubmissionOpen}
                        disabledLabel="Evaluations closed"
                        onClick={() =>
                            setActiveEvaluation({ type: 'faculty', item: faculty })
                        }
                    />
                ))}
            </div>
            <Pagination
                page={faculties.meta.current_page}
                totalPages={faculties.meta.last_page}
                onPageChange={(page) => goToPage('faculty_page', page)}
            />

            <h2 className="mt-8 text-sm font-extrabold uppercase tracking-wide text-green-700">
                Offices
            </h2>
            <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {offices.data.map((office) => (
                    <EvaluationCard
                        key={office.id}
                        image={officesCardBg}
                        title={office.name}
                        completed={office.completed}
                        disabled={!isSubmissionOpen}
                        disabledLabel="Evaluations closed"
                        onClick={() =>
                            setActiveEvaluation({ type: 'office', item: office })
                        }
                    />
                ))}
            </div>
            <Pagination
                page={offices.meta.current_page}
                totalPages={offices.meta.last_page}
                onPageChange={(page) => goToPage('office_page', page)}
            />

            <section className="mt-8" aria-label="Optional individual staff evaluations">
                <h2 className="text-sm font-extrabold uppercase tracking-wide text-green-700">Individual Office Staff (Optional)</h2>
                <p className="mt-2 text-sm text-gray-600">These evaluations are separate from overall Office evaluations and do not affect required completion.</p>
                <p className="mt-1 text-xs text-gray-500">Optional staff evaluations completed: {staffProgress.completed} / {staffProgress.total}</p>
                <div className="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {officeStaff.data.map((member) => <EvaluationCard key={member.id} image={officesCardBg}
                        title={member.name} subtitle={member.subtitle} completed={member.completed}
                        disabled={!isSubmissionOpen || !member.categories.some((category) => category.questions.length > 0)}
                        disabledLabel={!isSubmissionOpen ? 'Evaluations closed' : 'No staff criteria available'}
                        onClick={() => setActiveEvaluation({ type: 'staff', item: member })} />)}
                </div>
                {!officeStaff.data.length && <p className="mt-3 rounded-xl bg-white p-5 text-sm text-gray-500">No active staff are available for your applicable offices.</p>}
                <Pagination page={officeStaff.meta.current_page} totalPages={officeStaff.meta.last_page} onPageChange={(page) => goToPage('staff_page', page)} />
            </section>

            {activeEvaluation && (
                <EvaluationFormModal
                    show={!!activeEvaluation}
                    onClose={() => setActiveEvaluation(null)}
                    title={activeEvaluation.item.cardTitle ?? activeEvaluation.item.name}
                    subtitle={activeEvaluation.item.subtitle}
                    categories={activeEvaluation.item.categories}
                    isSubmissionOpen={isSubmissionOpen}
                    onSubmit={submit}
                />
            )}
        </StudentLayout>
    );
}
