import { cleanup, render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type * as InertiaReact from '@inertiajs/react';
import MenuIndex from '@/pages/admin/menu/index';

// Head は Inertia のアプリの中でしか動かないため、テストでは何も出さないものに置き換える
vi.mock('@inertiajs/react', async (importOriginal) => ({
    ...(await importOriginal<typeof InertiaReact>()),
    Head: () => null,
}));

describe('管理メニュー画面', () => {
    afterEach(() => {
        cleanup();
    });

    it('見出しを出し、「戻る」は出さない', () => {
        render(<MenuIndex />);

        expect(
            screen.getByRole('heading', { name: '管理メニュー' }),
        ).toBeTruthy();
        expect(screen.queryByRole('link', { name: '戻る' })).toBeNull();
    });

    it('保護者・生徒・スタッフの一覧へ進む行を、この順に出す', () => {
        render(<MenuIndex />);

        const nav = screen.getByRole('navigation', { name: '管理メニュー' });
        const links = within(nav).getAllByRole('link');

        expect(
            links.map((link) => [link.textContent, link.getAttribute('href')]),
        ).toEqual([
            ['保護者一覧', '/admin/guardians'],
            ['生徒一覧', '/admin/students'],
            ['スタッフ一覧', '/admin/staff'],
        ]);
    });

    it('行の高さは44px以上にする', () => {
        render(<MenuIndex />);

        for (const link of screen.getAllByRole('link')) {
            expect(link.className).toContain('min-h-11');
        }
    });
});
