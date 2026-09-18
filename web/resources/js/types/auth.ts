export type User = {
    id: number;
    name: string;
    email: string | null;
    avatar?: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
};
