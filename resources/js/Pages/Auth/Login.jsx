import InputError from '@/Components/InputError';
import EyeIcon from '@/Components/Icons/EyeIcon';
import ShieldCheckIcon from '@/Components/ShieldCheckIcon';
import EvaluationGuestLayout from '@/Layouts/EvaluationGuestLayout';
import ForgotPasswordModal from '@/Pages/Auth/Partials/ForgotPasswordModal';
import logo from '@/Assets/logo.png';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Login({ status, canResetPassword }) {
    const [showForgotPassword, setShowForgotPassword] = useState(false);
    const [showPassword, setShowPassword] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        id_number: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <EvaluationGuestLayout>
            <Head title="Log in" />

            <div className="mx-auto w-full max-w-sm rounded-3xl bg-[#faf6dc]/95 px-8 py-10 shadow-xl">
                <img
                    src={logo}
                    alt="Golden Gate Colleges logo"
                    className="mx-auto h-20 w-20"
                />

                <h2 className="mt-4 text-center text-2xl font-extrabold text-green-700">
                    Hi, Goldenian!
                </h2>
                <p className="mt-1 text-center text-sm text-gray-600">
                    Enter your credentials to continue
                </p>

                <div className="mt-2 flex items-center justify-center gap-1 text-sm font-bold text-green-700">
                    <ShieldCheckIcon className="h-4 w-4" />
                    USER LOGIN
                </div>

                {status && (
                    <div className="mt-4 text-center text-sm font-medium text-green-600">
                        {status}
                    </div>
                )}

                <form onSubmit={submit} className="mt-4">
                    <div>
                        <label
                            htmlFor="id_number"
                            className="block text-xs font-semibold uppercase tracking-wide text-gray-500"
                        >
                            User ID
                        </label>

                        <input
                            id="id_number"
                            type="text"
                            name="id_number"
                            value={data.id_number}
                            className="mt-1 block w-full rounded-md border-none bg-white px-4 py-2.5 text-sm text-gray-800 shadow-sm placeholder:text-gray-400 focus:ring-2 focus:ring-green-600"
                            placeholder="Enter your user id"
                            autoComplete="username"
                            autoFocus
                            onChange={(e) => setData('id_number', e.target.value)}
                        />

                        <InputError message={errors.id_number} className="mt-2" />
                    </div>

                    <div className="mt-4">
                        <label
                            htmlFor="password"
                            className="block text-xs font-semibold uppercase tracking-wide text-gray-500"
                        >
                            Password
                        </label>

                        <div className="relative mt-1">
                            <input
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                name="password"
                                value={data.password}
                                className="block w-full rounded-md border-none bg-white py-2.5 pl-4 pr-12 text-sm text-gray-800 shadow-sm placeholder:text-gray-400 focus:ring-2 focus:ring-green-600"
                                placeholder="Enter your your password"
                                autoComplete="current-password"
                                onChange={(e) => setData('password', e.target.value)}
                            />
                            <button
                                type="button"
                                aria-label={showPassword ? 'Hide password' : 'Show password'}
                                aria-controls="password"
                                onClick={() => setShowPassword((visible) => !visible)}
                                className="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-md text-gray-500 hover:text-green-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600"
                            >
                                <EyeIcon off={showPassword} />
                            </button>
                        </div>

                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <label className="mt-4 flex items-center gap-2 text-sm text-gray-600">
                        <input
                            type="checkbox"
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData('remember', e.target.checked)
                            }
                            className="rounded border-gray-300 text-green-600 shadow-sm focus:ring-green-600"
                        />
                        Remember me
                    </label>

                    <button
                        type="submit"
                        disabled={processing}
                        className="mt-6 w-full rounded-lg bg-green-700 py-3 text-sm font-bold uppercase tracking-wide text-white shadow-md transition hover:bg-green-800 disabled:opacity-50"
                    >
                        Login
                    </button>

                    <div className="mt-4 text-center">
                        {canResetPassword && (
                            <button
                                type="button"
                                onClick={() => setShowForgotPassword(true)}
                                className="text-sm text-gray-600 hover:text-gray-900"
                            >
                                Forgot Password?
                            </button>
                        )}
                    </div>
                </form>
            </div>

            <ForgotPasswordModal
                show={showForgotPassword}
                onClose={() => setShowForgotPassword(false)}
            />
        </EvaluationGuestLayout>
    );
}
