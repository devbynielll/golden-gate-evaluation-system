import { useState } from 'react';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal';
import ClipboardListIcon from '@/Components/Icons/ClipboardListIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import PlusIcon from '@/Components/Icons/PlusIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import ProgramFormModal from './ProgramFormModal';

// "Manage Department" page — its list of programs
export default function ManageDepartmentView({ department, programs = [] }) {
    const { errors, auth } = usePage().props;
    // dept admin has no list to go back to
    const canBrowseDepartments = auth.user.role === 'super_admin';
    const [formModal, setFormModal] = useState(null); // { mode, program }
    const [pendingDeleteId, setPendingDeleteId] = useState(null);

    const saveProgram = (form) => {
        if (formModal?.mode === 'edit') {
            router.put(
                route('departments.programs.update', [department.id, formModal.program.id]),
                form,
                { onSuccess: () => setFormModal(null) },
            );
        } else {
            router.post(
                route('departments.programs.store', department.id),
                form,
                { onSuccess: () => setFormModal(null) },
            );
        }
    };

    const confirmDelete = () => {
        router.delete(
            route('departments.programs.destroy', [department.id, pendingDeleteId]),
            { onFinish: () => setPendingDeleteId(null) },
        );
    };

    return (
        <AdminLayout>
            <Head title={`Manage ${department.code}`} />

            <div className="flex flex-wrap items-center justify-between gap-4">
                <h1 className="text-2xl font-extrabold uppercase tracking-wide text-green-700">
                    Manage Department
                </h1>

                {canBrowseDepartments && (
                    <Link
                        href={route('departments.index')}
                        className="rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-bold uppercase tracking-wide text-gray-600 shadow-sm transition hover:bg-gray-50"
                    >
                        ← Back to List
                    </Link>
                )}
            </div>

            <div className="mt-3 inline-flex items-center gap-2 rounded-md bg-gray-100 px-3 py-1.5 text-sm">
                <span className="font-bold text-green-700">
                    {department.code}
                </span>
                <span className="text-gray-300">|</span>
                <span className="text-gray-600">{department.name}</span>
            </div>

            <div className="mt-5 rounded-2xl border-t-4 border-green-600 bg-white p-6 shadow-sm">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <h2 className="text-sm font-extrabold uppercase tracking-wide text-gray-600">
                        Academic Programs
                    </h2>

                    <button
                        type="button"
                        onClick={() => setFormModal({ mode: 'add' })}
                        className="flex items-center gap-1.5 rounded-md bg-green-700 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800"
                    >
                        <PlusIcon className="h-3.5 w-3.5" />
                        Add Program
                    </button>
                </div>

                <div className="mt-4 min-h-[220px] overflow-x-auto rounded-lg border border-gray-100">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-gray-100 bg-gray-50">
                                <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                    Program Code
                                </th>
                                <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                    Program Name
                                </th>
                                <th className="px-5 py-2.5 text-right text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {programs.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={3}
                                        className="px-5 py-14 text-center text-xs text-gray-400"
                                    >
                                        No academic programs yet.
                                    </td>
                                </tr>
                            ) : (
                                programs.map((program) => (
                                    <tr
                                        key={program.id}
                                        className="border-b border-gray-50 last:border-0"
                                    >
                                        <td className="px-5 py-3 font-semibold text-green-700">
                                            {program.code}
                                        </td>
                                        <td className="px-5 py-3 text-gray-700">
                                            {program.name}
                                        </td>
                                        <td className="px-5 py-3">
                                            <div className="flex items-center justify-end gap-2">
                                                <Link
                                                    href={route('departments.programs.show', [department.id, program.id])}
                                                    title="Manage Program"
                                                    className="flex h-8 w-8 items-center justify-center rounded-md bg-indigo-100 text-indigo-500 transition hover:bg-indigo-200"
                                                >
                                                    <ClipboardListIcon className="h-4 w-4" />
                                                </Link>
                                                <button
                                                    type="button"
                                                    title="Edit Program"
                                                    onClick={() =>
                                                        setFormModal({
                                                            mode: 'edit',
                                                            program,
                                                        })
                                                    }
                                                    className="flex h-8 w-8 items-center justify-center rounded-md bg-green-100 text-green-600 transition hover:bg-green-200"
                                                >
                                                    <PencilIcon className="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    title="Delete Program"
                                                    onClick={() =>
                                                        setPendingDeleteId(
                                                            program.id,
                                                        )
                                                    }
                                                    className="flex h-8 w-8 items-center justify-center rounded-md bg-red-100 text-red-500 transition hover:bg-red-200"
                                                >
                                                    <TrashIcon className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <ProgramFormModal
                show={formModal !== null}
                mode={formModal?.mode}
                program={formModal?.program}
                onClose={() => setFormModal(null)}
                onSubmit={saveProgram}
                errors={errors}
            />

            <ConfirmDeleteModal
                show={pendingDeleteId !== null}
                title="Delete Program?"
                onCancel={() => setPendingDeleteId(null)}
                onConfirm={confirmDelete}
            />
        </AdminLayout>
    );
}
