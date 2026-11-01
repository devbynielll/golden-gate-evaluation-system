import { useEffect, useState } from 'react';
import XIcon from '@/Components/Icons/XIcon';
import Modal from '@/Components/Modal';

const EMPTY_FORM = { text: '' };

// add/edit question modal
export default function QuestionFormModal({
    show,
    mode = 'add',
    categoryName = '',
    question = null,
    onClose,
    onSubmit,
    onDelete,
    errors = {},
}) {
    const [form, setForm] = useState(EMPTY_FORM);
    const isEdit = mode === 'edit';

    useEffect(() => {
        if (show) {
            setForm(isEdit && question ? question : EMPTY_FORM);
        }
    }, [show, isEdit, question]);

    const submit = (e) => {
        e.preventDefault();
        if (!form.text) return;
        onSubmit(form);
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="p-6">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-extrabold uppercase tracking-wide text-green-700">
                        {isEdit ? 'Edit Question' : 'Add Question'}
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
                            Category Name
                        </label>
                        <input
                            type="text"
                            value={categoryName}
                            disabled
                            className="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 text-sm text-gray-500 shadow-sm"
                        />

                        <label className="mt-4 block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                            Question
                        </label>
                        <input
                            type="text"
                            value={form.text}
                            onChange={(e) =>
                                setForm({ text: e.target.value })
                            }
                            placeholder="Enter the question here..."
                            autoFocus
                            className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                        />
                        {errors.text && <p className="mt-1 text-xs text-red-500">{errors.text}</p>}
                    </div>

                    <div className="mt-5 flex justify-center gap-3">
                        <button
                            type="submit"
                            className="rounded-md bg-green-700 px-8 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800"
                        >
                            {isEdit ? 'Update' : 'Save'}
                        </button>
                        {isEdit && (
                            <button
                                type="button"
                                onClick={onDelete}
                                className="rounded-md bg-red-100 px-8 py-2.5 text-sm font-bold uppercase tracking-wide text-red-500 shadow-sm transition hover:bg-red-200"
                            >
                                Delete
                            </button>
                        )}
                    </div>
                </form>
            </div>
        </Modal>
    );
}
