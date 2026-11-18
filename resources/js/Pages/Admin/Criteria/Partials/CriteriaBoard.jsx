import { useState } from 'react';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal';
import LayersIcon from '@/Components/Icons/LayersIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import PlusIcon from '@/Components/Icons/PlusIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import Pagination from '@/Components/Pagination';
import { router, usePage } from '@inertiajs/react';
import CategoryFormModal from './CategoryFormModal';
import QuestionFormModal from './QuestionFormModal';

const LEGEND = [
    { value: 4, label: 'Very Satisfactory', tag: 'Very Satisfactory', color: 'text-green-700' },
    { value: 3, label: 'Satisfactory', tag: 'Satisfactory', color: 'text-green-600' },
    { value: 2, label: 'Fairly Satisfactory', tag: 'Fairly Satisfactory', color: 'text-orange-500' },
    { value: 1, label: 'Unsatisfactory', tag: 'Unsatisfactory', color: 'text-red-500' },
];

const EMPTY_PAGE = { data: [], current_page: 1, last_page: 1, total: 0 };

// categories + questions board, one per evaluatee type (faculty/office)
export default function CriteriaBoard({
    type,
    label,
    categories = EMPTY_PAGE,
    totalQuestions = 0,
}) {
    const { errors, criteriaPermissions = {}, selectedOfficeId = null, criteriaOfficeName = null } = usePage().props;
    const officeScoped = type === 'office' || type === 'staff';
    const mutationRoute = (action, ...ids) => route(
        type === 'staff' ? `staff-criteria.${action}` : `criteria.${action}`,
        type === 'staff' ? ids : [type, ...ids],
    );
    const writesLocked = criteriaPermissions.writesLocked ?? false;
    const readOnlyCategory = (category) => (criteriaPermissions.readOnlyCategoryIds ?? []).includes(category.id);
    const categoryLocked = (category) => writesLocked || readOnlyCategory(category) || (criteriaPermissions.usedCategoryIds ?? []).includes(category.id);
    const questionLocked = (question) => writesLocked || (criteriaPermissions.usedQuestionIds ?? []).includes(question.id);

    const [saving, setSaving] = useState(false);
    const mutationOptions = (callbacks = {}) => ({ ...callbacks, onStart: () => setSaving(true), onFinish: () => { setSaving(false); callbacks.onFinish?.(); } });

    const [categoryModal, setCategoryModal] = useState(null); // { mode, category }
    const [pendingCategoryDeleteId, setPendingCategoryDeleteId] = useState(null);

    const [questionModal, setQuestionModal] = useState(null); // { mode, category, question }
    const [pendingQuestionDelete, setPendingQuestionDelete] = useState(null); // { categoryId, questionId }

    const goToPage = (page) =>
        router.get(route(route().current()), { page, ...(officeScoped && selectedOfficeId !== null ? { office_id: selectedOfficeId } : {}) }, {
            preserveState: true,
            preserveScroll: true,
            only: ['categories', 'criteriaPermissions'],
        });

    const saveCategory = (form) => {
        if (saving) return;
        const onSuccess = () => setCategoryModal(null);
        if (categoryModal?.mode === 'edit') {
            router.put(mutationRoute('categories.update', categoryModal.category.id), form, mutationOptions({ onSuccess }));
        } else {
            router.post(mutationRoute('categories.store'), { ...form, ...(officeScoped ? { office_id: selectedOfficeId } : {}) }, mutationOptions({ onSuccess }));
        }
    };

    const confirmCategoryDelete = () => {
        if (saving) return;
        router.delete(mutationRoute('categories.destroy', pendingCategoryDeleteId), mutationOptions({
            onFinish: () => setPendingCategoryDeleteId(null),
        }));
    };

    const saveQuestion = (form) => {
        if (saving) return;
        const { category, mode, question } = questionModal;
        const onSuccess = () => setQuestionModal(null);

        if (mode === 'edit') {
            router.put(mutationRoute('questions.update', category.id, question.id), form, mutationOptions({ onSuccess }));
        } else {
            router.post(mutationRoute('questions.store', category.id), form, mutationOptions({ onSuccess }));
        }
    };

    const askDeleteQuestion = () => {
        setPendingQuestionDelete({
            categoryId: questionModal.category.id,
            questionId: questionModal.question.id,
        });
        setQuestionModal(null);
    };

    const confirmQuestionDelete = () => {
        if (saving) return;
        const { categoryId, questionId } = pendingQuestionDelete;
        router.delete(mutationRoute('questions.destroy', categoryId, questionId), mutationOptions({
            onFinish: () => setPendingQuestionDelete(null),
        }));
    };

    const categoryList = categories.data ?? [];

    return (
        <div className="flex flex-col gap-6 lg:flex-row lg:items-start">
            <div className="flex-1 space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border-l-4 border-green-600 bg-white p-5 shadow-sm">
                    <div>
                        <h1 className="text-lg font-extrabold text-gray-800">
                            Evaluation Criteria Management
                        </h1>
                        <p className="mt-1 text-xs text-gray-400">
                            Manage evaluation questions grouped by category.
                            All questions use the standard 1-4 Likert scale.
                        </p>
                    </div>

                    <button
                        type="button"
                        disabled={writesLocked || saving}
                        onClick={() => setCategoryModal({ mode: 'add' })}
                        className="flex items-center gap-1.5 rounded-md bg-green-700 px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800"
                    >
                        <PlusIcon className="h-3.5 w-3.5" />
                        Add New Category
                    </button>
                </div>

                {saving && <p role="status" className="text-xs text-gray-500">Saving criteria…</p>}
                {criteriaPermissions.notice && <p className="text-xs text-gray-500">{criteriaPermissions.notice}</p>}
                {errors.criteria && <p className="text-xs text-red-500" role="alert">{errors.criteria}</p>}

                <h2 className="text-lg font-extrabold uppercase tracking-wide text-green-700">
                    {label}
                </h2>

                {categoryList.length === 0 ? (
                    <p className="rounded-xl bg-white py-14 text-center text-xs text-gray-400 shadow-sm">
                        No categories added yet.
                    </p>
                ) : (
                    categoryList.map((category) => (
                        <div
                            key={category.id}
                            className="overflow-hidden rounded-xl bg-white shadow-sm"
                        >
                            <div className="flex items-center justify-between gap-3 bg-green-700 px-5 py-3">
                                <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-white">
                                    <LayersIcon className="h-4 w-4" />
                                    Section {category.section_number}: {category.name}
                                    {officeScoped && <span className="font-normal"> — {category.office_id === null ? 'Shared' : criteriaOfficeName}</span>}
                                </div>
                                <div className="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        title="Add Question"
                                        disabled={writesLocked || saving || readOnlyCategory(category)}
                                        onClick={() =>
                                            setQuestionModal({
                                                mode: 'add',
                                                category,
                                            })
                                        }
                                        className="flex h-7 w-7 items-center justify-center rounded-md bg-white/20 text-white transition hover:bg-white/30"
                                    >
                                        <PlusIcon className="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        title="Edit Category"
                                        disabled={saving || categoryLocked(category)}
                                        onClick={() =>
                                            setCategoryModal({
                                                mode: 'edit',
                                                category: {
                                                    ...category,
                                                    section: String(category.section_number),
                                                },
                                            })
                                        }
                                        className="flex h-7 w-7 items-center justify-center rounded-md bg-white/20 text-white transition hover:bg-white/30"
                                    >
                                        <PencilIcon className="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        title="Delete Category"
                                        disabled={saving || categoryLocked(category)}
                                        onClick={() =>
                                            setPendingCategoryDeleteId(
                                                category.id,
                                            )
                                        }
                                        className="flex h-7 w-7 items-center justify-center rounded-md bg-white/20 text-white transition hover:bg-white/30"
                                    >
                                        <TrashIcon className="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>

                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-gray-100 bg-gray-50">
                                        <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                            Question
                                        </th>
                                        <th className="px-5 py-2.5 text-right text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {(category.questions ?? []).length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={2}
                                                className="px-5 py-10 text-center text-xs text-gray-400"
                                            >
                                                No question added yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        category.questions.map((question) => (
                                            <tr
                                                key={question.id}
                                                className="border-b border-gray-50 last:border-0"
                                            >
                                                <td className="px-5 py-3 text-gray-700">
                                                    {question.text}
                                                </td>
                                                <td className="px-5 py-3 text-right">
                                                    <button
                                                        type="button"
                                                        title="Edit Question"
                                                        disabled={saving || readOnlyCategory(category) || questionLocked(question)}
                                                        onClick={() =>
                                                            setQuestionModal({
                                                                mode: 'edit',
                                                                category,
                                                                question,
                                                            })
                                                        }
                                                        className="inline-flex h-8 w-8 items-center justify-center rounded-md bg-blue-100 text-blue-500 transition hover:bg-blue-200"
                                                    >
                                                        <PencilIcon className="h-4 w-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    ))
                )}

                <div className="overflow-hidden rounded-xl bg-white shadow-sm">
                    <Pagination
                        page={categories.current_page ?? 1}
                        totalPages={categories.last_page ?? 1}
                        onPageChange={goToPage}
                    />
                </div>
            </div>

            <div className="w-full shrink-0 space-y-4 lg:sticky lg:top-6 lg:w-64">
                <div className="rounded-xl bg-white p-4 shadow-sm">
                    <p className="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-green-700">
                        Summary
                    </p>
                    <div className="mt-3 space-y-2 text-sm text-gray-600">
                        <div className="flex items-center justify-between">
                            <span>Categories</span>
                            <span className="font-bold text-gray-800">
                                {categories.total ?? categoryList.length}
                            </span>
                        </div>
                        <div className="flex items-center justify-between">
                            <span>Total Questions</span>
                            <span className="font-bold text-gray-800">
                                {totalQuestions}
                            </span>
                        </div>
                    </div>
                </div>

                <div className="rounded-xl bg-white p-4 shadow-sm">
                    <p className="text-xs font-bold uppercase tracking-wide text-green-700">
                        Legend
                    </p>
                    <div className="mt-3 space-y-1.5 text-xs">
                        {LEGEND.map((item) => (
                            <div
                                key={item.value}
                                className="flex items-center justify-between gap-2"
                            >
                                <span className="text-gray-500">
                                    {item.value} - {item.label}
                                </span>
                                <span className={`font-bold ${item.color}`}>
                                    {item.tag}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            <CategoryFormModal
                processing={saving}
                show={categoryModal !== null}
                mode={categoryModal?.mode}
                category={categoryModal?.category}
                onClose={() => { if (!saving) setCategoryModal(null); }}
                onSubmit={saveCategory}
                errors={errors}
            />

            <ConfirmDeleteModal
                show={pendingCategoryDeleteId !== null}
                title="Delete Category?"
                description="This will delete ALL questions inside this category."
                onCancel={() => setPendingCategoryDeleteId(null)}
                onConfirm={confirmCategoryDelete}
            />

            <QuestionFormModal
                processing={saving}
                show={questionModal !== null}
                mode={questionModal?.mode}
                categoryName={questionModal?.category?.name ?? ''}
                question={questionModal?.question}
                onClose={() => { if (!saving) setQuestionModal(null); }}
                onSubmit={saveQuestion}
                onDelete={askDeleteQuestion}
                errors={errors}
            />

            <ConfirmDeleteModal
                show={pendingQuestionDelete !== null}
                title="Delete Question?"
                description="This will delete the entire question."
                onCancel={() => setPendingQuestionDelete(null)}
                onConfirm={confirmQuestionDelete}
            />
        </div>
    );
}
