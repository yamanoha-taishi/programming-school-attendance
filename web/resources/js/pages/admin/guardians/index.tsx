import { Head } from '@inertiajs/react';
import PageHeader from '@/components/admin/page-header';
import { menu } from '@/routes/admin';

// 仮のページ。一覧の中身は、保護者一覧画面の実装のときに作る
export default function GuardiansIndex() {
    return (
        <>
            <Head title="保護者一覧" />
            <PageHeader title="保護者一覧" backHref={menu().url} />
        </>
    );
}
