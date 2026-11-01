export default function ShieldCheckBadgeIcon({ className = 'h-10 w-10' }) {
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
            <path d="M12 3 5 5.5V11c0 4.7 3 8.4 7 9.5 4-1.1 7-4.8 7-9.5V5.5L12 3Z" />
            <path d="m9 12 2 2 4-4" />
        </svg>
    );
}
