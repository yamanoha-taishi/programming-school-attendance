import { Link, usePage } from '@inertiajs/react';
import { ClipboardList, LayoutGrid, UserRound } from 'lucide-react';
import type { ReactNode } from 'react';
import { menu } from '@/routes/admin';
import { index as attendancesIndex } from '@/routes/admin/attendances';
import { edit as profileEdit } from '@/routes/profile';

type Tab = {
    label: string;
    href: string;
    icon: typeof ClipboardList;
    activePrefixes: string[];
    fullAccessOnly?: boolean;
};

const tabs: Tab[] = [
    {
        label: '出席記録',
        href: attendancesIndex().url,
        icon: ClipboardList,
        activePrefixes: ['/admin/attendances'],
    },
    {
        label: '管理',
        href: menu().url,
        icon: LayoutGrid,
        // 管理メニューから開く保護者・生徒・スタッフの画面でも「管理」を選択中にする
        activePrefixes: [
            '/admin/menu',
            '/admin/guardians',
            '/admin/students',
            '/admin/staff',
        ],
        fullAccessOnly: true,
    },
    {
        label: 'プロフィール',
        href: profileEdit().url,
        icon: UserRound,
        activePrefixes: ['/settings'],
    },
];

export default function StaffLayout({ children }: { children: ReactNode }) {
    const { url, props } = usePage();
    const visibleTabs = tabs.filter(
        (tab) => !tab.fullAccessOnly || props.auth.role === 'full_access',
    );

    return (
        <div className="min-h-dvh bg-background">
            <main className="pb-16">{children}</main>

            <nav className="fixed inset-x-0 bottom-0 flex border-t border-border bg-card">
                {visibleTabs.map((tab) => {
                    const isActive = tab.activePrefixes.some((prefix) =>
                        url.startsWith(prefix),
                    );
                    const Icon = tab.icon;

                    return (
                        <Link
                            key={tab.label}
                            href={tab.href}
                            className={`flex flex-1 flex-col items-center gap-1 pt-2.5 pb-2 text-[10px] ${
                                isActive
                                    ? 'font-bold text-primary'
                                    : 'text-text-faint'
                            }`}
                        >
                            <Icon className="size-[22px]" />
                            {tab.label}
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}
