import { Head, Link } from '@inertiajs/react';
import bg from '@/Assets/bg.jpg';
import logo from '@/Assets/logo.png';

export default function Welcome() {
    return (
        <>
            <Head title="Anonymous Monitoring and Evaluation System" />

            <div className="min-h-screen w-full font-sans">
                {/* Header */}
                <header className="flex items-center gap-3 bg-green-700 px-4 py-3 sm:px-8 sm:py-4">
                    <img
                        src={logo}
                        alt="Golden Gate Colleges logo"
                        className="h-10 w-10 shrink-0 sm:h-14 sm:w-14"
                    />
                    <div className="leading-tight">
                        <h1 className="text-sm font-extrabold uppercase tracking-wide text-white sm:text-xl md:text-2xl">
                            Anonymous Monitoring and Evaluation System
                        </h1>
                        <p className="text-[10px] font-semibold uppercase tracking-wide text-white/90 sm:text-xs md:text-sm">
                            Golden Gate Colleges
                        </p>
                    </div>
                </header>

                {/* Hero */}
                <main
                    className="relative flex min-h-[calc(100vh-64px)] items-center justify-center bg-cover bg-center px-4 py-16 sm:px-8"
                    style={{ backgroundImage: `url(${bg})` }}
                >
                    <div className="absolute inset-0 bg-green-600/60" />

                    <div className="relative z-10 mx-auto max-w-5xl text-center">
                        <h2 className="text-3xl font-extrabold leading-tight text-white drop-shadow-md sm:text-5xl md:text-6xl">
                            Together, We Shape Better
                        </h2>
                        <h2 className="mt-1 text-3xl font-extrabold italic leading-tight text-yellow-300 drop-shadow-md sm:text-5xl md:text-6xl">
                            Education &amp; Services
                        </h2>

                        <p className="mx-auto mt-6 max-w-2xl text-sm text-white drop-shadow-sm sm:text-base md:text-lg">
                            Welcome to the Faculty and Office Evaluation System
                            of Golden Gate Colleges. Your feedback matters in
                            improving education and student services.
                        </p>

                        <div className="mt-10">
                            <Link
                                href={route('login')}
                                className="inline-block rounded-full bg-yellow-50 px-8 py-3 text-sm font-bold uppercase tracking-wide text-green-700 shadow-md transition hover:bg-white sm:text-base"
                            >
                                Start Evaluation
                            </Link>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
