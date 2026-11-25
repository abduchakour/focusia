import type { LucideIcon } from 'lucide-vue-next';

export type Role = 'teacher' | 'student';

export interface User {
    id: number;
    first_name: string;
    last_name: string;
    name: string; // full name, computed by the server
    email: string;
    role: Role;
    role_label: string;
    avatar?: string;
    email_verified_at: string | null;
}

export interface Auth {
    user: User;
}

export interface Flash {
    success: string | null;
    error: string | null;
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
    [key: string]: unknown;
    name: string;
    auth: Auth;
    flash: Flash;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export type BreadcrumbItemType = BreadcrumbItem;
