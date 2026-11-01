export default function ShieldCheckIcon({ className = 'h-4 w-4' }) {
    return (
        <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="currentColor"
            className={className}
            aria-hidden="true"
        >
            <path
                fillRule="evenodd"
                d="M12 1.5c-.28 0-.56.06-.82.17l-7 3A2 2 0 0 0 3 6.5V11c0 5.06 3.41 9.42 8.4 10.9a2 2 0 0 0 1.2 0C17.59 20.42 21 16.06 21 11V6.5a2 2 0 0 0-1.18-1.83l-7-3a2.1 2.1 0 0 0-.82-.17Zm4.28 7.72-5 5a1 1 0 0 1-1.42 0l-2.5-2.5a1 1 0 1 1 1.42-1.42l1.79 1.8 4.29-4.3a1 1 0 0 1 1.42 1.42Z"
                clipRule="evenodd"
            />
        </svg>
    );
}
