import { router } from '@inertiajs/react';
import { Button, Card, CardHeader, PageHeader } from '@/components/app-shell';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type Backup = {
    id: number;
    fileName: string;
    status: string;
    encrypted: boolean;
    sizeBytes: number;
    checksum: string;
    verifiedAt: string | null;
    retentionUntil: string | null;
    archived: boolean;
};

export default function Operations({ backups }: { backups: Backup[] }) {
    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Operations"
                title="Backups & data health"
                description="Create encrypted local backups, verify recovery material, and run owner-scoped integrity checks before operating on important data."
                action={
                    <div className="flex flex-wrap gap-2">
                        <Button
                            onClick={() => router.post('/operations/backups')}
                        >
                            Create encrypted backup
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => router.post('/operations/integrity')}
                        >
                            Run integrity check
                        </Button>
                    </div>
                }
            />
            <Card>
                <CardHeader
                    title="Backup history"
                    meta="Backups are encrypted with APP_KEY and retained according to FINANCE_BACKUP_RETENTION_DAYS."
                />
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>File</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Retention</TableHead>
                            <TableHead>Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {backups.map((backup) => (
                            <TableRow key={backup.id}>
                                <TableCell>
                                    <div className="font-medium">
                                        {backup.fileName}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {Math.round(backup.sizeBytes / 1024)} KB
                                        ·{' '}
                                        {backup.encrypted
                                            ? 'encrypted'
                                            : 'not encrypted'}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    {backup.status}
                                    {backup.verifiedAt
                                        ? ` · verified ${new Date(backup.verifiedAt).toLocaleDateString()}`
                                        : ''}
                                </TableCell>
                                <TableCell>
                                    Retention until{' '}
                                    {backup.retentionUntil ?? 'manual review'}
                                </TableCell>
                                <TableCell>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    `/operations/backups/${backup.id}/verify`,
                                                )
                                            }
                                        >
                                            Verify
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            href={`/operations/backups/${backup.id}/download`}
                                        >
                                            Download encrypted file
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                        {backups.length === 0 && (
                            <TableRow>
                                <TableCell
                                    colSpan={4}
                                    className="py-10 text-center text-muted-foreground"
                                >
                                    No backup exists yet.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </Card>
        </div>
    );
}
