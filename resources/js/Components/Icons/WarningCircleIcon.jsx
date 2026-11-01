export default function WarningCircleIcon({ className = 'h-10 w-10' }) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.75}
            strokeLinecap="round"
            strokeLinejoin="round"
            className={className}
            aria-hidden="true"
        >
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7.5v6" />
            <circle cx="12" cy="16.5" r="0.75" fill="currentColor" stroke="none" />
        </svg>
    );
}
