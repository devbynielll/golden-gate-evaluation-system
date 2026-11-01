import { useEffect, useState } from 'react';
import XIcon from '@/Components/Icons/XIcon';
import Modal from '@/Components/Modal';

const EMPTY_FORM = {
    role: 'department_admin',
    name: '',
    email: '',
    id_number: '',
    office_id: '',
};

const inputClass =
    'mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600';
const labelClass = 'block text-[10px] font-bold uppercase tracking-wide text-gray-400';

// add/edit an admin account
export default function AccountFormModal({
    show,
    mode = 'add',
    account = null,
    takenRoles = [],
    offices = [],
    officeAssignments = [],
    onClose,
    onSubmit,
    errors = {},
    processing = false,
}) {
    const [form, setForm] = useState(EMPTY_FORM);
    const isEdit = mode === 'edit';

    useEffect(() => {
        if (!show) return;
        setForm(
            isEdit && account
                ? {
                      role: account.role,
                      name: account.name ?? '',
                      email: account.email ?? '',
                      id_number: account.id_number ?? '',
                      office_id: account.office_id ?? '',
                  }
                : { ...EMPTY_FORM, role: ['department_admin', 'office_admin'].find((r) => !takenRoles.includes(r)) ?? 'department_admin' },
        );
    }, [show, isEdit, account]);

    const setField = (field) => (e) =>
        setForm((prev) => ({ ...prev, [field]: e.target.value }));

    const submit = (e) => {
        e.preventDefault();
        const payload = {
            name: form.name,
            email: form.email,
            id_number: form.id_number || null,
            ...(form.role === 'office_admin' ? { office_id: form.office_id ? Number(form.office_id) : null } : {}),
        };
        onSubmit(isEdit ? payload : { ...payload, role: form.role });
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="p-6">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-extrabold uppercase tracking-wide text-green-700">
                        {isEdit ? 'Edit Account' : 'Add Account'}
                    </h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-gray-400 transition hover:text-gray-600"
                    >
                        <XIcon className="h-5 w-5" />
                    </button>
                </div>

                <form onSubmit={submit} className="mt-4">
                    <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <label className={labelClass}>Role</label>
                        <select
                            value={form.role}
                            onChange={setField('role')}
                            disabled={isEdit}
                            className={inputClass + ' disabled:bg-gray-100 disabled:text-gray-500'}
                        >
                            <option
                                value="department_admin"
                                disabled={!isEdit && takenRoles.includes('department_admin')}
                            >
                                Department Admin
                                {!isEdit && takenRoles.includes('department_admin') ? ' (already exists)' : ''}
                            </option>
                            <option
                                value="office_admin"
                            >
                                Office Admin
                            </option>
                        </select>
                        {errors.role && (
                            <p className="mt-1 text-xs text-red-500">{errors.role}</p>
                        )}

                        {form.role === 'office_admin' && (
                            <>
                                <label className={labelClass + ' mt-4'} htmlFor="account-office">Assigned Office</label>
                                <select id="account-office" value={form.office_id} onChange={setField('office_id')} required className={inputClass}>
                                    <option value="">Choose office...</option>
                                    {offices.map((office) => {
                                        const taken = officeAssignments.some((assignment) => assignment.office_id === office.id && assignment.id !== account?.id);
                                        return <option key={office.id} value={office.id} disabled={taken}>{office.code} — {office.name}{taken ? ' (assigned)' : ''}</option>;
                                    })}
                                </select>
                                {errors.office_id && <p className="mt-1 text-xs text-red-500">{errors.office_id}</p>}
                            </>
                        )}

                        <label className={labelClass + ' mt-4'}>Full Name</label>
                        <input
                            type="text"
                            value={form.name}
                            onChange={setField('name')}
                            className={inputClass}
                        />
                        {errors.name && (
                            <p className="mt-1 text-xs text-red-500">{errors.name}</p>
                        )}

                        <label className={labelClass + ' mt-4'}>Email</label>
                        <input
                            type="email"
                            value={form.email}
                            onChange={setField('email')}
                            className={inputClass}
                        />
                        {errors.email && (
                            <p className="mt-1 text-xs text-red-500">{errors.email}</p>
                        )}

                        <label className={labelClass + ' mt-4'}>
                            User ID (optional)
                        </label>
                        <input
                            type="text"
                            value={form.id_number}
                            onChange={setField('id_number')}
                            placeholder="Generated if left blank"
                            className={inputClass}
                        />
                        {errors.id_number && (
                            <p className="mt-1 text-xs text-red-500">{errors.id_number}</p>
                        )}
                    </div>

                    {!isEdit && (
                        <p className="mt-3 text-center text-xs text-gray-400">
                            A password is generated and emailed to the account.
                        </p>
                    )}

                    <div className="mt-5 flex justify-center">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-md bg-green-700 px-8 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800 disabled:opacity-60"
                        >
                            {isEdit ? 'Update' : 'Create'}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}
