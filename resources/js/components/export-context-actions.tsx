import { Check, Copy, Download, FileJson, FileText } from 'lucide-react';
import { useState } from 'react';
import type { ReactElement } from 'react';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

type ExportFormat = 'json' | 'markdown';

const formatDetails: Record<
    ExportFormat,
    { label: string; description: string; icon: typeof FileJson }
> = {
    json: {
        label: 'JSON',
        description: 'Structured data for apps and agents',
        icon: FileJson,
    },
    markdown: {
        label: 'Markdown',
        description: 'Readable snapshot for notes and documents',
        icon: FileText,
    },
};

export function ExportContextActions({
    trigger,
}: {
    trigger: ReactElement;
}) {
    const [open, setOpen] = useState(false);
    const [format, setFormat] = useState<ExportFormat>('json');
    const [status, setStatus] = useState<'idle' | 'copying' | 'copied' | 'error'>('idle');

    const exportUrl = `/export/context?format=${format}`;

    async function copyExport() {
        setStatus('copying');

        try {
            const response = await fetch(exportUrl, {
                headers: { Accept: format === 'json' ? 'application/json' : 'text/markdown' },
            });

            if (!response.ok) {
                throw new Error('Export request failed');
            }

            await navigator.clipboard.writeText(await response.text());
            setStatus('copied');
        } catch {
            setStatus('error');
        }
    }

    function selectFormat(nextFormat: ExportFormat) {
        setFormat(nextFormat);

        setStatus('idle');
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger render={trigger} />
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Export financial context</DialogTitle>
                    <DialogDescription>
                        Choose a format, then copy it directly or download a file.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-2">
                    {(Object.keys(formatDetails) as ExportFormat[]).map((option) => {
                        const details = formatDetails[option];
                        const Icon = details.icon;

                        return (
                            <button
                                key={option}
                                type="button"
                                onClick={() => selectFormat(option)}
                                className="flex items-center gap-3 rounded-lg border p-3 text-left transition-colors hover:bg-muted aria-pressed:border-primary aria-pressed:bg-muted"
                                aria-pressed={format === option}
                            >
                                <Icon data-icon="inline-start" />
                                <span className="flex-1">
                                    <span className="block text-sm font-medium">
                                        {details.label}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {details.description}
                                    </span>
                                </span>
                                {format === option && <Check aria-label="Selected" />}
                            </button>
                        );
                    })}
                </div>

                <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <Button variant="outline" onClick={copyExport} disabled={status === 'copying'}>
                        <Copy data-icon="inline-start" />
                        {status === 'copying'
                            ? 'Copying…'
                            : status === 'copied'
                              ? 'Copied'
                              : 'Copy'}
                    </Button>
                    <Button render={<a href={exportUrl} />} onClick={() => setOpen(false)}>
                        <Download data-icon="inline-start" />
                        Download {formatDetails[format].label}
                    </Button>
                </div>
                {status === 'error' && (
                    <p className="text-sm text-destructive">
                        Copy failed. Please try downloading the export instead.
                    </p>
                )}
            </DialogContent>
        </Dialog>
    );
}
