import CheckCircleIcon from '@/Components/Icons/CheckCircleIcon';
import EyeIcon from '@/Components/Icons/EyeIcon';
import InputError from '@/Components/InputError';
import LockIcon from '@/Components/LockIcon';
import ShieldCheckBadgeIcon from '@/Components/ShieldCheckBadgeIcon';
import {
    Dialog,
    DialogPanel,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

function Field({ id, label, value, onChange, error, autoComplete }) {
    const [visible, setVisible] = useState(false);

    return (
        <div>
            <label
                htmlFor={id}
                className="block text-xs font-bold uppercase tracking-wide text-gray-500"
            >
                {label}
            </label>

            <div className="relative mt-1">
                <input
                    id={id}
                    type={visible ? 'text' : 'password'}
                    value={value}
                    onChange={onChange}
                    autoComplete={autoComplete}
                    className="block w-full rounded-md border-gray-300 bg-gray-50 pr-10 shadow-sm focus:border-green-500 focus:ring-green-500"
                />
                <button
                    type="button"
                    onClick={() => setVisible((v) => !v)}
                    className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600"
                >
                    <EyeIcon off={visible} className="h-5 w-5" />
                </button>
            </div>

            <InputError message={error} className="mt-1" />
        </div>
    );
}

export default function ChangePasswordModal({ show, onClose }) {
    // step: 'form' -> 'confirm' -> 'success'
    const [step, setStep] = useState('form');

    const { data, setData, errors, put, reset, processing, clearErrors } =
        useForm({
            current_password: '',
            password: '',
            password_confirmation: '',
        });

    const closeAndReset = () => {
        reset();
        clearErrors();
        setStep('form');
        onClose();
    };

    const handleReviewRequest = (e) => {
        e.preventDefault();
        setStep('confirm');
    };

    const confirmUpdate = () => {
        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setStep('success');
            },
            onError: () => setStep('form'),
        });
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
                        <DialogPanel className="relative w-full max-w-sm transform overflow-hidden rounded-xl bg-white shadow-2xl transition-all">
                            <div className="border-b-4 border-green-600 bg-gray-50 px-6 py-4">
                                <h2 className="flex items-center gap-2 text-base font-bold text-gray-900">
                                    <LockIcon className="h-5 w-5" />
                                    Change Password
                                </h2>
                            </div>

                            <form
                                onSubmit={handleReviewRequest}
                                className="space-y-4 px-6 py-6"
                            >
                                <Field
                                    id="current_password"
                                    label="Current Password"
                                    value={data.current_password}
                                    onChange={(e) =>
                                        setData(
                                            'current_password',
                                            e.target.value,
                                        )
                                    }
                                    error={errors.current_password}
                                    autoComplete="current-password"
                                />

                                <Field
                                    id="password"
                                    label="New Password"
                                    value={data.password}
                                    onChange={(e) =>
                                        setData('password', e.target.value)
                                    }
                                    error={errors.password}
                                    autoComplete="new-password"
                                />
                                <p className="-mt-3 text-xs text-gray-400">
                                    At least 8 characters, with uppercase, lowercase, a number, and a symbol.
                                </p>

                                <Field
                                    id="password_confirmation"
                                    label="Confirm New Password"
                                    value={data.password_confirmation}
                                    onChange={(e) =>
                                        setData(
                                            'password_confirmation',
                                            e.target.value,
                                        )
                                    }
                                    error={errors.password_confirmation}
                                    autoComplete="new-password"
                                />

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full rounded-md bg-green-600 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-500 disabled:opacity-50"
                                >
                                    Update Password
                                </button>
                            </form>
                        </DialogPanel>
                    </TransitionChild>
                )}

                {step === 'confirm' && (
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
                                <ShieldCheckBadgeIcon className="mx-auto h-14 w-14 text-green-600" />

                                <p className="mt-4 text-sm text-gray-700">
                                    Are you sure you want to change your
                                    password?
                                </p>
                                <p className="mt-1 text-sm font-bold text-green-700">
                                    You will need to use your new credentials
                                    on your next login.
                                </p>

                                <div className="mt-6 flex flex-col gap-2">
                                    <button
                                        type="button"
                                        onClick={confirmUpdate}
                                        disabled={processing}
                                        className="rounded-md bg-green-600 px-5 py-2 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-500 disabled:opacity-50"
                                    >
                                        Yes, Update Now
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => setStep('form')}
                                        className="rounded-md px-5 py-2 text-sm font-bold uppercase tracking-wide text-gray-500 transition hover:text-gray-700"
                                    >
                                        Cancel
                                    </button>
                                </div>
                            </div>
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

                                <h2 className="mt-4 text-xl font-extrabold text-gray-900">
                                    Success!
                                </h2>
                                <p className="mt-1 text-sm text-gray-500">
                                    Password changed successfully!
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
