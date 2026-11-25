import { type NavItem, type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { BookOpenCheck, LayoutGrid, Tags, UserRound, UsersRound } from 'lucide-vue-next';
import { computed } from 'vue';

/**
 * Menu of the current role, shared by the desktop sidebar and the mobile bottom bar.
 * The server enforces the same split on every route; this only hides useless links.
 */
export function useNavigation() {
    const page = usePage<SharedData>();

    const items = computed<NavItem[]>(() =>
        page.props.auth.user.role === 'teacher'
            ? [
                  { title: 'Accueil', href: route('teacher.dashboard'), icon: LayoutGrid },
                  { title: 'Groupes', href: route('teacher.groups.index'), icon: UsersRound },
                  { title: 'Quiz', href: route('teacher.quizzes.index'), icon: BookOpenCheck },
                  { title: 'Notions', href: route('teacher.notions.index'), icon: Tags },
              ]
            : [
                  { title: 'Accueil', href: route('student.dashboard'), icon: LayoutGrid },
                  { title: 'Quiz', href: route('student.quizzes.index'), icon: BookOpenCheck },
                  { title: 'Groupes', href: route('student.groups.index'), icon: UsersRound },
              ],
    );

    const mobileItems = computed<NavItem[]>(() => [...items.value, { title: 'Profil', href: route('profile.edit'), icon: UserRound }]);

    /** Active when the current URL is the item or one of its sub-pages (dashboard: exact match). */
    const isActive = (href: string) => {
        const path = new URL(href, window.location.origin).pathname;
        const current = page.url.split('?')[0];
        const isRoot = path === '/enseignant' || path === '/etudiant';

        return isRoot ? current === path : current === path || current.startsWith(path + '/');
    };

    return { items, mobileItems, isActive };
}
