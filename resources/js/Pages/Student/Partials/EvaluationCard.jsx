import CheckCircleIcon from '@/Components/Icons/CheckCircleIcon';
import PencilIcon from '@/Components/Icons/PencilIcon';
import logo from '@/Assets/logo.png';

// clickable card with a photo bg, a start/completed badge, and a footer bar
export default function EvaluationCard({
    image,
    tint,
    title,
    subtitle,
    onClick,
    completed = false,
}) {
    return (
        <button
            type="button"
            onClick={completed ? undefined : onClick}
            aria-disabled={completed}
            className={
                'block w-full max-w-sm overflow-hidden rounded-lg text-left shadow-sm transition ' +
                (completed ? 'cursor-default' : 'hover:shadow-md')
            }
        >
            <div
                className="relative flex h-36 items-center justify-center bg-cover bg-center"
                style={{ backgroundImage: `url(${image})` }}
            >
                {tint && <div className={`absolute inset-0 ${tint}`} />}

                {completed ? (
                    <span className="relative inline-flex items-center gap-1.5 rounded-full bg-white px-4 py-2 text-xs font-bold uppercase tracking-wide text-green-700 shadow-sm">
                        <CheckCircleIcon className="h-4 w-4 rounded-full bg-green-600 p-0.5 text-white" />
                        Completed
                    </span>
                ) : (
                    <span className="relative inline-flex items-center gap-1.5 rounded-full bg-white px-4 py-2 text-xs font-bold uppercase tracking-wide text-green-700 shadow-sm">
                        <PencilIcon className="h-3.5 w-3.5" />
                        Start Evaluating
                    </span>
                )}
            </div>

            <div className="flex items-center gap-2 bg-green-700 px-4 py-2.5">
                <img
                    src={logo}
                    alt=""
                    className="h-7 w-7 shrink-0 rounded-full bg-white/10"
                />
                <div className="leading-tight">
                    <p className="text-xs font-bold uppercase tracking-wide text-white">
                        {title}
                    </p>
                    {subtitle && (
                        <p className="text-[11px] text-white/80">
                            {subtitle}
                        </p>
                    )}
                </div>
            </div>
        </button>
    );
}
