import { Dialog, DialogPanel, DialogTitle, Transition, TransitionChild } from '@headlessui/react';
import WarningCircleIcon from '@/Components/Icons/WarningCircleIcon';

export default function ConfirmDeleteModal({
    show,
    title = 'Delete?',
    description = 'This cannot be undone.',
    confirmLabel = 'Yes, delete!',
    cancelLabel = 'Cancel',
    onCancel,
    onConfirm,
}) {
    return (
        <Transition show={show} leave="duration-200">
            <Dialog
                as="div"
                className="fixed inset-0 z-[60] flex transform items-center justify-center overflow-y-auto px-4 py-6 transition-all"
                onClose={onCancel}
            >
                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="absolute inset-0 bg-gray-500/75" />
                </TransitionChild>

                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0 translate-y-4 sm:scale-95"
                    enterTo="opacity-100 translate-y-0 sm:scale-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                    leaveTo="opacity-0 translate-y-4 sm:scale-95"
                >
                    <DialogPanel className="relative w-full max-w-sm transform overflow-hidden rounded-xl border-2 border-indigo-400 bg-white shadow-2xl transition-all">
                        <div className="px-8 py-8 text-center">
                            <WarningCircleIcon className="mx-auto h-14 w-14 text-yellow-400" />

                            <DialogTitle className="mt-4 text-xl font-extrabold text-gray-900">
                                {title}
                            </DialogTitle>
                            <p className="mt-1 text-sm text-gray-500">
                                {description}
                            </p>

                            <div className="mt-6 flex items-center justify-center gap-3">
                                <button
                                    type="button"
                                    onClick={onConfirm}
                                    className="rounded-md bg-green-700 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-green-800"
                                >
                                    {confirmLabel}
                                </button>
                                <button
                                    type="button"
                                    onClick={onCancel}
                                    className="rounded-md bg-gray-300 px-5 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-gray-400"
                                >
                                    {cancelLabel}
                                </button>
                            </div>
                        </div>
                    </DialogPanel>
                </TransitionChild>
            </Dialog>
        </Transition>
    );
}
