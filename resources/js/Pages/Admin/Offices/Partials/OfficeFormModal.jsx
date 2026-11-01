import { useEffect, useRef, useState } from 'react';
import XIcon from '@/Components/Icons/XIcon';
import Modal from '@/Components/Modal';
import { OFFICE_SUGGESTIONS } from '@/Constants/codeNameSuggestions';

const EMPTY_FORM = { department_id: 'all', code: '', name: '' };

// add/edit office modal — "all" means no department (applies to everyone)
export default function OfficeFormModal({
    show,
    mode = 'add',
    office = null,
    departments = [],
    onClose,
    onSubmit,
    errors = {},
}) {
    const [form, setForm] = useState(EMPTY_FORM);
    const isEdit = mode === 'edit';
    // tracks whether the user typed their own name, so auto-fill won't clobber it
    const nameTouchedRef = useRef(false);

    useEffect(() => {
        if (show) {
            setForm(
                isEdit && office
                    ? {
                          department_id:
                              office.department_id ?? office.department?.id ?? 'all',
                          code: office.code ?? '',
                          name: office.name ?? '',
                      }
                    : EMPTY_FORM,
            );
            nameTouchedRef.current = isEdit;
        }
    }, [show, isEdit, office]);

    const setField = (field) => (e) => {
        const value = e.target.value;
        if (field === 'name') {
            nameTouchedRef.current = true;
            setForm((prev) => ({ ...prev, name: value }));
            return;
        }
        if (field === 'code') {
            setForm((prev) => {
                const matched = OFFICE_SUGGESTIONS[value.trim().toUpperCase()];
                return {
                    ...prev,
                    code: value,
                    name: !nameTouchedRef.current && matched ? matched : prev.name,
                };
            });
            return;
        }
        setForm((prev) => ({ ...prev, [field]: value }));
    };

    const submit = (e) => {
        e.preventDefault();
        if (!form.department_id || !form.code || !form.name) return;
        onSubmit({
            ...form,
            department_id: form.department_id === 'all' ? null : form.department_id,
        });
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="p-6">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-extrabold uppercase tracking-wide text-green-700">
                        {isEdit ? 'Edit Office' : 'Add Office'}
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
                        <label className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                            Department
                        </label>
                        <select
                            value={form.department_id}
                            onChange={setField('department_id')}
                            className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-green-600 focus:ring-green-600"
                        >
                            <option value="">Select a department...</option>
                            <option value="all">All Departments</option>
                            {departments.map((department) => (
                                <option key={department.id} value={department.id}>
                                    {department.code} - {department.name}
                                </option>
                            ))}
                        </select>
                        {errors.department_id && (
                            <p className="mt-1 text-xs text-red-500">
                                {errors.department_id}
                            </p>
                        )}

                        <label className="mt-4 block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                            Office Code
                        </label>
                        <input
                            type="text"
                            value={form.code}
                            onChange={setField('code')}
                            placeholder="e.g. REC"
                            autoFocus
                            className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                        />
                        {errors.code && (
                            <p className="mt-1 text-xs text-red-500">{errors.code}</p>
                        )}

                        <label className="mt-4 block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                            Office Name
                        </label>
                        <input
                            type="text"
                            value={form.name}
                            onChange={setField('name')}
                            placeholder="e.g. Record's Office"
                            className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                        />
                        {errors.name && (
                            <p className="mt-1 text-xs text-red-500">{errors.name}</p>
                        )}
                    </div>

                    <div className="mt-5 flex justify-center">
                        <button
                            type="submit"
                            className="rounded-md bg-green-700 px-8 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800"
                        >
                            {isEdit ? 'Update' : 'Save'}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}
