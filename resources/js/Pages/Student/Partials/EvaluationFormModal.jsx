import CheckCircleIcon from '@/Components/Icons/CheckCircleIcon';
import InfoCircleIcon from '@/Components/Icons/InfoCircleIcon';
import LayersIcon from '@/Components/Icons/LayersIcon';
import XIcon from '@/Components/Icons/XIcon';
import logo from '@/Assets/logo.png';
import {
    Dialog,
    DialogPanel,
    DialogTitle,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import { useMemo, useState } from 'react';

const MAX_COMMENT_CHARACTERS = 300;

const RATING_SCALE = [
    { value: 1, label: 'Unsatisfactory', className: 'bg-red-100 text-red-700 border-red-300' },
    { value: 2, label: 'Fairly Satisfactory', className: 'bg-orange-100 text-orange-700 border-orange-300' },
    { value: 3, label: 'Satisfactory', className: 'bg-green-100 text-green-700 border-green-300' },
    { value: 4, label: 'Very Satisfactory', className: 'bg-green-600 text-white border-green-700' },
];

// evaluation form: privacy notice, rating legend, criteria tables, comments
export default function EvaluationFormModal({
    show,
    onClose,
    title,
    subtitle,
    categories = [],
    onSubmit: submitEvaluation,
}) {
    const [step, setStep] = useState('form'); // 'form' | 'success'
    const [ratings, setRatings] = useState({});
    const [comments, setComments] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [submissionError, setSubmissionError] = useState('');

    const allQuestions = useMemo(
        () => categories.flatMap((category) => category.questions),
        [categories],
    );

    const isComplete =
        allQuestions.length > 0 &&
        allQuestions.every((question) => ratings[question.id]);

    const closeAndReset = () => {
        if (submitting) return;
        setSubmissionError('');
        setStep('form');
        setRatings({});
        setComments('');
        onClose();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!isComplete || submitting) return;

        setSubmissionError('');
        setSubmitting(true);
        submitEvaluation(
            { ratings, comments },
            {
                onSuccess: () => {
                    setSubmitting(false);
                    setStep('success');
                },
                onError: (errors) => { setSubmitting(false); setSubmissionError(Object.values(errors ?? {}).flat().join(' ') || 'The evaluation could not be submitted. Please try again.'); },
            },
        );
    };

    return (
        <Transition show={show} leave="duration-200">
            <Dialog
                as="div"
                className="fixed inset-0 z-50 flex transform items-center justify-center overflow-y-auto px-4 py-6 transition-all"
                onClose={closeAndReset}
            >
                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="absolute inset-0 bg-gray-500/75" />
                </TransitionChild>

                {step === 'form' && (
                    <TransitionChild
                        enter="ease-out duration-300"
                        enterFrom="opacity-0 translate-y-4 sm:scale-95"
                        enterTo="opacity-100 translate-y-0 sm:scale-100"
                        leave="ease-in duration-200"
                        leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                        leaveTo="opacity-0 translate-y-4 sm:scale-95"
                    >
                        <DialogPanel className="relative flex max-h-[90vh] w-full max-w-3xl transform flex-col overflow-hidden rounded-xl bg-white shadow-2xl transition-all">
                            {/* Header */}
                            <div className="flex shrink-0 items-center justify-between gap-3 bg-green-700 px-6 py-4">
                                <div className="flex items-center gap-3">
                                    <img
                                        src={logo}
                                        alt=""
                                        className="h-10 w-10 shrink-0"
                                    />
                                    <div className="leading-tight">
                                        <DialogTitle className="text-lg font-extrabold uppercase tracking-wide text-white">
                                            {title}
                                        </DialogTitle>
                                        {subtitle && (
                                            <p className="text-xs font-semibold uppercase tracking-wide text-white/80">
                                                {subtitle}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    onClick={closeAndReset}
                                    disabled={submitting}
                                    aria-label="Close evaluation form"
                                    className="text-white/80 hover:text-white"
                                >
                                    <XIcon className="h-5 w-5" />
                                </button>
                            </div>

                            {/* Body */}
                            <form
                                onSubmit={handleSubmit}
                                className="flex flex-1 flex-col overflow-hidden"
                            >
                                <div className="flex-1 space-y-4 overflow-y-auto px-6 py-5">
                                    {submissionError && <p role="alert" className="rounded-md bg-red-50 p-3 text-sm text-red-700">{submissionError}</p>}
                                    <div className="flex items-start gap-2 rounded-lg border border-gray-200 p-4 text-xs text-gray-600">
                                        <InfoCircleIcon className="h-4 w-4 shrink-0 text-blue-500" />
                                        <p>
                                            In accordance with the{' '}
                                            <strong>
                                                Data Privacy Act of 2012
                                                (Republic Act No. 10173)
                                            </strong>
                                            , all information collected
                                            through this evaluation will be
                                            treated with strict
                                            confidentiality.
                                        </p>
                                    </div>

                                    <div className="rounded-lg border border-gray-200 p-4">
                                        <p className="text-xs font-bold text-gray-600">
                                            Rating Scale Guide:
                                        </p>
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            {RATING_SCALE.map((scale) => (
                                                <span
                                                    key={scale.value}
                                                    className={`rounded-md border px-3 py-1.5 text-xs font-bold ${scale.className}`}
                                                >
                                                    {scale.value} &ndash;{' '}
                                                    {scale.label}
                                                </span>
                                            ))}
                                        </div>
                                    </div>

                                    {categories.map((category) => (
                                        <div
                                            key={category.id}
                                            className="overflow-hidden rounded-lg border border-gray-200"
                                        >
                                            <div className="flex items-center gap-2 bg-green-700 px-4 py-2.5">
                                                <LayersIcon className="h-4 w-4 text-white" />
                                                <h3 className="text-xs font-extrabold uppercase tracking-wide text-white">
                                                    Section {category.section}
                                                    : {category.name}
                                                </h3>
                                            </div>

                                            <div className="overflow-x-auto">
                                                <table className="w-full text-left text-sm">
                                                    <thead>
                                                        <tr className="bg-gray-100 text-xs font-bold uppercase tracking-wide text-gray-500">
                                                            <th className="px-4 py-2">
                                                                Performance
                                                                Criteria
                                                            </th>
                                                            {RATING_SCALE.map(
                                                                (scale) => (
                                                                    <th
                                                                        key={
                                                                            scale.value
                                                                        }
                                                                        className="px-2 py-2 text-center"
                                                                    >
                                                                        {
                                                                            scale.value
                                                                        }
                                                                    </th>
                                                                ),
                                                            )}
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        {category.questions.map(
                                                            (question) => (
                                                                <tr
                                                                    key={
                                                                        question.id
                                                                    }
                                                                    className="border-t border-gray-100"
                                                                >
                                                                    <td className="px-4 py-3 text-gray-700">
                                                                        {
                                                                            question.text
                                                                        }
                                                                    </td>
                                                                    {RATING_SCALE.map(
                                                                        (
                                                                            scale,
                                                                        ) => (
                                                                            <td
                                                                                key={
                                                                                    scale.value
                                                                                }
                                                                                className="px-2 py-3 text-center"
                                                                            >
                                                                                <input
                                                                                    type="radio"
                                                                                    aria-label={`${question.text}: ${scale.value} — ${scale.label}`}
                                                                                    name={`question-${question.id}`}
                                                                                    checked={
                                                                                        ratings[
                                                                                            question
                                                                                                .id
                                                                                        ] ===
                                                                                        scale.value
                                                                                    }
                                                                                    onChange={() =>
                                                                                        setRatings(
                                                                                            (
                                                                                                prev,
                                                                                            ) => ({
                                                                                                ...prev,
                                                                                                [question.id]:
                                                                                                    scale.value,
                                                                                            }),
                                                                                        )
                                                                                    }
                                                                                    className="h-4 w-4 accent-green-600"
                                                                                />
                                                                            </td>
                                                                        ),
                                                                    )}
                                                                </tr>
                                                            ),
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    ))}

                                    <div className="rounded-lg border border-gray-200 p-4">
                                        <p className="text-xs font-bold uppercase tracking-wide text-gray-400">
                                            Optional Comments / Feedback
                                        </p>
                                        <textarea
                                            value={comments}
                                            onChange={(e) =>
                                                setComments(e.target.value)
                                            }
                                            aria-label="Optional comments or feedback"
                                            maxLength={MAX_COMMENT_CHARACTERS}
                                            rows={3}
                                            placeholder="Type your feedback here..."
                                            className="mt-2 block w-full resize-none rounded-md border-gray-300 bg-gray-50 text-sm shadow-sm focus:border-green-500 focus:ring-green-500"
                                        />
                                        <div className="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
                                            <span>Optional. Avoid including details that could identify you.</span>
                                            <span aria-live="polite" className="tabular-nums">
                                                {comments.length} / {MAX_COMMENT_CHARACTERS} characters
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                {/* Footer */}
                                <div className="flex shrink-0 justify-end gap-3 border-t border-gray-100 px-6 py-4">
                                    <button
                                        type="button"
                                        onClick={closeAndReset}
                                        className="rounded-md bg-gray-200 px-5 py-2 text-sm font-bold uppercase tracking-wide text-gray-600 transition hover:bg-gray-300"
                                    >
                                        Discard
                                    </button>
                                    <button
                                        type="submit"
                                        disabled={!isComplete || submitting}
                                        className="rounded-md bg-green-600 px-6 py-2 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-500 disabled:cursor-not-allowed disabled:bg-gray-300 disabled:text-gray-500"
                                    >
                                        Submit
                                    </button>
                                </div>
                            </form>
                        </DialogPanel>
                    </TransitionChild>
                )}

                {step === 'success' && (
                    <TransitionChild
                        enter="ease-out duration-300"
                        enterFrom="opacity-0 translate-y-4 sm:scale-95"
                        enterTo="opacity-100 translate-y-0 sm:scale-100"
                        leave="ease-in duration-200"
                        leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                        leaveTo="opacity-0 translate-y-4 sm:scale-95"
                    >
                        <DialogPanel className="relative w-full max-w-sm transform overflow-hidden rounded-xl bg-white shadow-2xl transition-all">
                            <div className="px-8 py-8 text-center">
                                <CheckCircleIcon className="mx-auto h-14 w-14 text-green-500" />

                                <DialogTitle className="mt-4 text-xl font-extrabold text-gray-900">
                                    Thank You!
                                </DialogTitle>
                                <p className="mt-1 text-sm text-gray-500">
                                    Your evaluation was submitted
                                    successfully.
                                </p>

                                <button
                                    type="button"
                                    onClick={closeAndReset}
                                    className="mt-6 rounded-md bg-green-600 px-8 py-2 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-500"
                                >
                                    OK
                                </button>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                )}
            </Dialog>
        </Transition>
    );
}
