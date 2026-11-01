export default function InfoCircleIcon({ className = 'h-4 w-4' }) {
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
                d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-1 6a1 1 0 1 1 2 0 1 1 0 0 1-2 0Zm.25 3.25h1.5a.75.75 0 0 1 .75.75v5a.75.75 0 0 1-1.5 0V12.75h-.75a.75.75 0 0 1 0-1.5Z"
                clipRule="evenodd"
            />
        </svg>
    );
}
