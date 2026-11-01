import ChangePasswordModal from '@/Components/ChangePasswordModal';
import FlashStatus from '@/Components/FlashStatus';
import LockIcon from '@/Components/LockIcon';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import ChevronDownIcon from '@/Components/Icons/ChevronDownIcon';
import LogoutIcon from '@/Components/Icons/LogoutIcon';
import logo from '@/Assets/logo.png';
import { Link } from '@inertiajs/react';
import { useState } from 'react';

// student shell: header with role dropdown, content, footer
export default function StudentLayout({ children }) {
    const [showPasswordModal, setShowPasswordModal] = useState(false);

    return (
        <div className="flex min-h-screen w-full flex-col bg-gray-100 font-sans">
            {/* Header */}
            <header className="bg-green-700 px-4 py-3 sm:px-8 sm:py-4">
                <div className="mx-auto flex w-full max-w-5xl items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <img
                            src={logo}
                            alt="Golden Gate Colleges logo"
                            className="h-10 w-10 shrink-0 sm:h-12 sm:w-12"
                        />
                        <div className="leading-tight">
                            <h1 className="text-sm font-extrabold uppercase tracking-wide text-white sm:text-lg md:text-xl">
                                Anonymous Monitoring and Evaluation System
                            </h1>
                            <p className="text-[10px] font-semibold uppercase tracking-wide text-white/90 sm:text-xs">
                                Golden Gate Colleges
                            </p>
                        </div>
                    </div>

                    <Menu as="div" className="relative shrink-0">
                        <MenuButton className="flex items-center gap-1.5 rounded-full bg-green-600 px-4 py-1.5 text-xs font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-500">
                            Student
                            <ChevronDownIcon className="h-3.5 w-3.5" />
                        </MenuButton>
                        <MenuItems className="absolute right-0 z-10 mt-1 w-44 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5 focus:outline-none">
                            <MenuItem>
                                <button
                                    type="button"
                                    onClick={() => setShowPasswordModal(true)}
                                    className="flex w-full items-center gap-1.5 px-4 py-2 text-xs font-bold uppercase tracking-wide text-gray-600 data-[focus]:bg-gray-100"
                                >
                                    <LockIcon className="h-3.5 w-3.5" />
                                    Change Password
                                </button>
                            </MenuItem>
                            <MenuItem>
                                <Link
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                    className="flex w-full items-center gap-1.5 px-4 py-2 text-xs font-bold uppercase tracking-wide text-gray-600 data-[focus]:bg-gray-100"
                                >
                                    <LogoutIcon className="h-3.5 w-3.5" />
                                    Log Out
                                </Link>
                            </MenuItem>
                        </MenuItems>
                    </Menu>
                </div>
            </header>

            {/* Content */}
            <main className="flex flex-1 justify-center px-4 py-12 sm:px-8">
                <div className="w-full max-w-5xl">
                    <FlashStatus />
                    {children}
                </div>
            </main>

            {/* Footer */}
            <footer className="bg-green-700 py-3" />

            <ChangePasswordModal
                show={showPasswordModal}
                onClose={() => setShowPasswordModal(false)}
            />
        </div>
    );
}
