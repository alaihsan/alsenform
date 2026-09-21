import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface SessionItem {
    id: string;
    ip_address: string | null;
    is_current_device: boolean;
    last_active: string;
    agent: {
        platform: string;
        browser: string;
        is_desktop: boolean;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string | null;
    avatar_url?: string | null;
    role?: 'admin' | 'guru' | 'siswa' | string;
    is_admin?: boolean;
    nis?: string | null;
    kelas?: string | null;
    nip?: string | null;
    phone?: string | null;
    subject?: string | null;
    school_origin?: string | null;
    quiz_preferences?: Record<string, any> | null;
    has_proctor_pin?: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;

