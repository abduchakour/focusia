import type { Directive } from 'vue';

/**
 * v-reveal: the element fades and rises into place the first time it scrolls into view
 * (CSS classes .reveal / .is-visible in app.css; disabled by "reduce motion").
 * Optional value = delay in ms, for staggered groups.
 */
export const vReveal: Directive<HTMLElement, number | undefined> = {
    mounted(el, binding) {
        el.classList.add('reveal');
        if (binding.value) el.style.transitionDelay = `${binding.value}ms`;

        if (!('IntersectionObserver' in window)) {
            el.classList.add('is-visible');
            return;
        }
        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((e) => e.isIntersecting)) {
                    el.classList.add('is-visible');
                    observer.disconnect();
                }
            },
            { threshold: 0.15, rootMargin: '0px 0px -40px 0px' },
        );
        observer.observe(el);
    },
};
