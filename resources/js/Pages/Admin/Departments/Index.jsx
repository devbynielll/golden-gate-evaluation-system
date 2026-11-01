import { useState } from 'react';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal';
import ClipboardListIcon from '@/Components/Icons/ClipboardListIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import PlusIcon from '@/Components/Icons/PlusIcon';
import SearchIcon from '@/Components/Icons/SearchIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import AdminLayout from '@/Layouts/AdminLayout';
import DepartmentFormModal from '@/Pages/Admin/Departments/Partials/DepartmentFormModal';
import { Head, Link, router, usePage } from '@inertiajs/react';

// departments list page
export default function DepartmentsIndex({ departments = [], filters = {} }) {
    const { errors, auth } = usePage().props;
    const isSuper = auth.user.role === 'super_admin'; // dept admin can't add/edit/delete departments
    const [search, setSearch] = useState(filters.search ?? '');
    const [formModal, setFormModal] = useState(null); // { mode, department }
    const [pendingDeleteId, setPendingDeleteId] = useState(null);

    const runSearch = (value) => {
        setSearch(value);
        router.get(
            route('departments.index'),
            value ? { search: value } : {},
            { preserveState: true, replace: true, only: ['departments', 'filters'] },
        );
    };

    const saveDepartment = (form) => {
        if (formModal?.mode === 'edit') {
            router.put(route('departments.update', formModal.department.id), form, {
                onSuccess: () => setFormModal(null),
            });
        } else {
            router.post(route('departments.store'), form, {
                onSuccess: () => setFormModal(null),
            });
        }
    };

    const confirmDelete = () => {
        router.delete(route('departments.destroy', pendingDeleteId), {
            onFinish: () => setPendingDeleteId(null),
        });
    };

    return (
        <AdminLayout>
            <Head title="Departments" />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-extrabold uppercase tracking-wide text-green-700">
                        Institutional Departments
                    </h1>
                    <p className="mt-1 text-sm text-gray-400">
                        Manage departments and their academic programs.
                    </p>
                </div>

                {isSuper && (
                    <button
                        type="button"
                        onClick={() => setFormModal({ mode: 'add' })}
                        className="flex items-center gap-1.5 rounded-md bg-green-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-green-800"
                    >
                        <PlusIcon className="h-4 w-4" />
                        Add Department
                    </button>
                )}
            </div>

            <div className="relative mt-5 max-w-sm">
                <SearchIcon className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-green-600" />
                <input
                    type="text"
                    value={search}
                    onChange={(e) => runSearch(e.target.value)}
                    placeholder="Search departments..."
                    className="block w-full rounded-md border-green-200 py-2 pl-9 pr-3 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                />
            </div>

            <div className="mt-5 min-h-[380px] overflow-hidden rounded-xl bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-gray-100">
                                <th className="px-6 py-3 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                    Code
                                </th>
                                <th className="px-6 py-3 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                    Department Name
                                </th>
                                <th className="px-6 py-3 text-right text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {departments.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={3}
                                        className="px-6 py-14 text-center text-xs text-gray-400"
                                    >
                                        No departments match your search.
                                    </td>
                                </tr>
                            ) : (
                                departments.map((department) => (
                                    <tr
                                        key={department.id}
                                        className="border-b border-gray-50 last:border-0"
                                    >
                                        <td className="px-6 py-4 font-semibold text-gray-800">
                                            {department.code}
                                        </td>
                                        <td className="px-6 py-4">
                                            <Link
                                                href={route('departments.show', department.id)}
                                                className="font-semibold text-blue-600 hover:underline"
                                            >
                                                {department.name}
                                            </Link>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center justify-end gap-2">
                                                <Link
                                                    href={route('departments.show', department.id)}
                                                    title="Manage Department"
                                                    className="flex h-8 w-8 items-center justify-center rounded-md bg-indigo-100 text-indigo-500 transition hover:bg-indigo-200"
                                                >
                                                    <ClipboardListIcon className="h-4 w-4" />
                                                </Link>
                                                {isSuper && (
                                                    <>
                                                <button
                                                    type="button"
                                                    title="Edit Department"
                                                    onClick={() =>
                                                        setFormModal({
                                                            mode: 'edit',
                                                            department,
                                                        })
                                                    }
                                                    className="flex h-8 w-8 items-center justify-center rounded-md bg-green-100 text-green-600 transition hover:bg-green-200"
                                                >
                                                    <PencilIcon className="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    title="Delete Department"
                                                    onClick={() =>
                                                        setPendingDeleteId(
                                                            department.id,
                                                        )
                                                    }
                                                    className="flex h-8 w-8 items-center justify-center rounded-md bg-red-100 text-red-500 transition hover:bg-red-200"
                                                >
                                                    <TrashIcon className="h-4 w-4" />
                                                </button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <DepartmentFormModal
                show={formModal !== null}
                mode={formModal?.mode}
                department={formModal?.department}
                onClose={() => setFormModal(null)}
                onSubmit={saveDepartment}
                errors={errors}
            />

            <ConfirmDeleteModal
                show={pendingDeleteId !== null}
                title="Delete Department?"
                onCancel={() => setPendingDeleteId(null)}
                onConfirm={confirmDelete}
            />
        </AdminLayout>
    );
}
