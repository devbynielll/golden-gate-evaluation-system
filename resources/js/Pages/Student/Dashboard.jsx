import studentWelcomeImage from '@/Assets/student-welcome-image.png';
import StudentLayout from '@/Layouts/StudentLayout';
import { Head, Link, usePage } from '@inertiajs/react';

export default function StudentDashboard() {
    const { auth } = usePage().props;

    return (
        <StudentLayout>
            <Head title="Welcome" />

            <div className="overflow-hidden rounded-lg bg-white p-6 shadow-sm sm:p-10">
                <div className="flex flex-col items-center gap-8 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900">
                            Welcome, {auth.user.name}!
                        </h1>

                        <p className="mt-4 text-sm leading-relaxed text-gray-600">
                            Your participation in this evaluation helps
                            improve the quality of education and student
                            services at Golden Gate Colleges. Your honest
                            feedback is valuable in identifying areas for
                            improvement and helping the college maintain high
                            standards in both academic instruction and the
                            services provided by its offices.
                        </p>

                        <p className="mt-4 text-sm leading-relaxed text-gray-600">
                            Please evaluate your professors and courses based
                            on your actual classroom experiences this term,
                            and provide feedback on the services you have
                            received from the different offices within the
                            college departments. Your insights are greatly
                            appreciated and play an important role in
                            improving teaching methods, learning experiences,
                            and student services.
                        </p>

                        <p className="mt-4 text-sm leading-relaxed text-gray-600">
                            Thank you for contributing to the continuous
                            growth and improvement of our educational
                            community!
                        </p>
                    </div>

                    <img
                        src={studentWelcomeImage}
                        alt=""
                        className="h-40 w-40 shrink-0 object-contain"
                    />
                </div>
            </div>

            <div className="mt-6 flex justify-end">
                <Link
                    href={route('evaluation.index')}
                    className="inline-flex items-center gap-2 rounded-md bg-green-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm transition hover:bg-green-500"
                >
                    Proceed to Evaluation
                    <span aria-hidden="true">&rarr;</span>
                </Link>
            </div>
        </StudentLayout>
    );
}
