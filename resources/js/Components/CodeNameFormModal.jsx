import { useEffect, useRef, useState } from 'react';
import XIcon from '@/Components/Icons/XIcon';
import Modal from '@/Components/Modal';

const EMPTY_FORM = { code: '', name: '' };

// shared code + name modal
export default function CodeNameFormModal({
    show,
    mode = 'add',
    initial = null,
    onClose,
    onSubmit,
    titleAdd,
    titleEdit,
    codeLabel = 'Code',
    nameLabel = 'Name',
    codePlaceholder = '',
    namePlaceholder = '',
    errors = {},
    suggestions = {},
}) {
    const [form, setForm] = useState(EMPTY_FORM);
    const isEdit = mode === 'edit';
    // tracks whether the user typed their own name.
    const nameTouchedRef = useRef(false);

    useEffect(() => {
        if (show) {
            setForm(isEdit && initial ? initial : EMPTY_FORM);
            nameTouchedRef.current = isEdit;
        }
    }, [show, isEdit, initial]);

    const setField = (field) => (e) => {
        const value = e.target.value;
        if (field === 'name') {
            nameTouchedRef.current = true;
            setForm((prev) => ({ ...prev, name: value }));
            return;
        }
        setForm((prev) => {
            const matched = suggestions[value.trim().toUpperCase()];
            return {
                ...prev,
                code: value,
                name: !nameTouchedRef.current && matched ? matched : prev.name,
            };
        });
    };

    const submit = (e) => {
        e.preventDefault();
        if (!form.code || !form.name) return;
        onSubmit(form);
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="p-6">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-extrabold uppercase tracking-wide text-green-700">
                        {isEdit ? titleEdit : titleAdd}
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
                            {codeLabel}
                        </label>
                        <input
                            type="text"
                            value={form.code}
                            onChange={setField('code')}
                            placeholder={codePlaceholder}
                            autoFocus
                            className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                        />
                        {errors.code && (
                            <p className="mt-1 text-xs text-red-500">{errors.code}</p>
                        )}

                        <label className="mt-4 block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                            {nameLabel}
                        </label>
                        <input
                            type="text"
                            value={form.name}
                            onChange={setField('name')}
                            placeholder={namePlaceholder}
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
