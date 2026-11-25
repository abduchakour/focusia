import { type NavItem, type SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { BookOpenCheck, FileText, LayoutGrid, Library, Tags, UsersRound } from 'lucide-vue-next';
import { computed } from 'vue';

/** Items shown directly in the phone bottom bar; the others go in the "Plus" panel. */
const MOBILE_PRIMARY = 4;

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
                  { title: 'Supports', href: route('teacher.materials.index'), icon: FileText },
                  { title: 'Bibliothèque', href: route('teacher.resources.index'), icon: Library },
                  { title: 'Notions', href: route('teacher.notions.index'), icon: Tags },
              ]
            : [
                  { title: 'Accueil', href: route('student.dashboard'), icon: LayoutGrid },
                  { title: 'Quiz', href: route('student.quizzes.index'), icon: BookOpenCheck },
                  { title: 'Supports', href: route('student.materials.index'), icon: FileText },
                  { title: 'Bibliothèque', href: route('student.resources.index'), icon: Library },
                  { title: 'Groupes', href: route('student.groups.index'), icon: UsersRound },
              ],
    );

    const mobileItems = computed(() => items.value.slice(0, MOBILE_PRIMARY));
    const moreItems = computed(() => items.value.slice(MOBILE_PRIMARY));

    /** Active when the current URL is the item or one of its sub-pages (dashboard: exact match). */
    const isActive = (href: string) => {
        const path = new URL(href, window.location.origin).pathname;
        const current = page.url.split('?')[0];
        const isRoot = path === '/enseignant' || path === '/etudiant';

        return isRoot ? current === path : current === path || current.startsWith(path + '/');
    };

    return { items, mobileItems, moreItems, isActive };
}
