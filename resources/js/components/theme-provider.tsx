import * as React from 'react';

export type ThemeMode = 'dark' | 'light' | 'system';

type ThemeContextValue = {
    theme: ThemeMode;
    setTheme: (theme: ThemeMode) => void;
};

const ThemeContext = React.createContext<ThemeContextValue | null>(null);
const STORAGE_KEY = 'personal-finance-theme';
const THEME_MODES: ThemeMode[] = ['system', 'light', 'dark'];

function isThemeMode(value: string | null): value is ThemeMode {
    return value !== null && THEME_MODES.includes(value as ThemeMode);
}

function getSystemTheme(): 'dark' | 'light' {
    return typeof window !== 'undefined' &&
        window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
}

function applyTheme(theme: ThemeMode) {
    const resolvedTheme = theme === 'system' ? getSystemTheme() : theme;
    const root = document.documentElement;

    root.classList.toggle('dark', resolvedTheme === 'dark');
    root.style.colorScheme = resolvedTheme;
}

function getStoredTheme(defaultTheme: ThemeMode): ThemeMode {
    if (typeof window === 'undefined') {
        return defaultTheme;
    }

    try {
        const storedTheme = window.localStorage.getItem(STORAGE_KEY);

        return isThemeMode(storedTheme) ? storedTheme : defaultTheme;
    } catch {
        return defaultTheme;
    }
}

export function ThemeProvider({
    children,
    defaultTheme = 'system',
}: React.PropsWithChildren<{ defaultTheme?: ThemeMode }>) {
    const [theme, setThemeState] = React.useState<ThemeMode>(() => {
        return getStoredTheme(defaultTheme);
    });

    React.useEffect(() => {
        applyTheme(theme);

        try {
            window.localStorage.setItem(STORAGE_KEY, theme);
        } catch {
            // The selected theme still applies when storage is unavailable.
        }

        const media = window.matchMedia('(prefers-color-scheme: dark)');
        const handleSystemChange = () => {
            if (theme === 'system') {
                applyTheme(theme);
            }
        };

        media.addEventListener('change', handleSystemChange);

        return () => media.removeEventListener('change', handleSystemChange);
    }, [theme]);

    const setTheme = React.useCallback((nextTheme: ThemeMode) => {
        applyTheme(nextTheme);
        setThemeState(nextTheme);

        try {
            window.localStorage.setItem(STORAGE_KEY, nextTheme);
        } catch {
            // The selected theme still applies when storage is unavailable.
        }
    }, []);

    return (
        <ThemeContext.Provider value={{ theme, setTheme }}>
            {children}
        </ThemeContext.Provider>
    );
}

export function useTheme() {
    const context = React.useContext(ThemeContext);

    if (!context) {
        throw new Error('useTheme must be used within a ThemeProvider.');
    }

    return context;
}
