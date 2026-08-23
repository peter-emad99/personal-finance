import { format, isValid, parse } from 'date-fns';
import { CalendarIcon, ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

type DatePickerProps = {
    id?: string;
    value?: string | null;
    placeholder?: string;
    disabled?: boolean;
    required?: boolean;
    onChange: (value: string) => void;
};

function parseDate(value?: string | null) {
    if (!value) {
        return undefined;
    }

    const date = parse(value, 'yyyy-MM-dd', new Date());

    return isValid(date) ? date : undefined;
}

function parseMonth(value?: string | null) {
    if (!value) {
        return undefined;
    }

    const date = parse(value, 'yyyy-MM', new Date());

    return isValid(date) ? date : undefined;
}

export function DatePicker({
    id,
    value,
    placeholder = 'Pick a date',
    disabled,
    required,
    onChange,
}: DatePickerProps) {
    const date = parseDate(value);

    return (
        <Popover>
            <PopoverTrigger
                render={
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        disabled={disabled}
                        aria-required={required}
                        data-empty={!date}
                        className="w-full justify-start text-left font-normal data-[empty=true]:text-muted-foreground"
                    />
                }
            >
                <CalendarIcon data-icon="inline-start" />
                {date ? (
                    format(date, 'MMM d, yyyy')
                ) : (
                    <span>{placeholder}</span>
                )}
            </PopoverTrigger>
            <PopoverContent align="start" className="w-auto p-0">
                <Calendar
                    mode="single"
                    selected={date}
                    onSelect={(nextDate) =>
                        onChange(nextDate ? format(nextDate, 'yyyy-MM-dd') : '')
                    }
                />
            </PopoverContent>
        </Popover>
    );
}

type MonthPickerProps = {
    id?: string;
    value?: string | null;
    placeholder?: string;
    disabled?: boolean;
    required?: boolean;
    className?: string;
    ariaLabel?: string;
    onChange: (value: string) => void;
};

export function MonthPicker({
    id,
    value,
    placeholder = 'Pick a month',
    disabled,
    required,
    className,
    ariaLabel,
    onChange,
}: MonthPickerProps) {
    const month = parseMonth(value);
    const [visibleYear, setVisibleYear] = useState(
        month?.getFullYear() ?? new Date().getFullYear(),
    );

    const selectMonth = (monthIndex: number) => {
        const selected = new Date(visibleYear, monthIndex, 1);

        onChange(format(selected, 'yyyy-MM'));
    };

    return (
        <Popover>
            <PopoverTrigger
                render={
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        disabled={disabled}
                        aria-required={required}
                        aria-label={ariaLabel}
                        data-empty={!month}
                        className={cn(
                            'w-full justify-start text-left font-normal data-[empty=true]:text-muted-foreground',
                            className,
                        )}
                    />
                }
            >
                <CalendarIcon data-icon="inline-start" />
                {month ? (
                    format(month, 'MMMM yyyy')
                ) : (
                    <span>{placeholder}</span>
                )}
            </PopoverTrigger>
            <PopoverContent align="start" className="w-72 p-3">
                <div className="flex items-center justify-between gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Previous year"
                        onClick={() => setVisibleYear((year) => year - 1)}
                    >
                        <ChevronLeft />
                    </Button>
                    <p className="text-sm font-semibold">{visibleYear}</p>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label="Next year"
                        onClick={() => setVisibleYear((year) => year + 1)}
                    >
                        <ChevronRight />
                    </Button>
                </div>
                <div className="grid grid-cols-3 gap-2">
                    {Array.from({ length: 12 }, (_, monthIndex) => {
                        const option = new Date(visibleYear, monthIndex, 1);
                        const selected =
                            month?.getFullYear() === visibleYear &&
                            month.getMonth() === monthIndex;

                        return (
                            <Button
                                key={monthIndex}
                                type="button"
                                variant={selected ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => selectMonth(monthIndex)}
                            >
                                {format(option, 'MMM')}
                            </Button>
                        );
                    })}
                </div>
            </PopoverContent>
        </Popover>
    );
}
