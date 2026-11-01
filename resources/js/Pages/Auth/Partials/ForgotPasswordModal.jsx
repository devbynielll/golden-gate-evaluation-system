import { useState } from 'react';
import { Dialog, DialogPanel, Transition, TransitionChild } from '@headlessui/react';
import LockIcon from '@/Components/LockIcon';
import MailIcon from '@/Components/MailIcon';
import ShieldCheckBadgeIcon from '@/Components/ShieldCheckBadgeIcon';
import VerificationCodeInput from '@/Components/VerificationCodeInput';

const STEP_REQUEST = 'request';
const STEP_VERIFY = 'verify';
const STEP_RESET = 'reset';
const STEP_DONE = 'done';

// forgot password step flow
export default function ForgotPasswordModal({ show, onClose }) {
    const [step, setStep] = useState(STEP_REQUEST);
    const [email, setEmail] = useState('');
    const [code, setCode] = useState('');
    const [resetToken, setResetToken] = useState('');
    const [newPassword, setNewPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [error, setError] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const submitEmail = async (e) => {
        e.preventDefault();
        setError('');
        setSubmitting(true);
        try {
            await axios.post(route('password.code.send'), { email });
            setStep(STEP_VERIFY);
        } catch (e) {
            setError(e.response?.data?.message ?? 'Something went wrong. Please try again.');
        } finally {
            setSubmitting(false);
        }
    };

    const submitCode = async (e) => {
        e.preventDefault();
        setError('');
        setSubmitting(true);
        try {
            const { data } = await axios.post(route('password.code.verify'), { email, code });
            setResetToken(data.reset_token);
            setStep(STEP_RESET);
        } catch (e) {
            setError(e.response?.data?.errors?.code?.[0] ?? 'That code is invalid or has expired.');
        } finally {
            setSubmitting(false);
        }
    };

    const submitReset = async (e) => {
        e.preventDefault();
        setError('');
        if (newPassword !== confirmPassword) {
            setError('Passwords do not match.');
            return;
        }
        setSubmitting(true);
        try {
            await axios.post(route('password.code.reset'), {
                email,
                reset_token: resetToken,
                password: newPassword,
                password_confirmation: confirmPassword,
            });
            setStep(STEP_DONE);
        } catch (e) {
            const errors = e.response?.data?.errors;
            setError(errors ? Object.values(errors)[0][0] : 'Something went wrong. Please try again.');
        } finally {
            setSubmitting(false);
        }
    };

    const close = () => {
        setEmail('');
        setCode('');
        setResetToken('');
        setNewPassword('');
        setConfirmPassword('');
        setError('');
        setStep(STEP_REQUEST);
        onClose();
    };

    return (
        <Transition show={show} leave="duration-200">
            <Dialog
                as="div"
                className="fixed inset-0 z-50 flex transform items-center justify-center overflow-y-auto px-4 py-6 transition-all"
                onClose={close}
            >
                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="absolute inset-0 bg-black/10" />
                </TransitionChild>

                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0 translate-y-4 sm:scale-95"
                    enterTo="opacity-100 translate-y-0 sm:scale-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                    leaveTo="opacity-0 translate-y-4 sm:scale-95"
                >
                    <DialogPanel className="relative w-full max-w-sm transform overflow-hidden rounded-2xl bg-white shadow-2xl transition-all">
                        {step === STEP_REQUEST && (
                            <div className="px-8 py-8">
                                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-600">
                                    <MailIcon className="h-7 w-7 text-white" />
                                </div>

                                <h2 className="mt-4 text-center text-lg font-bold text-gray-900">
                                    Reset Password
                                </h2>
                                <p className="mx-auto mt-1 max-w-xs text-center text-xs text-gray-500">
                                    Enter your registered email and we&apos;ll
                                    send you a verification code
                                </p>

                                <form onSubmit={submitEmail} className="mt-5">
                                    <label
                                        htmlFor="forgot-email"
                                        className="block text-xs font-semibold text-gray-600"
                                    >
                                        Email Address
                                    </label>

                                    <input
                                        id="forgot-email"
                                        type="email"
                                        name="email"
                                        value={email}
                                        className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                                        placeholder="name@example.com"
                                        autoComplete="username"
                                        autoFocus
                                        required
                                        onChange={(e) =>
                                            setEmail(e.target.value)
                                        }
                                    />

                                    {error && (
                                        <p className="mt-2 text-xs text-red-500">{error}</p>
                                    )}

                                    <button
                                        type="submit"
                                        disabled={submitting}
                                        className="mt-5 w-full rounded-lg bg-green-600 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-md transition hover:bg-green-700 disabled:opacity-50"
                                    >
                                        {submitting ? 'Sending...' : 'Send Code'}
                                    </button>
                                </form>
                            </div>
                        )}

                        {step === STEP_VERIFY && (
                            <div className="px-8 py-8">
                                <div className="mx-auto flex h-14 w-14 items-center justify-center">
                                    <ShieldCheckBadgeIcon className="h-12 w-12 text-green-600" />
                                </div>

                                <h2 className="mt-2 text-center text-lg font-bold text-gray-900">
                                    Enter Verification Code
                                </h2>
                                <p className="mt-1 text-center text-xs text-gray-500">
                                    We sent a 6-digit code to
                                </p>
                                <p className="text-center text-sm font-semibold text-green-700">
                                    {email}
                                </p>

                                <form onSubmit={submitCode} className="mt-5">
                                    <VerificationCodeInput
                                        value={code}
                                        onChange={setCode}
                                    />

                                    {error && (
                                        <p className="mt-2 text-center text-xs text-red-500">{error}</p>
                                    )}

                                    <button
                                        type="submit"
                                        disabled={code.length < 6 || submitting}
                                        className="mt-6 w-full rounded-lg bg-green-600 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-md transition hover:bg-green-700 disabled:opacity-50"
                                    >
                                        {submitting ? 'Verifying...' : 'Verify Code'}
                                    </button>
                                </form>
                            </div>
                        )}

                        {step === STEP_RESET && (
                            <div className="px-8 py-8">
                                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-600">
                                    <LockIcon className="h-7 w-7 text-white" />
                                </div>

                                <h2 className="mt-4 text-center text-lg font-bold text-gray-900">
                                    Set New Password
                                </h2>
                                <p className="mt-1 text-center text-xs text-gray-500">
                                    Please create a strong password for your
                                    account.
                                </p>

                                <form onSubmit={submitReset} className="mt-5">
                                    <label
                                        htmlFor="new-password"
                                        className="block text-xs font-semibold text-gray-600"
                                    >
                                        New Password
                                    </label>

                                    <input
                                        id="new-password"
                                        type="password"
                                        value={newPassword}
                                        className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                                        placeholder="Enter new password"
                                        autoComplete="new-password"
                                        autoFocus
                                        required
                                        onChange={(e) =>
                                            setNewPassword(e.target.value)
                                        }
                                    />

                                    <label
                                        htmlFor="confirm-password"
                                        className="mt-4 block text-xs font-semibold text-gray-600"
                                    >
                                        Confirm Password
                                    </label>

                                    <input
                                        id="confirm-password"
                                        type="password"
                                        value={confirmPassword}
                                        className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-green-600 focus:ring-green-600"
                                        placeholder="Re-enter password"
                                        autoComplete="new-password"
                                        required
                                        onChange={(e) =>
                                            setConfirmPassword(e.target.value)
                                        }
                                    />

                                    <p className="mt-2 text-[11px] text-gray-400">
                                        At least 8 characters, with uppercase, lowercase, a number, and a symbol.
                                    </p>

                                    {error && (
                                        <p className="mt-2 text-center text-xs text-red-500">{error}</p>
                                    )}

                                    <div className="mt-6 flex justify-center">
                                        <button
                                            type="submit"
                                            disabled={submitting}
                                            className="rounded-full bg-green-600 px-10 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-md transition hover:bg-green-700 disabled:opacity-50"
                                        >
                                            {submitting ? 'Resetting...' : 'Reset'}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        )}

                        {step === STEP_DONE && (
                            <div className="px-8 py-8 text-center">
                                <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-600">
                                    <ShieldCheckBadgeIcon className="h-7 w-7 text-white" />
                                </div>

                                <h2 className="mt-4 text-lg font-bold text-gray-900">
                                    Password Reset
                                </h2>
                                <p className="mt-1 text-xs text-gray-500">
                                    You can now log in with your new password.
                                </p>

                                <button
                                    type="button"
                                    onClick={close}
                                    className="mt-6 rounded-lg bg-green-600 px-8 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-md transition hover:bg-green-700"
                                >
                                    OK
                                </button>
                            </div>
                        )}
                    </DialogPanel>
                </TransitionChild>
            </Dialog>
        </Transition>
    );
}
