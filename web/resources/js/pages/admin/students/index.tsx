import { Head } from '@inertiajs/react';
import PageHeader from '@/components/admin/page-header';
import { menu } from '@/routes/admin';

// 仮のページ。一覧の中身は、生徒一覧画面の実装のときに作る
export default function StudentsIndex() {
    return (
        <>
            <Head title="生徒一覧" />
            <PageHeader title="生徒一覧" backHref={menu().url} />
        </>
    );
}
