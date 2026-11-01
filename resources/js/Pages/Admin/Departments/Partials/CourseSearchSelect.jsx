import { useEffect, useState } from 'react';
import axios from 'axios';
import SearchIcon from '@/Components/Icons/SearchIcon';
import XIcon from '@/Components/Icons/XIcon';

const YEAR_OPTIONS = [
    { value: '1', label: '1st Year' },
    { value: '2', label: '2nd Year' },
    { value: '3', label: '3rd Year' },
    { value: '4', label: '4th Year' },
];

const labelFor = (course) =>
    `${course.programCode} Y${course.year} · ${course.subjectCode} - ${course.subjectName}`;

const offeringLabel = (offering) =>
    `${offering.label}${offering.facultyName ? ` · ${offering.facultyName}` : ' · no instructor yet'}`;

// filter by department/program/year, then add/remove courses freely
export default function CourseSearchSelect({
    selected = [],
    onChange,
    error,
    defaultDepartmentId = null,
    defaultProgramId = null,
}) {
    const [departments, setDepartments] = useState([]);
    const [programs, setPrograms] = useState([]);
    const [filtersError, setFiltersError] = useState(null);

    // starts scoped to the student's own department/program, still changeable
    const [departmentId, setDepartmentId] = useState(defaultDepartmentId ? String(defaultDepartmentId) : '');
    const [programId, setProgramId] = useState(defaultProgramId ? String(defaultProgramId) : '');
    const [year, setYear] = useState('');
    const [query, setQuery] = useState('');

    const [results, setResults] = useState([]);
    const [loading, setLoading] = useState(false);
    const [searchError, setSearchError] = useState(null);

    // load filter options once
    useEffect(() => {
        axios
            .get(route('courses.filters'))
            .then(({ data }) => {
                setDepartments(data.departments ?? []);
                setPrograms(data.programs ?? []);
            })
            .catch(() => setFiltersError("Couldn't load departments/programs."));
    }, []);

    const hasScope = departmentId || programId || year || query.trim().length >= 2;

    useEffect(() => {
        if (!hasScope) {
            setResults([]);
            return;
        }

        let cancelled = false;
        setLoading(true);
        setSearchError(null);

        const timer = setTimeout(() => {
            axios
                .get(route('courses.search'), {
                    params: {
                        department_id: departmentId || undefined,
                        program_id: programId || undefined,
                        year: year || undefined,
                        q: query.trim() || undefined,
                    },
                })
                .then(({ data }) => {
                    if (!cancelled) setResults(data.data ?? []);
                })
                .catch(() => {
                    if (!cancelled) setSearchError("Couldn't load courses. Try again.");
                })
                .finally(() => {
                    if (!cancelled) setLoading(false);
                });
        }, 300);

        return () => {
            cancelled = true;
            clearTimeout(timer);
        };
    }, [departmentId, programId, year, query]);

    const programOptions = departmentId
        ? programs.filter((p) => String(p.department_id) === String(departmentId))
        : programs;

    const isSelected = (id) => selected.some((c) => c.id === id);

    const toggleCourse = (course) => {
        onChange(
            isSelected(course.id)
                ? selected.filter((c) => c.id !== course.id)
                : [...selected, course],
        );
    };

    const removeCourse = (id) => onChange(selected.filter((c) => c.id !== id));

    // block the student attends
    const setBlock = (id, value) =>
        onChange(
            selected.map((c) => {
                if (c.id !== id) return c;
                const classSectionId = value === '' ? null : Number(value);
                const offering = c.offerings?.find((o) => o.sectionId === classSectionId);
                return { ...c, classSectionId, facultyName: offering?.facultyName ?? null };
            }),
        );

    return (
        <div>
            <label className="block text-[10px] font-bold uppercase tracking-wide text-gray-400">
                Enrolled Courses
            </label>

            <div className="mt-1 space-y-2">
                <div className="grid grid-cols-3 gap-2">
                    <select
                        value={departmentId}
                        onChange={(e) => {
                            setDepartmentId(e.target.value);
                            setProgramId('');
                        }}
                        className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-green-600 focus:ring-green-600"
                    >
                        <option value="">All Departments</option>
                        {departments.map((department) => (
                            <option key={department.id} value={department.id}>
                                {department.code}
                            </option>
                        ))}
                    </select>

                    <select
                        value={programId}
                        onChange={(e) => setProgramId(e.target.value)}
                        className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-green-600 focus:ring-green-600"
                    >
                        <option value="">All Programs</option>
                        {programOptions.map((program) => (
                            <option key={program.id} value={program.id}>
                                {program.code}
                            </option>
                        ))}
                    </select>

                    <select
                        value={year}
                        onChange={(e) => setYear(e.target.value)}
                        className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-green-600 focus:ring-green-600"
                    >
                        <option value="">All Years</option>
                        {YEAR_OPTIONS.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="relative">
                    <SearchIcon className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-green-600" />
                    <input
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Search by subject, program, or instructor..."
                        className="block w-full rounded-md border-gray-300 py-2 pl-9 pr-3 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                    />
                </div>

                {filtersError && <p className="text-xs text-red-500">{filtersError}</p>}
            </div>

            <div className="mt-2 max-h-52 overflow-y-auto rounded-md border border-gray-200">
                {!hasScope && (
                    <p className="px-3 py-3 text-xs text-gray-400">
                        Pick a department, program, or year level to see its courses.
                    </p>
                )}
                {hasScope && loading && (
                    <p className="px-3 py-3 text-xs text-gray-400">Loading courses...</p>
                )}
                {hasScope && !loading && searchError && (
                    <p className="px-3 py-3 text-xs text-red-500">{searchError}</p>
                )}
                {hasScope && !loading && !searchError && results.length === 0 && (
                    <p className="px-3 py-3 text-xs text-gray-400">No matching courses.</p>
                )}
                {hasScope &&
                    !loading &&
                    !searchError &&
                    results.map((course) => (
                        <button
                            key={course.id}
                            type="button"
                            onClick={() => toggleCourse(course)}
                            className={`flex w-full items-center justify-between gap-2 border-b border-gray-100 px-3 py-2 text-left text-xs transition last:border-0 hover:bg-green-50 ${
                                isSelected(course.id) ? 'bg-green-50 text-green-700' : 'text-gray-700'
                            }`}
                        >
                            <span>
                                {labelFor(course)}
                                <span className="ml-1.5 text-[10px] font-bold uppercase text-gray-400">
                                    {course.offerings?.length
                                        ? course.offerings.map(offeringLabel).join(' | ')
                                        : 'No class scheduled yet'}
                                </span>
                            </span>
                            <span className="shrink-0 font-bold uppercase tracking-wide">
                                {isSelected(course.id) ? 'Remove' : 'Add'}
                            </span>
                        </button>
                    ))}
            </div>

            <div className="mt-2 space-y-1.5">
                {selected.length === 0 && (
                    <p className="text-xs text-gray-400">No courses selected yet.</p>
                )}
                {selected.map((course) => (
                    <div
                        key={course.id}
                        className="flex flex-wrap items-center gap-2 rounded-md bg-green-100 px-2 py-1 text-xs font-semibold text-green-700"
                    >
                        <span className="flex-1">{labelFor(course)}</span>
                        {course.offerings?.length > 0 ? (
                            <select
                                value={course.classSectionId ?? ''}
                                onChange={(e) => setBlock(course.id, e.target.value)}
                                className={`rounded-md py-0.5 text-xs ${
                                    course.classSectionId ? 'border-green-300' : 'border-red-400'
                                }`}
                            >
                                <option value="">Choose block...</option>
                                {course.offerings.map((offering) => (
                                    <option key={offering.sectionId} value={offering.sectionId}>
                                        {offeringLabel(offering)}
                                    </option>
                                ))}
                            </select>
                        ) : (
                            <span className="text-[10px] font-bold uppercase text-amber-600">
                                No class scheduled yet
                            </span>
                        )}
                        <button
                            type="button"
                            onClick={() => removeCourse(course.id)}
                            className="text-green-500 hover:text-green-800"
                        >
                            <XIcon className="h-3 w-3" />
                        </button>
                    </div>
                ))}
            </div>

            {error && <p className="mt-1 text-xs text-red-500">{error}</p>}
        </div>
    );
}
