import { useState } from 'react';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal';
import InboxIcon from '@/Components/Icons/InboxIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import PlusIcon from '@/Components/Icons/PlusIcon';
import SearchIcon from '@/Components/Icons/SearchIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import AdminLayout from '@/Layouts/AdminLayout';
import Pagination from '@/Components/Pagination';
import ImportModal from '@/Components/ImportModal';
import OfficeFormModal from '@/Pages/Admin/Offices/Partials/OfficeFormModal';
import { Head, Link, router, usePage } from '@inertiajs/react';

const OFFICE_IMPORT_FIELDS = [
    { key: 'code', label: 'Office Code', required: true },
    { key: 'name', label: 'Office Name', required: true },
    { key: 'departmentCode', label: 'Department (code, full label, or All Departments)' },
];

// offices list page
export default function OfficesIndex({ offices, filters = {}, departments = [] }) {
    const { errors } = usePage().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const [formModal, setFormModal] = useState(null); // { mode, office }
    const [pendingDeleteId, setPendingDeleteId] = useState(null);
    const [importOpen, setImportOpen] = useState(false);

    const visit = (params) =>
        router.get(route('offices.index'), params, {
            preserveState: true,
            replace: true,
            only: ['offices', 'filters'],
        });

    const runSearch = (value) => {
        setSearch(value);
        visit(value ? { search: value } : {});
    };

    const goToPage = (page) => visit({ search: search || undefined, page });

    const saveOffice = (form) => {
        if (formModal?.mode === 'edit') {
            router.put(route('offices.update', formModal.office.id), form, {
                onSuccess: () => setFormModal(null),
            });
        } else {
            router.post(route('offices.store'), form, {
                onSuccess: () => setFormModal(null),
            });
        }
    };

    const confirmDelete = () => {
        router.delete(route('offices.destroy', pendingDeleteId), {
            onFinish: () => setPendingDeleteId(null),
        });
    };

    const rows = offices?.data ?? [];

    return (
        <AdminLayout>
            <Head title="Offices" />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-extrabold uppercase tracking-wide text-green-700">
                        Institutional Offices
                    </h1>
                    <p className="mt-1 text-sm text-gray-400">
                        Manage institutional offices.
                    </p>
                </div>

                <div className="flex items-center gap-2">
                    <button
                        type="button"
                        onClick={() => setImportOpen(true)}
                        className="flex items-center gap-1.5 rounded-md border border-green-600 px-4 py-2.5 text-sm font-bold text-green-700 shadow-sm transition hover:bg-green-50"
                    >
                        Import
                    </button>
                    <button
                        type="button"
                        onClick={() => setFormModal({ mode: 'add' })}
                        className="flex items-center gap-1.5 rounded-md bg-green-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-green-800"
                    >
                        <PlusIcon className="h-4 w-4" />
                        Add Office
                    </button>
                </div>
            </div>

            <div className="relative mt-5 max-w-sm">
                <SearchIcon className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-green-600" />
                <input
                    type="text"
                    value={search}
                    onChange={(e) => runSearch(e.target.value)}
                    placeholder="Search offices..."
                    className="block w-full rounded-md border-green-200 py-2 pl-9 pr-3 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                />
            </div>

            {errors.office && <p role="alert" className="mt-3 text-sm text-red-600">{errors.office}</p>}
            <div className="mt-5 min-h-[380px] overflow-hidden rounded-xl bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-gray-100">
                                <th className="px-6 py-3 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                    Code
                                </th>
                                <th className="px-6 py-3 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                    Office Name
                                </th>
                                <th className="px-6 py-3 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                    Department
                                </th>
                                <th className="px-6 py-3 text-right text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td colSpan={4} className="px-6 py-20">
                                        <div className="flex flex-col items-center gap-2 text-gray-300">
                                            <InboxIcon className="h-8 w-8" />
                                            <p className="text-xs text-gray-400">
                                                There are no offices available to review.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                rows.map((office) => (
                                    <tr
                                        key={office.id}
                                        className="border-b border-gray-50 last:border-0"
                                    >
                                        <td className="px-6 py-4 font-semibold text-gray-800">
                                            {office.code}
                                        </td>
                                        <td className="px-6 py-4 font-semibold text-blue-600">
                                            {office.name}
                                        </td>
                                        <td className="px-6 py-4 text-gray-600">
                                            {office.department?.name ?? (
                                                <span className="font-semibold text-green-600">
                                                    All Departments
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center justify-end gap-2">
                                                <Link href={route('offices.staff.index', office.id)} className="rounded-md bg-green-100 px-3 py-2 text-xs font-bold text-green-700" aria-label={`Manage staff for ${office.name}`}>Staff</Link>
                                                <button
                                                    type="button"
                                                    title="Edit Office"
                                                    onClick={() =>
                                                        setFormModal({
                                                            mode: 'edit',
                                                            office,
                                                        })
                                                    }
                                                    className="flex h-8 w-8 items-center justify-center rounded-md bg-green-100 text-green-600 transition hover:bg-green-200"
                                                >
                                                    <PencilIcon className="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    title="Delete Office"
                                                    onClick={() =>
                                                        setPendingDeleteId(
                                                            office.id,
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

                <Pagination
                    page={offices?.current_page ?? 1}
                    totalPages={offices?.last_page ?? 1}
                    onPageChange={goToPage}
                />
            </div>

            <OfficeFormModal
                show={formModal !== null}
                mode={formModal?.mode}
                office={formModal?.office}
                departments={departments}
                onClose={() => setFormModal(null)}
                onSubmit={saveOffice}
                errors={errors}
            />

            <ConfirmDeleteModal
                show={pendingDeleteId !== null}
                title="Delete Office?"
                onCancel={() => setPendingDeleteId(null)}
                onConfirm={confirmDelete}
            />

            <ImportModal
                show={importOpen}
                title="Import Offices"
                previewUrl={route('offices.import.preview')}
                importUrl={route('offices.import.store')}
                fields={OFFICE_IMPORT_FIELDS}
                onClose={() => setImportOpen(false)}
            />
        </AdminLayout>
    );
}
