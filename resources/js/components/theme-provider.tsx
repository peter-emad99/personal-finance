import * as React from 'react';

export type ThemeMode = 'dark' | 'light' | 'system';

type ThemeContextValue = {
    theme: ThemeMode;
    setTheme: (theme: ThemeMode) => void;
};

const ThemeContext = React.createContext<ThemeContextValue | null>(null);
const STORAGE_KEY = 'personal-finance-theme';

function getSystemTheme(): 'dark' | 'light' {
    return window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
}

function applyTheme(theme: ThemeMode) {
    const resolvedTheme = theme === 'system' ? getSystemTheme() : theme;
    const root = document.documentElement;

    root.classList.remove('light', 'dark');
    root.classList.add(resolvedTheme);
    root.style.colorScheme = resolvedTheme;
}

export function ThemeProvider({
    children,
    defaultTheme = 'system',
}: React.PropsWithChildren<{ defaultTheme?: ThemeMode }>) {
    const [theme, setThemeState] = React.useState<ThemeMode>(() => {
        if (typeof window === 'undefined') {
            return defaultTheme;
        }

        return (
            (window.localStorage.getItem(STORAGE_KEY) as ThemeMode | null) ??
            defaultTheme
        );
    });

    React.useEffect(() => {
        applyTheme(theme);
        window.localStorage.setItem(STORAGE_KEY, theme);

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
        setThemeState(nextTheme);
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
