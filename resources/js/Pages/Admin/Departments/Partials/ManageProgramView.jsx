import { useState } from 'react';
import ConfirmDeleteModal from '@/Components/ConfirmDeleteModal';
import PencilIcon from '@/Components/Icons/PencilIcon';
import PlusIcon from '@/Components/Icons/PlusIcon';
import TrashIcon from '@/Components/Icons/TrashIcon';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import ImportModal from '@/Components/ImportModal';
import ClassFormModal from './ClassFormModal';
import FacultyFormModal from './FacultyFormModal';
import StudentFormModal from './StudentFormModal';
import SubjectFormModal from './SubjectFormModal';

const STUDENT_IMPORT_FIELDS = [
    { key: 'studentId', label: 'Student ID', required: true },
    { key: 'email', label: 'Email (required for new students)' },
    { key: 'firstName', label: 'First Name (required for new students)' },
    { key: 'lastName', label: 'Last Name (required for new students)' },
    { key: 'middleName', label: 'Middle Name' },
    { key: 'suffix', label: 'Suffix' },
    { key: 'year', label: 'Year Level' },
    { key: 'block', label: 'Block' },
    { key: 'isIrregular', label: 'Irregular? (Y/N)' },
];

const FACULTY_IMPORT_FIELDS = [
    { key: 'facultyId', label: 'Faculty ID', required: true },
    { key: 'name', label: 'Name', required: true },
];

const SUBJECT_IMPORT_FIELDS = [
    { key: 'code', label: 'Subject Code', required: true },
    { key: 'description', label: 'Description', required: true },
    { key: 'year', label: 'Year Level', required: true },
];

const CLASS_IMPORT_FIELDS = [
    { key: 'year', label: 'Year Level', required: true },
    { key: 'block', label: 'Block / Section', required: true },
    { key: 'subjectCode', label: 'Subject Code', required: true },
    { key: 'facultyId', label: 'Faculty ID (blank or TBA = unassigned)' },
    { key: 'description', label: 'Description (optional reference check)' },
    { key: 'facultyName', label: 'Faculty Name (optional reference check)' },
];

const TABS = ['Subjects', 'Faculties', 'Classes', 'Students'];

// "Manage Program" page — subjects/faculties/classes/students
export default function ManageProgramView({
    department,
    program,
    subjects = [],
    faculty = [],
    classFaculty = [],
    classes = [],
    students = [],
    term = null,
    terms = [],
    canScheduleClasses = false,
}) {
    const { errors } = usePage().props;
    const [activeTab, setActiveTab] = useState('Subjects');
    const [studentSubTab, setStudentSubTab] = useState('Regular');

    const [subjectModal, setSubjectModal] = useState(null); // { mode, subject }
    const [pendingSubjectDeleteId, setPendingSubjectDeleteId] = useState(null);

    const [facultyModal, setFacultyModal] = useState(null); // { mode, faculty }
    const [pendingFacultyDeleteId, setPendingFacultyDeleteId] = useState(null);

    const [classModal, setClassModal] = useState(null); // { mode, classSection }
    const [pendingClassDeleteId, setPendingClassDeleteId] = useState(null);

    const [studentModal, setStudentModal] = useState(null); // { mode, student }
    const [pendingStudentDeleteId, setPendingStudentDeleteId] = useState(null);

    const [classImportOpen, setClassImportOpen] = useState(false);
    const [subjectImportOpen, setSubjectImportOpen] = useState(false);
    const [facultyImportOpen, setFacultyImportOpen] = useState(false);
    const [studentImportOpen, setStudentImportOpen] = useState(false);

    const base = [department.id, program.id];

    const saveSubject = (form) => {
        const onSuccess = () => setSubjectModal(null);
        if (subjectModal?.mode === 'edit') {
            router.put(route('programs.subjects.update', [...base, subjectModal.subject.id]), form, { onSuccess });
        } else {
            router.post(route('programs.subjects.store', base), form, { onSuccess });
        }
    };

    const confirmSubjectDelete = () => {
        router.delete(route('programs.subjects.destroy', [...base, pendingSubjectDeleteId]), {
            onFinish: () => setPendingSubjectDeleteId(null),
        });
    };

    const saveFaculty = (form) => {
        const onSuccess = () => setFacultyModal(null);
        if (facultyModal?.mode === 'edit') {
            // file uploads need POST with a spoofed _method, not a real PUT
            router.post(
                route('programs.faculty.update', [...base, facultyModal.faculty.id]),
                { ...form, _method: 'put' },
                { onSuccess },
            );
        } else {
            router.post(route('programs.faculty.store', base), form, { onSuccess });
        }
    };

    const confirmFacultyDelete = () => {
        router.delete(route('programs.faculty.destroy', [...base, pendingFacultyDeleteId]), {
            onFinish: () => setPendingFacultyDeleteId(null),
        });
    };

    const saveClass = (form) => {
        if (!canScheduleClasses) return;
        const onSuccess = () => setClassModal(null);
        if (classModal?.mode === 'edit') {
            router.put(route('programs.classes.update', [...base, classModal.classSection.id]), { ...form, termId: classModal?.termId }, { onSuccess });
        } else {
            router.post(route('programs.classes.store', base), { ...form, termId: classModal?.termId }, { onSuccess });
        }
    };

    const confirmClassDelete = () => {
        router.delete(route('programs.classes.destroy', [...base, pendingClassDeleteId.id]), {
            data: { termId: pendingClassDeleteId.termId },
            onFinish: () => setPendingClassDeleteId(null),
        });
    };

    const saveStudent = (form, { onFinish } = {}) => {
        const onSuccess = () => setStudentModal(null);
        if (studentModal?.mode === 'edit') {
            router.put(route('programs.students.update', [...base, studentModal.student.id]), form, { onSuccess, onFinish });
        } else {
            router.post(route('programs.students.store', base), form, { onSuccess, onFinish });
        }
    };

    const confirmStudentDelete = () => {
        router.delete(route('programs.students.destroy', [...base, pendingStudentDeleteId]), {
            onFinish: () => setPendingStudentDeleteId(null),
        });
    };


    const regularStudents = students.filter((student) => !student.isIrregular);
    const irregularStudents = students.filter((student) => student.isIrregular);

    return (
        <AdminLayout>
            <Head title={`Manage ${program.code}`} />

            <div className="flex flex-wrap items-center justify-between gap-4">
                <h1 className="text-2xl font-extrabold uppercase tracking-wide text-green-700">
                    Manage Program
                </h1>

                <Link
                    href={route('departments.show', department.id)}
                    className="rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-bold uppercase tracking-wide text-gray-600 shadow-sm transition hover:bg-gray-50"
                >
                    ← Back to Program List
                </Link>
            </div>

            <div className="mt-3 inline-flex items-center gap-2 rounded-md bg-gray-100 px-3 py-1.5 text-sm">
                <span className="font-bold text-green-700">
                    {department.code}
                </span>
                <span className="text-gray-300">|</span>
                <span className="text-gray-600">{program.name}</span>
            </div>

            <div className="mt-5 rounded-2xl border-t-4 border-green-600 bg-white p-6 shadow-sm">
                <div className="flex flex-wrap gap-6 border-b border-gray-100 pb-3">
                    {TABS.map((tab) => (
                        <button
                            key={tab}
                            type="button"
                            onClick={() => setActiveTab(tab)}
                            className={`rounded-md px-3 py-1.5 text-xs font-bold uppercase tracking-wide transition ${
                                activeTab === tab
                                    ? 'bg-green-100 text-green-700'
                                    : 'text-gray-400 hover:text-gray-600'
                            }`}
                        >
                            {tab}
                        </button>
                    ))}
                </div>

                {activeTab === 'Subjects' && (
                    <>
                        <div className="mt-4 flex flex-wrap items-center justify-between gap-4">
                            <span className="rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-gray-600">
                                {program.code} Subjects
                            </span>

                            <div className="flex flex-wrap gap-2">
                                <button type="button" onClick={() => setSubjectImportOpen(true)}
                                    className="flex items-center gap-1.5 rounded-md border border-green-600 px-4 py-2 text-xs font-bold uppercase tracking-wide text-green-700 shadow-sm transition hover:bg-green-50">
                                    Import
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setSubjectModal({ mode: 'add' })}
                                    className="flex items-center gap-1.5 rounded-md bg-green-700 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800"
                                >
                                    <PlusIcon className="h-3.5 w-3.5" />
                                    Add Subject
                                </button>
                            </div>
                        </div>

                        <div className="mt-4 min-h-[220px] overflow-x-auto rounded-lg border border-gray-100">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-gray-100 bg-gray-50">
                                        <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                            Code
                                        </th>
                                        <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                            Description
                                        </th>
                                        <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                            Year
                                        </th>
                                        <th className="px-5 py-2.5 text-right text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {subjects.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="px-5 py-14 text-center text-xs text-gray-400"
                                            >
                                                No subjects yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        subjects.map((subject) => (
                                            <tr
                                                key={subject.id}
                                                className="border-b border-gray-50 last:border-0"
                                            >
                                                <td className="px-5 py-3 font-semibold text-green-700">
                                                    {subject.code}
                                                </td>
                                                <td className="px-5 py-3">
                                                    <p className="text-gray-700">
                                                        {subject.description}
                                                    </p>
                                                </td>
                                                <td className="px-5 py-3 text-gray-700">
                                                    {subject.year}
                                                </td>
                                                <td className="px-5 py-3">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <button
                                                            type="button"
                                                            title="Edit Subject"
                                                            onClick={() =>
                                                                setSubjectModal({
                                                                    mode: 'edit',
                                                                    subject,
                                                                })
                                                            }
                                                            className="flex h-8 w-8 items-center justify-center rounded-md bg-green-100 text-green-600 transition hover:bg-green-200"
                                                        >
                                                            <PencilIcon className="h-4 w-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            title="Delete Subject"
                                                            onClick={() =>
                                                                setPendingSubjectDeleteId(
                                                                    subject.id,
                                                                )
                                                            }
                                                            className="flex h-8 w-8 items-center justify-center rounded-md bg-red-100 text-red-500 transition hover:bg-red-200"
                                                        >
                                                            <TrashIcon className="h-4 w-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                {activeTab === 'Faculties' && (
                    <>
                        <div className="mt-4 flex flex-wrap items-center justify-between gap-4">
                            <span className="rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-gray-600">
                                {program.code} Faculties
                            </span>

                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() => setFacultyImportOpen(true)}
                                    className="flex items-center gap-1.5 rounded-md border border-green-600 px-4 py-2 text-xs font-bold uppercase tracking-wide text-green-700 shadow-sm transition hover:bg-green-50"
                                >
                                    Import
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setFacultyModal({ mode: 'add' })}
                                    className="flex items-center gap-1.5 rounded-md bg-green-700 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800"
                                >
                                    <PlusIcon className="h-3.5 w-3.5" />
                                    Add Faculty
                                </button>
                            </div>
                        </div>

                        <div className="mt-4 min-h-[220px] overflow-x-auto rounded-lg border border-gray-100">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-gray-100 bg-gray-50">
                                        <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                            Faculty ID
                                        </th>
                                        <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                            Name
                                        </th>
                                        <th className="px-5 py-2.5 text-right text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {faculty.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={3}
                                                className="px-5 py-14 text-center text-xs text-gray-400"
                                            >
                                                No faculty members yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        faculty.map((member) => (
                                            <tr
                                                key={member.id}
                                                className="border-b border-gray-50 last:border-0"
                                            >
                                                <td className="px-5 py-3 font-semibold text-green-700">
                                                    {member.facultyId}
                                                </td>
                                                <td className="px-5 py-3 text-gray-700">
                                                    {member.name}
                                                </td>
                                                <td className="px-5 py-3">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <button
                                                            type="button"
                                                            title="Edit Faculty"
                                                            onClick={() =>
                                                                setFacultyModal({
                                                                    mode: 'edit',
                                                                    faculty: member,
                                                                })
                                                            }
                                                            className="flex h-8 w-8 items-center justify-center rounded-md bg-green-100 text-green-600 transition hover:bg-green-200"
                                                        >
                                                            <PencilIcon className="h-4 w-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            title="Delete Faculty"
                                                            onClick={() =>
                                                                setPendingFacultyDeleteId(
                                                                    member.id,
                                                                )
                                                            }
                                                            className="flex h-8 w-8 items-center justify-center rounded-md bg-red-100 text-red-500 transition hover:bg-red-200"
                                                        >
                                                            <TrashIcon className="h-4 w-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                {activeTab === 'Classes' && (
                    <>
                        <div className="mt-4 flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-600">
                            {term ? (
                                <span><strong>{term.isActive ? 'Active period' : 'Historical period'}:</strong> {term.label}{!canScheduleClasses && ' — read-only'}</span>
                            ) : (
                                <span className="text-amber-600">No active evaluation period. Ask the Super Admin to activate one before scheduling or importing classes.</span>
                            )}
                        </div>

                        {errors.termId && <p className="mt-2 text-xs text-red-500" role="alert">{errors.termId}</p>}
                        <div className="mt-4 flex flex-wrap items-center justify-between gap-4">
                            <span className="rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-gray-600">
                                {program.code} Classes
                            </span>

                            <div className="flex flex-wrap gap-2">
                                <button type="button" onClick={() => setClassImportOpen(true)} disabled={!canScheduleClasses}
                                    className="flex items-center gap-1.5 rounded-md border border-green-600 px-4 py-2 text-xs font-bold uppercase tracking-wide text-green-700 shadow-sm transition hover:bg-green-50 disabled:cursor-not-allowed disabled:opacity-60">
                                    Import
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setClassModal({ mode: 'add', termId: term?.id })}
                                    disabled={!canScheduleClasses}
                                    className="flex items-center gap-1.5 rounded-md bg-green-700 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800"
                                >
                                    <PlusIcon className="h-3.5 w-3.5" />
                                    Add Class
                                </button>
                            </div>
                        </div>

                        <div className="mt-4 min-h-[220px] overflow-x-auto rounded-lg border border-gray-100">
                            <table className="w-full text-left text-sm">
                                <thead>
                                    <tr className="border-b border-gray-100 bg-gray-50">
                                        <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                            Section Name
                                        </th>
                                        <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                            Year Level
                                        </th>
                                        <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                            Status
                                        </th>
                                        <th className="px-5 py-2.5 text-right text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {classes.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="px-5 py-14 text-center text-xs text-gray-400"
                                            >
                                                No class sections yet.
                                            </td>
                                        </tr>
                                    ) : (
                                        classes.map((classSection) => (
                                            <tr
                                                key={classSection.id}
                                                className="border-b border-gray-50 last:border-0"
                                            >
                                                <td className="px-5 py-3 font-semibold text-green-700">
                                                    {program.code} - {classSection.year} {classSection.block}
                                                </td>
                                                <td className="px-5 py-3 text-gray-700">
                                                    {classSection.year}
                                                </td>
                                                <td className="px-5 py-3 text-gray-500">
                                                    {classSection.subjectIds?.length ?? 0} Subject
                                                    {classSection.subjectIds?.length === 1 ? '' : 's'} Assigned
                                                </td>
                                                <td className="px-5 py-3">
                                                    <div className="flex items-center justify-end gap-2">
                                                        <button
                                                            type="button"
                                                            title={canScheduleClasses ? 'Edit Class Section' : 'View Class Section'}
                                                            onClick={() =>
                                                                setClassModal({
                                                                    mode: 'edit',
                                                                    classSection,
                                                                    termId: term?.id,
                                                                })
                                                            }
                                                            className="flex h-8 w-8 items-center justify-center rounded-md bg-green-100 text-green-600 transition hover:bg-green-200"
                                                        >
                                                            <PencilIcon className="h-4 w-4" />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            title="Delete Class Section"
                                                            disabled={!canScheduleClasses}
                                                            onClick={() =>
                                                                setPendingClassDeleteId(
                                                                    { id: classSection.id, termId: term?.id },
                                                                )
                                                            }
                                                            className="flex h-8 w-8 items-center justify-center rounded-md bg-red-100 text-red-500 transition hover:bg-red-200"
                                                        >
                                                            <TrashIcon className="h-4 w-4" />
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </>
                )}

                {activeTab === 'Students' && (
                    <>
                        <div className="mt-4 flex flex-wrap items-center justify-between gap-4">
                            <div className="inline-flex rounded-md bg-gray-100 p-1">
                                {['Regular', 'Irregular'].map((tab) => (
                                    <button
                                        key={tab}
                                        type="button"
                                        onClick={() => setStudentSubTab(tab)}
                                        className={`rounded-md px-3 py-1.5 text-xs font-bold uppercase tracking-wide transition ${
                                            studentSubTab === tab
                                                ? 'bg-white text-green-700 shadow-sm'
                                                : 'text-gray-400 hover:text-gray-600'
                                        }`}
                                    >
                                        {tab}
                                    </button>
                                ))}
                            </div>

                            <div className="flex items-center gap-2">
                                <button
                                    type="button"
                                    onClick={() => setStudentImportOpen(true)}
                                    className="flex items-center gap-1.5 rounded-md border border-green-600 px-4 py-2 text-xs font-bold uppercase tracking-wide text-green-700 shadow-sm transition hover:bg-green-50"
                                >
                                    Import
                                </button>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setStudentModal({
                                            mode: 'add',
                                            student: { isIrregular: studentSubTab === 'Irregular' },
                                        })
                                    }
                                    className="flex items-center gap-1.5 rounded-md bg-green-700 px-4 py-2 text-xs font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800"
                                >
                                    <PlusIcon className="h-3.5 w-3.5" />
                                    Add Student
                                </button>
                            </div>
                        </div>

                        {studentSubTab === 'Regular' ? (
                            <div className="mt-4 min-h-[220px] overflow-x-auto rounded-lg border border-gray-100">
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-gray-100 bg-gray-50">
                                            <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                                Student ID
                                            </th>
                                            <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                                Name / Email
                                            </th>
                                            <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                                Section
                                            </th>
                                            <th className="px-5 py-2.5 text-right text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {regularStudents.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={4}
                                                    className="px-5 py-14 text-center text-xs text-gray-400"
                                                >
                                                    No regular students registered yet.
                                                </td>
                                            </tr>
                                        ) : (
                                            regularStudents.map((student) => (
                                                <tr
                                                    key={student.id}
                                                    className="border-b border-gray-50 last:border-0"
                                                >
                                                    <td className="px-5 py-3 font-semibold text-green-700">
                                                        {student.studentId}
                                                    </td>
                                                    <td className="px-5 py-3">
                                                        <p className="text-gray-700">
                                                            {student.firstName} {student.lastName}
                                                        </p>
                                                        <p className="text-xs text-gray-400">
                                                            {student.email}
                                                        </p>
                                                    </td>
                                                    <td className="px-5 py-3 text-gray-700">
                                                        {student.year && student.block
                                                            ? `${program.code} - ${student.year} ${student.block}`
                                                            : '—'}
                                                    </td>
                                                    <td className="px-5 py-3">
                                                        <div className="flex items-center justify-end gap-2">
                                                            <button
                                                                type="button"
                                                                title="Edit Student"
                                                                onClick={() =>
                                                                    setStudentModal({
                                                                        mode: 'edit',
                                                                        student,
                                                                    })
                                                                }
                                                                className="flex h-8 w-8 items-center justify-center rounded-md bg-green-100 text-green-600 transition hover:bg-green-200"
                                                            >
                                                                <PencilIcon className="h-4 w-4" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                title="Delete Student"
                                                                onClick={() =>
                                                                    setPendingStudentDeleteId(
                                                                        student.id,
                                                                    )
                                                                }
                                                                className="flex h-8 w-8 items-center justify-center rounded-md bg-red-100 text-red-500 transition hover:bg-red-200"
                                                            >
                                                                <TrashIcon className="h-4 w-4" />
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <div className="mt-4 min-h-[220px] overflow-x-auto rounded-lg border border-gray-100">
                                <table className="w-full text-left text-sm">
                                    <thead>
                                        <tr className="border-b border-gray-100 bg-gray-50">
                                            <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                                Student ID
                                            </th>
                                            <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                                Name / Email
                                            </th>
                                            <th className="px-5 py-2.5 text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                                Enrolled Courses
                                            </th>
                                            <th className="px-5 py-2.5 text-right text-[11px] font-bold uppercase tracking-wide text-gray-400">
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {irregularStudents.length === 0 ? (
                                            <tr>
                                                <td
                                                    colSpan={4}
                                                    className="px-5 py-14 text-center text-xs text-gray-400"
                                                >
                                                    No irregular students registered yet.
                                                </td>
                                            </tr>
                                        ) : (
                                            irregularStudents.map((student) => (
                                                <tr
                                                    key={student.id}
                                                    className="border-b border-gray-50 last:border-0"
                                                >
                                                    <td className="px-5 py-3 font-semibold text-green-700">
                                                        {student.studentId}
                                                    </td>
                                                    <td className="px-5 py-3">
                                                        <p className="text-gray-700">
                                                            {student.firstName} {student.lastName}
                                                        </p>
                                                        <p className="text-xs text-gray-400">
                                                            {student.email}
                                                        </p>
                                                    </td>
                                                    <td className="px-5 py-3">
                                                        <div className="flex flex-wrap gap-1.5">
                                                            {(student.enrolledCourses ?? []).length === 0 && (
                                                                <span className="text-xs text-gray-400">
                                                                    No courses assigned.
                                                                </span>
                                                            )}
                                                            {(student.enrolledCourses ?? []).map((course) => (
                                                                <span
                                                                    key={course.id}
                                                                    title={`${course.programCode} Y${course.year} · ${course.facultyName ?? 'No instructor yet'}`}
                                                                    className="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600"
                                                                >
                                                                    {course.subjectCode} ({course.programCode})
                                                                </span>
                                                            ))}
                                                        </div>
                                                    </td>
                                                    <td className="px-5 py-3">
                                                        <div className="flex items-center justify-end gap-2">
                                                            <button
                                                                type="button"
                                                                title="Edit Student"
                                                                onClick={() =>
                                                                    setStudentModal({
                                                                        mode: 'edit',
                                                                        student,
                                                                    })
                                                                }
                                                                className="flex h-8 w-8 items-center justify-center rounded-md bg-green-100 text-green-600 transition hover:bg-green-200"
                                                            >
                                                                <PencilIcon className="h-4 w-4" />
                                                            </button>
                                                            <button
                                                                type="button"
                                                                title="Delete Student"
                                                                onClick={() =>
                                                                    setPendingStudentDeleteId(
                                                                        student.id,
                                                                    )
                                                                }
                                                                className="flex h-8 w-8 items-center justify-center rounded-md bg-red-100 text-red-500 transition hover:bg-red-200"
                                                            >
                                                                <TrashIcon className="h-4 w-4" />
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </>
                )}
            </div>

            <SubjectFormModal
                show={subjectModal !== null}
                mode={subjectModal?.mode}
                subject={subjectModal?.subject}
                onClose={() => setSubjectModal(null)}
                onSubmit={saveSubject}
                errors={errors}
            />

            <ConfirmDeleteModal
                show={pendingSubjectDeleteId !== null}
                title="Delete Subject?"
                onCancel={() => setPendingSubjectDeleteId(null)}
                onConfirm={confirmSubjectDelete}
            />

            <FacultyFormModal
                show={facultyModal !== null}
                mode={facultyModal?.mode}
                faculty={facultyModal?.faculty}
                onClose={() => setFacultyModal(null)}
                onSubmit={saveFaculty}
                errors={errors}
            />

            <ConfirmDeleteModal
                show={pendingFacultyDeleteId !== null}
                title="Delete Faculty?"
                onCancel={() => setPendingFacultyDeleteId(null)}
                onConfirm={confirmFacultyDelete}
            />

            <ClassFormModal
                show={classModal !== null}
                mode={classModal?.mode}
                classSection={classModal?.classSection}
                readOnly={!canScheduleClasses}
                subjects={subjects}
                faculties={classFaculty}
                onClose={() => setClassModal(null)}
                onSubmit={saveClass}
                errors={errors}
            />

            <ConfirmDeleteModal
                show={pendingClassDeleteId !== null}
                title="Delete Class?"
                onCancel={() => setPendingClassDeleteId(null)}
                onConfirm={confirmClassDelete}
            />

            <StudentFormModal
                show={studentModal !== null}
                mode={studentModal?.mode}
                student={studentModal?.student}
                classes={classes}
                homeDepartmentId={department.id}
                homeProgramId={program.id}
                onClose={() => setStudentModal(null)}
                onSubmit={saveStudent}
                errors={errors}
            />

            <ConfirmDeleteModal
                show={pendingStudentDeleteId !== null}
                title="Delete Student?"
                onCancel={() => setPendingStudentDeleteId(null)}
                onConfirm={confirmStudentDelete}
            />

            <ImportModal
                key={`class-import-${term?.id ?? 'none'}`}
                show={classImportOpen}
                title="Import Classes"
                previewUrl={route('programs.classes.import.preview', base)}
                importUrl={route('programs.classes.import.store', base)}
                fields={CLASS_IMPORT_FIELDS}
                extraData={{ termId: term?.id }}
                fileLabel="Excel / CSV File"
                accept=".csv,.xlsx,.xls"
                helpText={`One row per subject offering: Year Level, Block / Section, Subject Code, optional Faculty ID (blank or TBA = unassigned). Program: ${program.code}. Active period: ${term?.label ?? 'none'}. Up to 2,000 rows including the header. Adds missing offerings only; existing assignments are never replaced or removed.`}
                onClose={() => setClassImportOpen(false)}
            />

            <ImportModal
                show={subjectImportOpen}
                title="Import Subjects"
                previewUrl={route('programs.subjects.import.preview', base)}
                importUrl={route('programs.subjects.import.store', base)}
                fields={SUBJECT_IMPORT_FIELDS}
                fileLabel="Excel / CSV File"
                accept=".csv,.xlsx,.xls"
                helpText="Columns: Subject Code, Description, Year Level (1–6). This program only. Up to 2,000 rows including the header. Identical records are skipped; conflicts are reported. Existing subjects are never overwritten."
                onClose={() => setSubjectImportOpen(false)}
            />

            <ImportModal
                show={facultyImportOpen}
                title="Import Faculty"
                previewUrl={route('programs.faculty.import.preview', base)}
                importUrl={route('programs.faculty.import.store', base)}
                fields={FACULTY_IMPORT_FIELDS}
                onClose={() => setFacultyImportOpen(false)}
            />

            <ImportModal
                key={`student-import-${studentSubTab}`}
                show={studentImportOpen}
                title={studentSubTab === 'Irregular' ? 'Import Irregular Students' : 'Import Students'}
                previewUrl={route('programs.students.import.preview', base)}
                importUrl={route('programs.students.import.store', base)}
                validateUrl={route('programs.students.import.validate', base)}
                studentRosterOnly
                extraData={{ mode: studentSubTab === 'Irregular' ? 'irregular' : 'regular' }}
                helpText={studentSubTab === 'Irregular'
                    ? 'Import basic information with IRREGULAR (Y/N) = Y. Year and block do not assign subjects. After import, open Edit Student to select attended subjects and class sections. Existing students are left unchanged.'
                    : 'Import regular students with IRREGULAR (Y/N) = N (or blank) using their year and block. Existing students are left unchanged. Use the Irregular tab to import Y rows, then assign their subjects manually.'}
                fields={STUDENT_IMPORT_FIELDS.map((field) => field.key === 'isIrregular' && studentSubTab === 'Irregular' ? { ...field, required: true } : field)}
                onClose={() => setStudentImportOpen(false)}
            />
        </AdminLayout>
    );
}
