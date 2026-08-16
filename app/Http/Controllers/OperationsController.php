<?php

namespace App\Http\Controllers;

use App\Models\Backup;
use App\Services\BackupService;
use App\Services\IntegrityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('operations', [
            'backups' => Backup::withTrashed()->latest()->limit(20)->get()->map(fn (Backup $backup): array => [
                'id' => $backup->id,
                'fileName' => $backup->file_name,
                'status' => $backup->status,
                'encrypted' => $backup->encrypted,
                'sizeBytes' => $backup->size_bytes,
                'checksum' => $backup->checksum,
                'verifiedAt' => $backup->verified_at ? (string) $backup->verified_at : null,
                'retentionUntil' => $backup->retention_until ? (string) $backup->retention_until : null,
                'archived' => $backup->trashed(),
            ])->values(),
        ]);
    }

    public function createBackup(BackupService $backups): RedirectResponse
    {
        try {
            $backup = $backups->create(request()->user());

            return back()->with('success', "Encrypted backup {$backup->file_name} created.");
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Backup failed. Check the operations log and application logs.');
        }
    }

    public function verify(Backup $backup, BackupService $backups): RedirectResponse
    {
        $result = $backups->verify($backup);

        return back()->with($result['valid'] ? 'success' : 'error', $result['valid'] ? 'Backup verified successfully.' : implode(' ', $result['errors']));
    }

    public function integrity(IntegrityService $integrity): RedirectResponse
    {
        $result = $integrity->run();

        return back()->with($result['status'] === 'passed' ? 'success' : 'error', $result['status'] === 'passed' ? 'Data integrity checks passed.' : implode(' ', $result['findings']));
    }

    public function download(Backup $backup): StreamedResponse
    {
        return Storage::disk($backup->disk)->download($backup->path, $backup->file_name, ['Content-Type' => 'application/octet-stream']);
    }

    public function archive(Backup $backup): RedirectResponse
    {
        $backup->delete();

        return back()->with('success', 'Backup metadata archived; the encrypted file remains recoverable until retention purge.');
    }

    public function restore(int $backup): RedirectResponse
    {
        Backup::withTrashed()->findOrFail($backup)->restore();

        return back()->with('success', 'Backup metadata restored.');
    }
}
