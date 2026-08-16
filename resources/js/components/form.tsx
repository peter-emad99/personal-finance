import * as React from 'react';
import type {
    InputHTMLAttributes,
    PropsWithChildren,
    SelectHTMLAttributes,
} from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

export function Field({
    label,
    className,
    ...props
}: InputHTMLAttributes<HTMLInputElement> & {
    label: string;
    className?: string;
}) {
    return (
        <label className={cn('grid gap-2', className)}>
            <Label>{label}</Label>
            <Input {...props} />
        </label>
    );
}

type Option = { value: string; label: React.ReactNode };

export function SelectField({
    label,
    children,
    value,
    defaultValue,
    onChange,
    className,
    name,
    ...props
}: PropsWithChildren<
    SelectHTMLAttributes<HTMLSelectElement> & {
        label: string;
        className?: string;
    }
>) {
    const options = React.Children.toArray(children).flatMap((child) => {
        if (!React.isValidElement(child) || child.type !== 'option') {
            return [];
        }

        const optionProps = child.props as {
            value?: string;
            children?: React.ReactNode;
        };

        return [
            {
                value: optionProps.value ?? String(optionProps.children ?? ''),
                label: optionProps.children,
            },
        ];
    });

    const currentValue = value == null ? undefined : String(value);
    const initialValue =
        defaultValue == null ? undefined : String(defaultValue);

    return (
        <label className={cn('grid gap-2', className)}>
            <Label>{label}</Label>
            <Select
                name={name}
                value={currentValue}
                defaultValue={initialValue}
                disabled={props.disabled}
                required={props.required}
                onValueChange={(next) => {
                    onChange?.({
                        target: { value: String(next ?? '') },
                    } as React.ChangeEvent<HTMLSelectElement>);
                }}
            >
                <SelectTrigger className="h-9 w-full">
                    <SelectValue placeholder="Select an option" />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option: Option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </label>
    );
}

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
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>
                {children}
            </DialogContent>
        </Dialog>
    );
}

export { Button };
