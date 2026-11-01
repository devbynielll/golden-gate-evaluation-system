export default function EyeIcon({ off = false, className = 'h-5 w-5' }) {
    if (off) {
        return (
            <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth={2}
                strokeLinecap="round"
                strokeLinejoin="round"
                className={className}
                aria-hidden="true"
            >
                <path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 10 8 10 8a17.4 17.4 0 0 1-3.13 4.51m-2.7 2.02A9.16 9.16 0 0 1 12 20c-7 0-10-8-10-8a17.4 17.4 0 0 1 4.06-5.94" />
                <path d="M9.53 9.53a3 3 0 0 0 4.24 4.24" />
                <path d="M2 2l20 20" />
            </svg>
        );
    }

    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2}
            strokeLinecap="round"
            strokeLinejoin="round"
            className={className}
            aria-hidden="true"
        >
            <path d="M2 12s3-8 10-8 10 8 10 8-3 8-10 8-10-8-10-8Z" />
            <circle cx="12" cy="12" r="3" />
        </svg>
    );
}
