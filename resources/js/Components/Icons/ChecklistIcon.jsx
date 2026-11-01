export default function ChecklistIcon({ className = 'h-4 w-4' }) {
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
            <path d="m3 6 1.5 1.5L7 5" />
            <path d="m3 12 1.5 1.5L7 11" />
            <path d="m3 18 1.5 1.5L7 17" />
            <path d="M11 6h10M11 12h10M11 18h10" />
        </svg>
    );
}
