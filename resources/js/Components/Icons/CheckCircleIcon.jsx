export default function CheckCircleIcon({ className = 'h-10 w-10' }) {
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
            <circle cx="12" cy="12" r="10" />
            <path d="m8 12.5 2.5 2.5L16 9.5" />
        </svg>
    );
}
