import type { PropsWithChildren } from 'react';

import {
    Sheet,
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
    return (
        <Sheet open onOpenChange={(open) => !open && onClose()}>
            <SheetContent
                side="right"
                className="w-full gap-0 sm:max-w-none lg:w-[min(82vw,88rem)]"
            >
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
