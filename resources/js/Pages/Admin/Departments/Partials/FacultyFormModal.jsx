import { useEffect, useRef, useState } from 'react';
import CameraIcon from '@/Components/Icons/CameraIcon';
import PlusIcon from '@/Components/Icons/PlusIcon';
import XIcon from '@/Components/Icons/XIcon';
import Modal from '@/Components/Modal';

const EMPTY_FORM = { facultyId: '', name: '', photoUrl: null };

const initials = (name) =>
    name
        .trim()
        .split(/\s+/)
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();

// add/edit faculty modal
export default function FacultyFormModal({
    show,
    mode = 'add',
    faculty = null,
    onClose,
    onSubmit,
    errors = {},
}) {
    const [form, setForm] = useState(EMPTY_FORM);
    const [photoFile, setPhotoFile] = useState(null);
    const [preview, setPreview] = useState(null);
    const fileInput = useRef(null);
    const isEdit = mode === 'edit';

    useEffect(() => {
        if (show) {
            setForm(isEdit && faculty ? { facultyId: faculty.facultyId, name: faculty.name, photoUrl: faculty.photoUrl } : EMPTY_FORM);
            setPhotoFile(null);
            setPreview(null);
        }
    }, [show, isEdit, faculty]);

    // release the blob URL once it's no longer shown
    useEffect(() => () => preview && URL.revokeObjectURL(preview), [preview]);

    const setField = (field) => (e) =>
        setForm((prev) => ({ ...prev, [field]: e.target.value }));

    const pickPhoto = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setPhotoFile(file);
        setPreview(URL.createObjectURL(file));
    };

    const submit = (e) => {
        e.preventDefault();
        if (!form.facultyId || !form.name) return;
        const payload = { facultyId: form.facultyId, name: form.name };
        onSubmit(photoFile ? { ...payload, photo: photoFile } : payload);
    };

    const photoToShow = preview ?? form.photoUrl;

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="p-6">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-extrabold uppercase tracking-wide text-green-700">
                        {isEdit ? 'Edit Faculty' : 'Add Faculty'}
                    </h2>
                    <button
                        type="button"
                        onClick={onClose}
                        className="text-gray-400 transition hover:text-gray-600"
                    >
                        <XIcon className="h-5 w-5" />
                    </button>
                </div>

                <div className="mt-4 flex justify-center">
                    <button
                        type="button"
                        onClick={() => fileInput.current?.click()}
                        title="Upload photo"
                        className="relative flex h-20 w-20 items-center justify-center overflow-hidden rounded-full bg-gray-100 text-lg font-bold text-gray-400"
                    >
                        {photoToShow ? (
                            <img
                                src={photoToShow}
                                alt=""
                                className="h-full w-full object-cover"
                            />
                        ) : form.name ? (
                            initials(form.name)
                        ) : (
                            <CameraIcon className="h-6 w-6" />
                        )}
                        <span className="absolute -bottom-0.5 -right-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-green-600 text-white">
                            <PlusIcon className="h-3 w-3" />
                        </span>
                    </button>
                    <input
                        ref={fileInput}
                        type="file"
                        accept="image/*"
                        onChange={pickPhoto}
                        className="hidden"
                    />
                </div>
                {errors.photo && (
                    <p className="mt-1 text-center text-xs text-red-500">{errors.photo}</p>
                )}

                <form onSubmit={submit} className="mt-4">
                    <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
                        <label className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                            Faculty ID
                        </label>
                        <input
                            type="text"
                            value={form.facultyId}
                            onChange={setField('facultyId')}
                            placeholder="e.g. F12345"
                            autoFocus
                            className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                        />
                        {errors.facultyId && <p className="mt-1 text-xs text-red-500">{errors.facultyId}</p>}

                        <label className="mt-4 block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                            Faculty Name
                        </label>
                        <input
                            type="text"
                            value={form.name}
                            onChange={setField('name')}
                            className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                        />
                        {errors.name && <p className="mt-1 text-xs text-red-500">{errors.name}</p>}

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
