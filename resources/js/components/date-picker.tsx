import { format, isValid, parse } from 'date-fns';
import { CalendarIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

type DatePickerProps = {
    id?: string;
    value?: string | null;
    placeholder?: string;
    disabled?: boolean;
    onChange: (value: string) => void;
};

function parseDate(value?: string | null) {
    if (!value) {
        return undefined;
    }

    const date = parse(value, 'yyyy-MM-dd', new Date());

    return isValid(date) ? date : undefined;
}

export function DatePicker({
    id,
    value,
    placeholder = 'Pick a date',
    disabled,
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
