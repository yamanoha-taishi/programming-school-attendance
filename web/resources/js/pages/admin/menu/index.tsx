import { Head, Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import PageHeader from '@/components/admin/page-header';
import { index as guardiansIndex } from '@/routes/admin/guardians';
import { index as staffIndex } from '@/routes/admin/staff';
import { index as studentsIndex } from '@/routes/admin/students';

const items = [
    { label: '保護者一覧', href: guardiansIndex().url },
    { label: '生徒一覧', href: studentsIndex().url },
    { label: 'スタッフ一覧', href: staffIndex().url },
];

export default function MenuIndex() {
    return (
        <>
            <Head title="管理メニュー" />
            <PageHeader title="管理メニュー" />
            {/* 灰色の背景の上に、画面幅いっぱいの白い行を線で区切って並べる（角丸のカードにはしない） */}
            <nav aria-label="管理メニュー" className="mt-3">
                <ul className="border-t border-border">
                    {items.map((item) => (
                        <li key={item.href} className="border-b border-border">
                            {/* 別の画面へ進む行。行全体を押せるようにし、右に灰色の「›」 */}
                            <Link
                                href={item.href}
                                className="flex min-h-11 items-center justify-between bg-card px-3 py-2.5 text-base"
                            >
                                {item.label}
                                <ChevronRight className="size-4 text-text-faint" />
                            </Link>
                        </li>
                    ))}
                </ul>
            </nav>
        </>
    );
}
