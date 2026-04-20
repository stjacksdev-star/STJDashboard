import { computed, ref } from 'vue';

const storageKey = 'stj-dashboard-theme';
const theme = ref(resolveInitialTheme());

applyTheme(theme.value);

function resolveInitialTheme() {
    if (typeof window === 'undefined') {
        return 'light';
    }

    const storedTheme = window.localStorage.getItem(storageKey);

    if (storedTheme === 'dark' || storedTheme === 'light') {
        return storedTheme;
    }

    return 'light';
}

function applyTheme(value) {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.dataset.theme = value;
    document.documentElement.style.colorScheme = value;
}

function setTheme(value) {
    theme.value = value === 'dark' ? 'dark' : 'light';

    if (typeof window !== 'undefined') {
        window.localStorage.setItem(storageKey, theme.value);
    }

    applyTheme(theme.value);
}

export function useTheme() {
    return {
        theme,
        isDark: computed(() => theme.value === 'dark'),
        setTheme,
        toggleTheme: () => setTheme(theme.value === 'dark' ? 'light' : 'dark'),
    };
}
