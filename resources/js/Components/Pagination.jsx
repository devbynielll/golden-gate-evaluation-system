export default function Pagination({ page, totalPages, onPageChange }) {
    if (totalPages <= 1) return null;

    return (
        <div className="flex items-center justify-between border-t border-gray-100 px-5 py-3">
            <p className="text-xs text-gray-400">
                Page {page} of {totalPages}
            </p>

            <div className="flex items-center gap-1">
                <button
                    type="button"
                    onClick={() => onPageChange(page - 1)}
                    disabled={page === 1}
                    className="rounded-md px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-gray-500 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
                >
                    Prev
                </button>

                {Array.from({ length: totalPages }, (_, i) => i + 1).map(
                    (number) => (
                        <button
                            key={number}
                            type="button"
                            onClick={() => onPageChange(number)}
                            className={
                                'h-7 w-7 rounded-md text-xs font-bold transition ' +
                                (number === page
                                    ? 'bg-green-700 text-white'
                                    : 'text-gray-500 hover:bg-gray-100')
                            }
                        >
                            {number}
                        </button>
                    ),
                )}

                <button
                    type="button"
                    onClick={() => onPageChange(page + 1)}
                    disabled={page === totalPages}
                    className="rounded-md px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-gray-500 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
                >
                    Next
                </button>
            </div>
        </div>
    );
}
