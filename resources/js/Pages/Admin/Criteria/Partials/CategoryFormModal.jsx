import { useEffect, useState } from 'react';
import { DialogTitle } from '@headlessui/react';
import XIcon from '@/Components/Icons/XIcon';
import Modal from '@/Components/Modal';

const EMPTY_FORM = { name: '', section: '' };

// add/edit category modal
export default function CategoryFormModal({
    show,
    mode = 'add',
    category = null,
    onClose,
    onSubmit,
    errors = {},
    processing = false,
}) {
    const [form, setForm] = useState(EMPTY_FORM);
    const isEdit = mode === 'edit';

    useEffect(() => {
        if (show) {
            setForm(isEdit && category ? category : EMPTY_FORM);
        }
    }, [show, isEdit, category]);

    const setField = (field) => (e) =>
        setForm((prev) => ({ ...prev, [field]: e.target.value }));

    const submit = (e) => {
        e.preventDefault();
        if (processing) return;
        if (!form.name || !form.section) return;
        onSubmit(form);
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="p-6">
                <div className="flex items-center justify-between">
                    <DialogTitle className="text-lg font-extrabold uppercase tracking-wide text-green-700">
                        {isEdit ? 'Edit Category' : 'Add Category'}
                    </DialogTitle>
                    <button
                        type="button"
                        onClick={onClose}
                        disabled={processing}
                        aria-label="Close category form"
                        className="text-gray-400 transition hover:text-gray-600"
                    >
                        <XIcon className="h-5 w-5" />
                    </button>
                </div>

                <form onSubmit={submit} className="mt-4" aria-busy={processing}>
                    <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <label htmlFor="criteria-category-name" className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                            Category Name
                        </label>
                        <input
                            type="text"
                            id="criteria-category-name"
                            aria-label="Category Name"
                            disabled={processing}
                            aria-invalid={Boolean(errors.name)}
                            aria-describedby={errors.name ? 'criteria-name-error' : undefined}
                            value={form.name}
                            onChange={setField('name')}
                            placeholder="e.g. Teaching Effectiveness"
                            autoFocus
                            className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                        />
                        {errors.name && <p id="criteria-name-error" role="alert" className="mt-1 text-xs text-red-500">{errors.name}</p>}

                        <label htmlFor="criteria-category-section" className="mt-4 block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                            Section Number
                        </label>
                        <input
                            type="text"
                            id="criteria-category-section"
                            aria-label="Section Number"
                            disabled={processing}
                            aria-invalid={Boolean(errors.section)}
                            aria-describedby={errors.section ? 'criteria-section-error' : undefined}
                            value={form.section}
                            onChange={setField('section')}
                            placeholder="e.g. 1"
                            className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                        />
                        {errors.section && <p id="criteria-section-error" role="alert" className="mt-1 text-xs text-red-500">{errors.section}</p>}
                    </div>

                    <div className="mt-5 flex justify-center">
                        <button
                            type="submit"
                            disabled={processing}
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
