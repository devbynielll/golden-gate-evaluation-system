import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import CheckCircleIcon from '@/Components/Icons/CheckCircleIcon';
import XIcon from '@/Components/Icons/XIcon';

// shows the "status" message flashed by back()->with('status', ...)
export default function FlashStatus() {
    const { flash } = usePage().props;
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (flash?.status) setVisible(true);
    }, [flash?.status]);

    if (!visible || !flash?.status) return null;

    return (
        <div className="mx-auto mb-4 flex w-full max-w-5xl items-center gap-2 rounded-md border border-green-200 bg-green-50 px-4 py-2.5 text-sm text-green-800 shadow-sm">
            <CheckCircleIcon className="h-4 w-4 shrink-0 text-green-600" />
            <span className="flex-1">{flash.status}</span>
            <button
                type="button"
                onClick={() => setVisible(false)}
                className="text-green-500 transition hover:text-green-700"
            >
                <XIcon className="h-4 w-4" />
            </button>
        </div>
    );
}
