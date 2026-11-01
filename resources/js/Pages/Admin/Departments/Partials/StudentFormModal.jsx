import { useEffect, useState } from 'react';
import XIcon from '@/Components/Icons/XIcon';
import Modal from '@/Components/Modal';
import CourseSearchSelect from './CourseSearchSelect';

const EMPTY_FORM = {
    studentId: '',
    email: '',
    firstName: '',
    middleName: '',
    lastName: '',
    suffix: '',
    isIrregular: false,
    year: '',
    block: '',
    enrolledCourses: [],
};

const YEAR_OPTIONS = [
    { value: '1', label: '1st Year' },
    { value: '2', label: '2nd Year' },
    { value: '3', label: '3rd Year' },
    { value: '4', label: '4th Year' },
];

// register/edit student modal; irregulars get a second column for courses
export default function StudentFormModal({
    show,
    mode = 'add',
    student = null,
    classes = [],
    homeDepartmentId = null,
    homeProgramId = null,
    onClose,
    onSubmit,
    errors = {},
}) {
    const [form, setForm] = useState(EMPTY_FORM);
    const [submitting, setSubmitting] = useState(false);
    const [blockError, setBlockError] = useState('');
    const isEdit = mode === 'edit';

    useEffect(() => {
        if (show) {
            setForm({ ...EMPTY_FORM, ...(student ?? {}) });
            setSubmitting(false);
            setBlockError('');
        }
    }, [show, student]);

    const setField = (field) => (e) =>
        setForm((prev) => ({ ...prev, [field]: e.target.value }));

    const setIrregular = (isIrregular) =>
        setForm((prev) => ({ ...prev, isIrregular, year: '', block: '' }));

    const setEnrolledCourses = (enrolledCourses) =>
        setForm((prev) => ({ ...prev, enrolledCourses }));

    const blockOptions = [
        ...new Set(
            classes
                .filter((classSection) => classSection.year === form.year)
                .map((classSection) => classSection.block),
        ),
    ];

    const submit = (e) => {
        e.preventDefault();
        if (!form.studentId || !form.firstName || !form.lastName) return;
        if (form.isIrregular && form.enrolledCourses.length === 0) return;
        // each course needs its block
        if (form.isIrregular && form.enrolledCourses.some((c) => c.offerings?.length > 0 && !c.classSectionId)) {
            setBlockError('Choose the block the student attends for each course.');
            return;
        }
        setBlockError('');
        if (!form.isIrregular && (!form.year || !form.block)) return;
        if (submitting) return;

        const { enrolledCourses, ...rest } = form;
        setSubmitting(true);
        onSubmit(
            {
                ...rest,
                enrollments: enrolledCourses.map((course) => ({
                    subjectId: course.id,
                    classSectionId: course.classSectionId ?? null,
                })),
            },
            { onFinish: () => setSubmitting(false) },
        );
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth={form.isIrregular ? '4xl' : 'md'}>
            <div className="p-6">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-extrabold uppercase tracking-wide text-green-700">
                        {isEdit ? 'Edit Student' : 'Register Student'}
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
                    <div className={form.isIrregular ? 'grid grid-cols-1 gap-6 md:grid-cols-2' : ''}>
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                        Student ID
                                    </label>
                                    <input
                                        type="text"
                                        value={form.studentId}
                                        onChange={setField('studentId')}
                                        placeholder="e.g. CE1123456"
                                        autoFocus
                                        className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                                    />
                                    {errors.studentId && <p className="mt-1 text-xs text-red-500">{errors.studentId}</p>}
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                        Email
                                    </label>
                                    <input
                                        type="email"
                                        value={form.email}
                                        onChange={setField('email')}
                                        className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                                    />
                                    {errors.email && <p className="mt-1 text-xs text-red-500">{errors.email}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                        First Name
                                    </label>
                                    <input
                                        type="text"
                                        value={form.firstName}
                                        onChange={setField('firstName')}
                                        className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                                    />
                                    {errors.firstName && <p className="mt-1 text-xs text-red-500">{errors.firstName}</p>}
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                        Middle Name
                                    </label>
                                    <input
                                        type="text"
                                        value={form.middleName}
                                        onChange={setField('middleName')}
                                        className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-[2fr_1fr] gap-4">
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                        Last Name
                                    </label>
                                    <input
                                        type="text"
                                        value={form.lastName}
                                        onChange={setField('lastName')}
                                        className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                                    />
                                    {errors.lastName && <p className="mt-1 text-xs text-red-500">{errors.lastName}</p>}
                                </div>
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                        Suffix
                                    </label>
                                    <input
                                        type="text"
                                        value={form.suffix}
                                        onChange={setField('suffix')}
                                        className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                    Student Type
                                </label>
                                <div className="mt-1 grid grid-cols-2 gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setIrregular(false)}
                                        className={`rounded-md border px-3 py-2 text-xs font-bold uppercase tracking-wide transition ${
                                            !form.isIrregular
                                                ? 'border-green-600 bg-green-50 text-green-700'
                                                : 'border-gray-200 text-gray-400 hover:text-gray-600'
                                        }`}
                                    >
                                        Regular
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setIrregular(true)}
                                        className={`rounded-md border px-3 py-2 text-xs font-bold uppercase tracking-wide transition ${
                                            form.isIrregular
                                                ? 'border-green-600 bg-green-50 text-green-700'
                                                : 'border-gray-200 text-gray-400 hover:text-gray-600'
                                        }`}
                                    >
                                        Irregular
                                    </button>
                                </div>
                            </div>

                            {!form.isIrregular && (
                                <div>
                                    <label className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                        Section Assignment
                                    </label>
                                    <div className="mt-1 grid grid-cols-2 gap-4">
                                        <select
                                            value={form.year}
                                            onChange={(e) =>
                                                setForm((prev) => ({
                                                    ...prev,
                                                    year: e.target.value,
                                                    block: '',
                                                }))
                                            }
                                            className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-green-600 focus:ring-green-600"
                                        >
                                            <option value="">Select Year</option>
                                            {YEAR_OPTIONS.map((option) => (
                                                <option key={option.value} value={option.value}>
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>

                                        <select
                                            value={form.block}
                                            onChange={setField('block')}
                                            disabled={!form.year}
                                            className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-green-600 focus:ring-green-600 disabled:bg-gray-100"
                                        >
                                            <option value="">Select Block</option>
                                            {blockOptions.map((block) => (
                                                <option key={block} value={block}>
                                                    {block}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    {(errors.year || errors.block) && (
                                        <p className="mt-1 text-xs text-red-500">
                                            {errors.year || errors.block}
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>

                        {form.isIrregular && (
                            <div className="border-t border-gray-100 pt-4 md:border-l md:border-t-0 md:pl-6 md:pt-0">
                                <CourseSearchSelect
                                    selected={form.enrolledCourses}
                                    onChange={setEnrolledCourses}
                                    error={errors.subjectIds || blockError}
                                    defaultDepartmentId={homeDepartmentId}
                                    defaultProgramId={homeProgramId}
                                />
                            </div>
                        )}
                    </div>

                    <div className="mt-5 flex justify-center">
                        <button
                            type="submit"
                            disabled={submitting}
                            className="rounded-md bg-green-700 px-8 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {submitting ? 'Saving...' : isEdit ? 'Update' : 'Save'}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}
