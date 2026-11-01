import { useState } from 'react';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal';
import InboxIcon from '@/Components/Icons/InboxIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import PlusIcon from '@/Components/Icons/PlusIcon';
import SearchIcon from '@/Components/Icons/SearchIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import Pagination from '@/Components/Pagination';
import AdminLayout from '@/Layouts/AdminLayout';
import AccountFormModal from '@/Pages/Admin/Accounts/Partials/AccountFormModal';
import { Head, router, usePage } from '@inertiajs/react';

const ROLE_LABELS = {
    department_admin: 'Department Admin',
    office_admin: 'Office Admin',
};

// dept and office admin accounts
export default function AccountsIndex({
    accounts,
    filters = {},
    takenRoles = [],
    offices = [],
    officeAssignments = [],
}) {
    const { errors } = usePage().props;
    const [search, setSearch] = useState(filters.search ?? '');
    const [formModal, setFormModal] = useState(null); // { mode, account }
    const [processing, setProcessing] = useState(false);
    const [pendingDeleteId, setPendingDeleteId] = useState(null);
    const [pendingResetId, setPendingResetId] = useState(null);

    const visit = (params) =>
        router.get(route('accounts.index'), params, {
            preserveState: true,
            replace: true,
            only: ['accounts', 'filters', 'offices', 'officeAssignments', 'takenRoles'],
        });

    const runSearch = (value) => {
        setSearch(value);
        visit(value ? { search: value } : {});
    };

    const goToPage = (page) => visit({ search: search || undefined, page });

    const saveAccount = (form) => {
        const options = {
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setFormModal(null),
        };

        if (formModal?.mode === 'edit') {
            router.put(route('accounts.update', formModal.account.id), form, options);
        } else {
            router.post(route('accounts.store'), form, options);
        }
    };

    const confirmDelete = () => {
        router.delete(route('accounts.destroy', pendingDeleteId), {
            onFinish: () => setPendingDeleteId(null),
        });
    };

    const confirmReset = () => {
        router.post(route('accounts.reset-password', pendingResetId), {}, {
            onFinish: () => setPendingResetId(null),
        });
    };

    const rows = accounts?.data ?? [];
    const headings = ['Name', 'Role', 'User ID', 'Actions'];

    return (
        <AdminLayout>
            <Head title="Accounts" />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-extrabold uppercase tracking-wide text-green-700">
                        Admin Accounts
                    </h1>
                    <p className="mt-1 text-sm text-gray-400">
                        Create and manage department and office admins.
                    </p>
                </div>

                <button
                    type="button"
                    onClick={() => setFormModal({ mode: 'add' })}
                    className="flex items-center gap-1.5 rounded-md bg-green-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-green-800"
                >
                    <PlusIcon className="h-4 w-4" />
                    Add Account
                </button>
            </div>

            <div className="relative mt-5 max-w-sm">
                <SearchIcon className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-green-600" />
                <input
                    type="text"
                    value={search}
                    onChange={(e) => runSearch(e.target.value)}
                    placeholder="Search accounts..."
                    className="block w-full rounded-md border-green-200 py-2 pl-9 pr-3 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                />
            </div>

            <div className="mt-5 min-h-[380px] overflow-hidden rounded-xl bg-white shadow-sm">
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b border-gray-100">
                                {headings.map((heading) => (
                                    <th
                                        key={heading}
                                        className={
                                            'px-6 py-3 text-[11px] font-bold uppercase tracking-wide text-gray-400' +
                                            (heading === 'Actions' ? ' text-right' : '')
                                        }
                                    >
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 ? (
                                <tr>
                                    <td colSpan={headings.length} className="px-6 py-20">
                                        <div className="flex flex-col items-center gap-2 text-gray-300">
                                            <InboxIcon className="h-8 w-8" />
                                            <p className="text-xs text-gray-400">
                                                There are no admin accounts yet.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            ) : (
                                rows.map((account) => {
                                    return (
                                        <tr
                                            key={account.id}
                                            className="border-b border-gray-50 last:border-0"
                                        >
                                            <td className="px-6 py-4">
                                                <p className="font-semibold text-gray-800">
                                                    {account.name}
                                                </p>
                                                <p className="text-xs text-gray-400">
                                                    {account.email}
                                                </p>
                                            </td>
                                            <td className="px-6 py-4 text-gray-600">
                                                {ROLE_LABELS[account.role] ?? account.role}
                                                {account.role === 'office_admin' && <p className="mt-1 text-xs text-gray-400">{account.office ? `${account.office.code} — ${account.office.name}` : 'Unassigned — office access blocked'}</p>}
                                            </td>
                                            <td className="px-6 py-4 text-gray-600">
                                                {account.id_number}
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => setPendingResetId(account.id)}
                                                        className="rounded-md bg-gray-100 px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-wide text-gray-600 transition hover:bg-gray-200"
                                                    >
                                                        Reset Password
                                                    </button>
                                                    <button
                                                        type="button"
                                                        title="Edit Account"
                                                        onClick={() => setFormModal({ mode: 'edit', account })}
                                                        className="flex h-8 w-8 items-center justify-center rounded-md bg-green-100 text-green-600 transition hover:bg-green-200"
                                                    >
                                                        <PencilIcon className="h-4 w-4" />
                                                    </button>
                                                    <button
                                                        type="button"
                                                        title="Delete Account"
                                                        onClick={() => setPendingDeleteId(account.id)}
                                                        className="flex h-8 w-8 items-center justify-center rounded-md bg-red-100 text-red-500 transition hover:bg-red-200"
                                                    >
                                                        <TrashIcon className="h-4 w-4" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination
                    page={accounts?.current_page ?? 1}
                    totalPages={accounts?.last_page ?? 1}
                    onPageChange={goToPage}
                />
            </div>

            <AccountFormModal
                show={formModal !== null}
                mode={formModal?.mode}
                account={formModal?.account}
                takenRoles={takenRoles}
                offices={offices}
                officeAssignments={officeAssignments}
                onClose={() => setFormModal(null)}
                onSubmit={saveAccount}
                errors={errors}
                processing={processing}
            />

            <ConfirmDeleteModal
                show={pendingDeleteId !== null}
                title="Delete Account?"
                onCancel={() => setPendingDeleteId(null)}
                onConfirm={confirmDelete}
            />

            <ConfirmDeleteModal
                show={pendingResetId !== null}
                title="Reset Password?"
                description="A new password is generated and emailed to this admin. The old one stops working."
                confirmLabel="Yes, reset"
                onCancel={() => setPendingResetId(null)}
                onConfirm={confirmReset}
            />
        </AdminLayout>
    );
}
