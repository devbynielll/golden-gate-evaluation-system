import { Link } from '@inertiajs/react';
import bg from '@/Assets/bg.jpg';
import logo from '@/Assets/logo.png';

// guest layout
export default function EvaluationGuestLayout({ children }) {
    return (
        <div className="flex min-h-screen w-full flex-col font-sans">
            {/* Header */}
            <header className="flex items-center justify-between gap-3 bg-green-700 px-4 py-3 sm:px-8 sm:py-4">
                <div className="flex items-center gap-3">
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
                </div>

                <Link
                    href="/"
                    className="shrink-0 text-xs font-bold uppercase tracking-wide text-white hover:text-white/80 sm:text-sm"
                >
                    Home
                </Link>
            </header>

            {/* Content */}
            <main
                className="relative flex flex-1 items-center justify-center bg-cover bg-center px-4 py-12 sm:px-8"
                style={{ backgroundImage: `url(${bg})` }}
            >
                <div className="absolute inset-0 bg-green-600/60" />

                <div className="relative z-10 w-full">{children}</div>
            </main>

            {/* Footer */}
            <footer className="bg-green-700 py-3" />
        </div>
    );
}
