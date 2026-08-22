import { useEffect, useState } from 'react';
import type { PropsWithChildren, ReactElement } from 'react';

import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

export function FormModal({
    title,
    description,
    onClose,
    children,
}: PropsWithChildren<{
    title: string;
    description?: string;
    onClose: () => void;
}>) {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        const frame = requestAnimationFrame(() => setOpen(true));

        return () => cancelAnimationFrame(frame);
    }, []);

    return (
        <Sheet
            open={open}
            onOpenChange={setOpen}
            onOpenChangeComplete={(nextOpen) => {
                if (!nextOpen) {
                    onClose();
                }
            }}
        >
            <SheetContent side="right" className="gap-0">
                <SheetHeader className="border-b pr-12">
                    <SheetTitle>{title}</SheetTitle>
                    {description && (
                        <SheetDescription>{description}</SheetDescription>
                    )}
                </SheetHeader>
                <div className="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
                    {children}
                </div>
            </SheetContent>
        </Sheet>
    );
}

export function FormModalClose({ children }: { children: ReactElement }) {
    return <SheetClose render={children} />;
}
