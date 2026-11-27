import { Head } from '@inertiajs/react';
import PageHeader from '@/components/admin/page-header';
import { menu } from '@/routes/admin';

// 仮のページ。一覧の中身は、スタッフ一覧画面の実装のときに作る
export default function StaffIndex() {
    return (
        <>
            <Head title="スタッフ一覧" />
            <PageHeader title="スタッフ一覧" backHref={menu().url} />
        </>
    );
}
