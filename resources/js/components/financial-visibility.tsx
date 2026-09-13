import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useMemo,
    useState,
} from 'react';
import type { PropsWithChildren } from 'react';

type FinancialVisibilityContextValue = {
    isMasked: boolean;
    mask: () => void;
    setMasked: (masked: boolean) => void;
    toggleMasked: () => void;
};

const FinancialVisibilityContext =
    createContext<FinancialVisibilityContextValue | null>(null);

export function FinancialVisibilityProvider({
    children,
    enableShortcut = false,
}: PropsWithChildren<{ enableShortcut?: boolean }>) {
    const [isMasked, setIsMaskedState] = useState(true);

    const setMasked = useCallback((masked: boolean) => {
        setIsMaskedState(masked);
    }, []);

    const mask = useCallback(() => {
        setIsMaskedState(true);
    }, []);

    const toggleMasked = useCallback(() => {
        setIsMaskedState((current) => !current);
    }, []);

    useEffect(() => {
        if (!enableShortcut) {
            return;
        }

        const handleKeyDown = (event: KeyboardEvent) => {
            const target = event.target;
            const isTyping =
                target instanceof HTMLElement &&
                (target.matches('input, textarea, select') ||
                    target.isContentEditable);

            if (
                isTyping ||
                event.repeat ||
                event.metaKey ||
                event.ctrlKey ||
                event.altKey ||
                event.key.toLowerCase() !== 'm'
            ) {
                return;
            }

            event.preventDefault();
            setIsMaskedState(true);
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [enableShortcut]);

    const value = useMemo(
        () => ({ isMasked, mask, setMasked, toggleMasked }),
        [isMasked, mask, setMasked, toggleMasked],
    );

    return (
        <FinancialVisibilityContext.Provider value={value}>
            {children}
        </FinancialVisibilityContext.Provider>
    );
}

export function useFinancialVisibility() {
    const context = useContext(FinancialVisibilityContext);

    if (!context) {
        throw new Error(
            'useFinancialVisibility must be used inside FinancialVisibilityProvider',
        );
    }

    return context;
}

export function FinancialValue({
    children,
    className,
}: PropsWithChildren<{ className?: string }>) {
    const { isMasked } = useFinancialVisibility();

    return (
        <span
            className={className}
            data-financial-value
            data-masked={isMasked}
            aria-label={isMasked ? 'Financial value hidden' : undefined}
        >
            {isMasked ? '••••' : children}
        </span>
    );
}
