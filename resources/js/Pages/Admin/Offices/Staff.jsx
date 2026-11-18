import { useState } from 'react';
import { DialogTitle } from '@headlessui/react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import Modal from '@/Components/Modal';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal';
import Pagination from '@/Components/Pagination';
import PlusIcon from '@/Components/Icons/PlusIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import XIcon from '@/Components/Icons/XIcon';

export default function OfficeStaff({ office, staff, filters = {} }) {
    const { auth, errors: pageErrors = {} } = usePage().props;
    const isSuper = auth.user.role === 'super_admin';
    const [editor, setEditor] = useState(null);
    const [pendingArchive, setPendingArchive] = useState(null);
    const [archiving, setArchiving] = useState(false);
    const [loading, setLoading] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? 'active');
    const form = useForm({ name: '', position: '' });
    const closeEditor = () => { if (!form.processing) { setEditor(null); form.reset(); form.clearErrors(); } };
    const edit = (member = {}) => {
        form.clearErrors(); form.setData({ name: member.name ?? '', position: member.position ?? '' }); setEditor(member);
    };
    const save = (event) => {
        event.preventDefault();
        if (form.processing) return;
        const options = { preserveScroll: true, onSuccess: () => { setEditor(null); form.reset(); } };
        if (editor.id) form.put(route('offices.staff.update', [office.id, editor.id]), options);
        else form.post(route('offices.staff.store', office.id), options);
    };
    const visit = (params) => router.get(route('offices.staff.index', office.id), params, {
        preserveState: true, preserveScroll: true, only: ['office', 'staff', 'filters'],
        onStart: () => setLoading(true), onFinish: () => setLoading(false),
    });
    const archive = () => {
        if (archiving) return;
        router.patch(route('offices.staff.archive', [office.id, pendingArchive.id]), {}, {
            preserveScroll: true, onStart: () => setArchiving(true),
            onFinish: () => { setArchiving(false); setPendingArchive(null); },
        });
    };
    return (
        <AdminLayout>
            <Head title={`${office.code} Staff`} />
            <div className="mb-4 flex flex-wrap gap-4 text-xs font-semibold text-green-700">
                {isSuper && <Link href={route('offices.index')}>Back to Offices</Link>}
                <Link href={route('criteria.staff', { office_id: office.id })}>Staff Criteria</Link>
            </div>
            <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border-l-4 border-green-600 bg-white p-5 shadow-sm">
                <div><h1 className="text-lg font-extrabold text-gray-800">{office.name} — Staff</h1>
                    <p className="mt-1 text-xs text-gray-500">Register names and positions. Archived staff remain listed for historical reference.</p></div>
                <button type="button" onClick={() => edit()} className="flex items-center gap-1.5 rounded-md bg-green-700 px-4 py-2.5 text-xs font-bold uppercase text-white hover:bg-green-800"><PlusIcon className="h-4 w-4" />Add Staff</button>
            </div>
            <form className="my-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 shadow-sm" onSubmit={(event) => { event.preventDefault(); visit({ search, status }); }}>
                <label className="text-xs font-semibold text-gray-600">Search names or positions<input type="search" maxLength={100} value={search} onChange={(event) => setSearch(event.target.value)} className="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-green-600 focus:ring-green-600" /></label>
                <label className="text-xs font-semibold text-gray-600">Status<select value={status} onChange={(event) => setStatus(event.target.value)} className="mt-1 block rounded-md border-gray-300 text-sm focus:border-green-600 focus:ring-green-600"><option value="active">Active</option><option value="archived">Archived</option><option value="all">All staff</option></select></label>
                <button type="submit" disabled={loading} className="rounded-md bg-green-700 px-4 py-2 text-xs font-bold uppercase text-white disabled:opacity-50">{loading ? 'Loading…' : 'Apply Filter'}</button>
            </form>
            {(pageErrors.search || pageErrors.status || pageErrors.staff) && <p role="alert" className="mb-3 text-sm text-red-600">{pageErrors.search || pageErrors.status || pageErrors.staff}</p>}
            <div className="overflow-hidden rounded-xl bg-white shadow-sm" aria-busy={loading}>
                {loading && <p role="status" className="px-5 py-2 text-sm text-gray-500">Loading staff…</p>}
                <div className="overflow-x-auto"><table className="w-full text-left text-sm">
                    <thead><tr className="border-b border-gray-100 bg-gray-50">{['Full Name', 'Position', 'Status', 'Actions'].map((label) => <th scope="col" key={label} className="px-5 py-3 text-xs font-bold uppercase text-gray-500">{label}</th>)}</tr></thead>
                    <tbody>{staff.data.length ? staff.data.map((member) => <tr key={member.id} className="border-b border-gray-100">
                        <td className="px-5 py-3 font-semibold text-gray-800">{member.name}</td><td className="px-5 py-3 text-gray-600">{member.position}</td>
                        <td className="px-5 py-3 text-xs font-bold text-green-700">{member.is_active ? 'Active' : 'Archived'}</td>
                        <td className="whitespace-nowrap px-5 py-3"><button type="button" onClick={() => edit(member)} aria-label={`Edit ${member.name}`} className="mr-3 inline-flex items-center gap-1 rounded-md bg-green-100 px-3 py-2 text-xs font-bold text-green-700"><PencilIcon className="h-4 w-4" />Edit</button>
                            {member.is_active && <button type="button" disabled={archiving} onClick={() => setPendingArchive(member)} aria-label={`Archive ${member.name}`} className="rounded-md bg-gray-100 px-3 py-2 text-xs font-bold text-gray-600 disabled:opacity-50">Archive</button>}</td>
                    </tr>) : <tr><td colSpan={4} className="px-5 py-14 text-center text-sm text-gray-500">No staff match this selection. Use Add Staff to register a staff member.</td></tr>}</tbody>
                </table></div>
                <Pagination page={staff.current_page} totalPages={staff.last_page} onPageChange={(page) => visit({ search: filters.search, status: filters.status, page })} />
            </div>
            <Modal show={editor !== null} onClose={closeEditor} maxWidth="md">
                <div className="p-6"><div className="flex items-center justify-between gap-3"><DialogTitle className="text-lg font-extrabold uppercase text-green-700">{editor?.id ? 'Edit Staff' : 'Add Staff'}</DialogTitle>
                    <button type="button" disabled={form.processing} onClick={closeEditor} aria-label="Close staff form" className="text-gray-500"><XIcon className="h-5 w-5" /></button></div>
                    <p className="mt-2 text-xs text-gray-500">{office.code} — {office.name}</p>
                    <form onSubmit={save} className="mt-4 space-y-4" aria-busy={form.processing}>
                        {[['name', 'Full Name'], ['position', 'Position']].map(([field, label]) => <div key={field}>
                            <label htmlFor={`staff-${field}`} className="block text-xs font-bold uppercase text-gray-500">{label}</label>
                            <input id={`staff-${field}`} type="text" required maxLength={255} autoFocus={field === 'name'} disabled={form.processing} value={form.data[field]} onChange={(event) => form.setData(field, event.target.value)} aria-invalid={Boolean(form.errors[field])} aria-describedby={form.errors[field] ? `staff-${field}-error` : undefined} className="mt-1 block w-full rounded-md border-gray-300 text-sm focus:border-green-600 focus:ring-green-600" />
                            {form.errors[field] && <p id={`staff-${field}-error`} role="alert" className="mt-1 text-xs text-red-600">{form.errors[field]}</p>}
                        </div>)}
                        {form.errors.office_id && <p role="alert" className="text-xs text-red-600">{form.errors.office_id}</p>}
                        <button type="submit" disabled={form.processing} className="rounded-md bg-green-700 px-6 py-2.5 text-sm font-bold uppercase text-white disabled:opacity-50">{form.processing ? 'Saving…' : 'Save'}</button>
                    </form>
                </div>
            </Modal>
            <ConfirmDeleteModal show={pendingArchive !== null} title="Archive staff member?" description={`${pendingArchive?.name ?? ''} will become inactive. Historical references are retained.`} confirmLabel="Archive staff" onConfirm={archive} onCancel={() => { if (!archiving) setPendingArchive(null); }} />
        </AdminLayout>
    );
}
