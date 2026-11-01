import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import BriefcaseIcon from '@/Components/Icons/BriefcaseIcon';
import BuildingIcon from '@/Components/Icons/BuildingIcon';
import ChartBarIcon from '@/Components/Icons/ChartBarIcon';
import ChecklistIcon from '@/Components/Icons/ChecklistIcon';
import ChevronDownIcon from '@/Components/Icons/ChevronDownIcon';
import HomeIcon from '@/Components/Icons/HomeIcon';
import LogoutIcon from '@/Components/Icons/LogoutIcon';
import FlashStatus from '@/Components/FlashStatus';
import logo from '@/Assets/logo.png';
import UserIcon from '@/Components/Icons/UserIcon';
import { Link, usePage } from '@inertiajs/react';

// admin shell: header, tabs, content, footer
const ROLE_LABELS = {
    super_admin: 'Super Admin',
    department_admin: 'Department Admin',
    office_admin: 'Office Admin',
};

export default function AdminLayout({ children }) {
    const role = usePage().props.auth.user.role;
    const isSuper = role === 'super_admin';
    const isDepartmentAdmin = role === 'department_admin';
    const isOfficeAdmin = role === 'office_admin';

    const criteriaActive =
        route().current('criteria.faculty') || route().current('criteria.office');
    const reportsActive =
        route().current('reports.faculty') || route().current('reports.office');

    const navItems = [
        {
            label: 'Home',
            icon: HomeIcon,
            href: route('dashboard'),
            active: route().current('dashboard'),
        },
        (isSuper || isDepartmentAdmin) && {
            label: 'Department',
            icon: BuildingIcon,
            href: route('departments.index'),
            active:
                route().current('departments.index') ||
                route().current('departments.show') ||
                route().current('departments.programs.show'),
        },
        isSuper && {
            label: 'Offices',
            icon: BriefcaseIcon,
            href: route('offices.index'),
            active: route().current('offices.index'),
        },
        isSuper && {
            label: 'Accounts',
            icon: UserIcon,
            href: route('accounts.index'),
            active: route().current('accounts.index'),
        },
    ].filter(Boolean);

    const reportItems = [
        (isSuper || isDepartmentAdmin) && { label: 'Faculty', href: route('reports.faculty') },
        (isSuper || isOfficeAdmin) && { label: 'Offices', href: route('reports.office') },
    ].filter(Boolean);

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

                    <div className="flex shrink-0 items-center gap-4">
                        <span className="hidden text-xs font-bold uppercase tracking-wide text-white sm:inline">
                            {ROLE_LABELS[role] ?? 'Administrator'}
                        </span>

                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="flex items-center gap-1.5 rounded-full bg-green-600 px-4 py-1.5 text-xs font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-green-500"
                        >
                            <LogoutIcon className="h-3.5 w-3.5" />
                            Log Out
                        </Link>
                    </div>
                </div>
            </header>

            {/* Section tabs */}
            <nav className="border-b border-gray-200 bg-white px-4 py-2 shadow-sm sm:px-8">
                <div className="mx-auto flex w-full max-w-5xl items-center gap-1">
                    {navItems.map(({ label, icon: Icon, href, active }) => (
                        <Link
                            key={label}
                            href={href}
                            className={
                                'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-bold uppercase tracking-wide transition ' +
                                (active
                                    ? 'bg-green-100 text-green-700'
                                    : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700')
                            }
                        >
                            <Icon className="h-4 w-4" />
                            {label}
                        </Link>
                    ))}

                    {isDepartmentAdmin && (
                        <Link href={route('criteria.faculty')} className={'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-bold uppercase tracking-wide transition ' + (criteriaActive ? 'bg-green-100 text-green-700' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700')}>
                            <ChecklistIcon className="h-4 w-4" />
                            Faculty Criteria
                        </Link>
                    )}

                    {isOfficeAdmin && (
                        <Link href={route('criteria.office')} className={'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-bold uppercase tracking-wide transition ' + (criteriaActive ? 'bg-green-100 text-green-700' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700')}>
                            <ChecklistIcon className="h-4 w-4" />
                            Office Criteria
                        </Link>
                    )}

                    {isSuper && (
                    <Menu as="div" className="relative">
                        <MenuButton
                            className={
                                'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-bold uppercase tracking-wide transition ' +
                                (criteriaActive
                                    ? 'bg-green-100 text-green-700'
                                    : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700')
                            }
                        >
                            <ChecklistIcon className="h-4 w-4" />
                            Criteria
                            <ChevronDownIcon className="h-3 w-3" />
                        </MenuButton>
                        <MenuItems className="absolute left-0 z-10 mt-1 w-40 origin-top-left rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5 focus:outline-none">
                            <MenuItem>
                                <Link
                                    href={route('criteria.faculty')}
                                    className="block px-4 py-2 text-xs font-bold uppercase tracking-wide text-gray-600 data-[focus]:bg-gray-100"
                                >
                                    Faculty
                                </Link>
                            </MenuItem>
                            <MenuItem>
                                <Link
                                    href={route('criteria.office')}
                                    className="block px-4 py-2 text-xs font-bold uppercase tracking-wide text-gray-600 data-[focus]:bg-gray-100"
                                >
                                    Offices
                                </Link>
                            </MenuItem>
                        </MenuItems>
                    </Menu>
                    )}

                    <Menu as="div" className="relative">
                        <MenuButton
                            className={
                                'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-bold uppercase tracking-wide transition ' +
                                (reportsActive
                                    ? 'bg-green-100 text-green-700'
                                    : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700')
                            }
                        >
                            <ChartBarIcon className="h-4 w-4" />
                            Reports
                            <ChevronDownIcon className="h-3 w-3" />
                        </MenuButton>
                        <MenuItems className="absolute left-0 z-10 mt-1 w-40 origin-top-left rounded-md bg-white py-1 shadow-lg ring-1 ring-black/5 focus:outline-none">
                            {reportItems.map(({ label, href }) => (
                                <MenuItem key={label}>
                                    <Link
                                        href={href}
                                        className="block px-4 py-2 text-xs font-bold uppercase tracking-wide text-gray-600 data-[focus]:bg-gray-100"
                                    >
                                        {label}
                                    </Link>
                                </MenuItem>
                            ))}
                        </MenuItems>
                    </Menu>
                </div>
            </nav>

            {/* Content */}
            <main className="flex-1 px-4 py-6 sm:px-8">
                <FlashStatus />
                <div className="mx-auto w-full max-w-5xl">{children}</div>
            </main>

            {/* Footer */}
            <footer className="bg-green-700 py-3" />
        </div>
    );
}
