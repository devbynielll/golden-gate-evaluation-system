import { Head, Link } from '@inertiajs/react';
import logo from '@/Assets/logo.png';

// 404 page
export default function NotFound({ homeUrl = '/' }) {
    return (
        <>
            <Head title="Page Not Found" />

            <div className="flex min-h-screen w-full flex-col items-center justify-center bg-gray-100 px-4 font-sans">
                <img
                    src={logo}
                    alt="Golden Gate Colleges logo"
                    className="h-20 w-20"
                />

                <h1 className="mt-6 text-6xl font-extrabold text-green-700">404</h1>
                <p className="mt-2 text-lg font-bold text-gray-700">
                    Page Not Found
                </p>
                <p className="mt-1 max-w-sm text-center text-sm text-gray-400">
                    The page you're looking for doesn't exist or may have been moved.
                </p>

                <Link
                    href={homeUrl}
                    className="mt-8 rounded-md bg-green-700 px-8 py-2.5 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-800"
                >
                    Return Home
                </Link>
            </div>
        </>
    );
}
