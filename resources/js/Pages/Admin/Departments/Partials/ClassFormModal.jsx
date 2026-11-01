import { useEffect, useState } from 'react';
import XIcon from '@/Components/Icons/XIcon';
import Modal from '@/Components/Modal';

export const classFormState = (section = null) => ({
    year: section?.year ?? '',
    block: section?.block ?? '',
    assignments: (section?.assignments ?? section?.subjectIds?.map((subjectId) => ({
        subjectId, instructorId: section.instructorId ?? null,
    })) ?? []).map((assignment) => ({ ...assignment })),
    scheduleVersion: section?.scheduleVersion ?? null,
});

// Directory qualifications are separate from actual teaching assignments.
export default function ClassFormModal({
    show, mode = 'add', classSection = null, subjects = [], faculties = [],
    onClose, onSubmit, errors = {}, readOnly = false,
}) {
    const isEdit = mode === 'edit';
    const [form, setForm] = useState(() => classFormState(isEdit ? classSection : null));
    useEffect(() => {
        if (show) setForm(classFormState(isEdit ? classSection : null));
    }, [show, isEdit, classSection]);

    const setField = (field) => (e) => setForm((prev) => ({ ...prev, [field]: e.target.value }));
    // Keep existing selected subjects visible even when their stored year differs.
    const yearSubjects = subjects.filter((subject) => subject.year === form.year
        || form.assignments.some((assignment) => assignment.subjectId === subject.id));
    const toggleSubject = (subjectId) => setForm((prev) => ({
        ...prev,
        assignments: prev.assignments.some((assignment) => assignment.subjectId === subjectId)
            ? prev.assignments.filter((assignment) => assignment.subjectId !== subjectId)
            : [...prev.assignments, { subjectId, instructorId: null }],
    }));
    const assignProfessor = (subjectId, value) => setForm((prev) => ({
        ...prev,
        assignments: prev.assignments.map((assignment) => assignment.subjectId === subjectId
            ? { ...assignment, instructorId: value === '' ? null : Number(value) }
            : assignment),
    }));

    const submit = (e) => {
        e.preventDefault();
        if (!readOnly && form.year && form.block) onSubmit(form);
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <div className="p-6">
                <div className="flex items-center justify-between">
                    <h2 className="text-lg font-extrabold uppercase tracking-wide text-green-700">
                        {readOnly ? 'View Class Section' : isEdit ? 'Edit Class Section' : 'Add Class Section'}
                    </h2>
                    <button type="button" onClick={onClose} aria-label="Close class form" className="text-gray-400 transition hover:text-gray-600">
                        <XIcon className="h-5 w-5" />
                    </button>
                </div>
                <form onSubmit={submit} className="mt-4">
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label htmlFor="class-year" className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">Year Level</label>
                            <input disabled={readOnly} id="class-year" type="text" value={form.year} onChange={setField('year')} placeholder="e.g. 1" autoFocus
                                className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600" />
                            {errors.year && <p className="mt-1 text-xs text-red-500">{errors.year}</p>}
                        </div>
                        <div>
                            <label htmlFor="class-block" className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">Block / Section</label>
                            <input disabled={readOnly} id="class-block" type="text" value={form.block} onChange={setField('block')} placeholder="e.g. A"
                                className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600" />
                            {errors.block && <p className="mt-1 text-xs text-red-500">{errors.block}</p>}
                        </div>
                    </div>
                    <p className="mt-4 text-[10px] font-bold uppercase tracking-wide text-gray-400">Assign Subjects and Professors</p>
                    <div className="mt-1 min-h-[120px] rounded-md border border-gray-200 bg-gray-50 p-2">
                        {!form.year ? <p className="flex h-28 items-center justify-center text-center text-xs text-gray-400">Enter a year level to see subjects.</p>
                            : yearSubjects.length === 0 ? <p className="flex h-28 items-center justify-center text-center text-xs text-gray-400">No subjects for that year level yet.</p>
                                : yearSubjects.map((subject) => {
                                    const index = form.assignments.findIndex((assignment) => assignment.subjectId === subject.id);
                                    const assignment = form.assignments[index];
                                    return (
                                        <div key={subject.id} className="rounded-md px-2 py-1.5 hover:bg-gray-100">
                                            <label className="flex cursor-pointer items-start gap-2 text-sm">
                                                <input disabled={readOnly} type="checkbox" checked={Boolean(assignment)} onChange={() => toggleSubject(subject.id)}
                                                    className="mt-0.5 rounded border-gray-300 text-green-600 focus:ring-green-600" />
                                                <span><span className="font-semibold text-gray-700">{subject.code}</span>
                                                    <span className="block text-xs text-gray-400">{subject.description}</span></span>
                                            </label>
                                            {assignment && (
                                                <div className="ml-6 mt-2">
                                                    <label htmlFor={`professor-${subject.id}`} className="text-xs font-semibold text-gray-600">Professor for {subject.code}</label>
                                                    <select disabled={readOnly} id={`professor-${subject.id}`} value={assignment.instructorId ?? ''}
                                                        onChange={(e) => assignProfessor(subject.id, e.target.value)}
                                                        className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600">
                                                        <option value="">TBA (No instructor)</option>
                                                        {faculties.map((faculty) => <option key={faculty.id} value={faculty.id}>{faculty.label ?? faculty.name}</option>)}
                                                    </select>
                                                    {errors[`assignments.${index}.instructorId`] && <p className="mt-1 text-xs text-red-500">{errors[`assignments.${index}.instructorId`]}</p>}
                                                    {errors[`assignments.${index}.subjectId`] && <p className="mt-1 text-xs text-red-500">{errors[`assignments.${index}.subjectId`]}</p>}
                                                </div>
                                            )}
                                        </div>
                                    );
                                })}
                    </div>
                    {['assignments', 'subjectIds', 'scheduleVersion', 'termId'].map((key) => errors[key]
                        && <p key={key} role="alert" className="mt-2 text-xs text-red-500">{errors[key]}</p>)}
                    {!readOnly && <div className="mt-5 flex justify-center">
                        <button type="submit" className="rounded-md bg-green-700 px-8 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800">
                            {isEdit ? 'Update' : 'Save'}
                        </button>
                    </div>}
                </form>
            </div>
        </Modal>
    );
}
