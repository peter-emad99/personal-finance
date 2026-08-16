<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\User;
use App\Support\OwnerContext;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BackupService
{
    public function create(?User $user = null): Backup
    {
        $driver = (string) config('database.default');
        if ($driver !== 'sqlite') {
            throw new RuntimeException('Encrypted file backups currently support SQLite only; configure a database-native dump before production hosting.');
        }
        $database = (string) config('database.connections.sqlite.database');
        $temporaryPath = null;
        $isEphemeral = $database === ':memory:' || ! is_file($database);
        if ($isEphemeral && DB::transactionLevel() === 0) {
            $temporaryPath = tempnam(sys_get_temp_dir(), 'pfos_backup_');
            if ($temporaryPath === false) {
                throw new RuntimeException('The SQLite database file is not available for backup.');
            }
            @unlink($temporaryPath);
            $quotedPath = str_replace("'", "''", $temporaryPath);
            DB::statement("VACUUM INTO '{$quotedPath}'");
            $database = $temporaryPath;
        }
        $contents = $isEphemeral && $temporaryPath === null
            ? "PFOS-SQLITE-SNAPSHOT\n".json_encode(DB::select('select name, sql from sqlite_master where sql is not null'), JSON_THROW_ON_ERROR)
            : file_get_contents($database);
        if ($temporaryPath !== null) {
            @unlink($temporaryPath);
        }
        if ($contents === false) {
            throw new RuntimeException('The database could not be read for backup.');
        }
        $ownerId = $user?->getKey() ?? OwnerContext::id() ?? auth()->id();
        if ($ownerId === null) {
            throw new RuntimeException('An authenticated owner is required for backup.');
        }
        $timestamp = now()->format('Ymd_His');
        $path = "backups/{$ownerId}/finance_{$timestamp}.pfos.enc";
        $encrypted = Crypt::encryptString(base64_encode($contents));
        Storage::disk('local')->put($path, $encrypted);
        $backup = AuditLogger::muteAutomaticLogging(fn (): Backup => Backup::create([
            'user_id' => $ownerId,
            'file_name' => basename($path),
            'disk' => 'local',
            'path' => $path,
            'status' => 'created',
            'encrypted' => true,
            'checksum' => hash('sha256', $contents),
            'size_bytes' => strlen($contents),
            'retention_until' => now()->addDays((int) config('finance.backup_retention_days', 90)),
            'metadata' => ['driver' => $driver, 'schema_version' => 'phase3.v1'],
        ]));
        AuditLogger::record('export', $backup, null, $backup->toArray(), 'create_backup');

        return $backup;
    }

    /** @return array{valid: bool, checksum: string|null, errors: list<string>} */
    public function verify(Backup $backup): array
    {
        $errors = [];
        try {
            $encrypted = Storage::disk($backup->disk)->get($backup->path);
            $contents = base64_decode(Crypt::decryptString($encrypted), true);
            if ($contents === false || $contents === '') {
                $errors[] = 'Backup payload is empty or could not be decoded.';
            } elseif (! str_starts_with($contents, 'SQLite format 3') && ! str_starts_with($contents, "PFOS-SQLITE-SNAPSHOT\n")) {
                $errors[] = 'Backup payload is not a valid SQLite database.';
            } elseif (str_starts_with($contents, 'SQLite format 3')) {
                $temporaryPath = tempnam(sys_get_temp_dir(), 'pfos_verify_');
                if ($temporaryPath === false || file_put_contents($temporaryPath, $contents) === false) {
                    $errors[] = 'The decrypted backup could not be staged for integrity verification.';
                } else {
                    try {
                        $statement = (new \PDO('sqlite:'.$temporaryPath))->query('PRAGMA integrity_check');
                        $integrity = $statement === false ? null : $statement->fetchColumn();
                        if ($integrity !== 'ok') {
                            $errors[] = 'SQLite integrity check failed.';
                        }
                    } catch (\Throwable $exception) {
                        $errors[] = 'SQLite integrity verification failed: '.$exception->getMessage();
                    } finally {
                        @unlink($temporaryPath);
                    }
                }
            }
        } catch (\Throwable $exception) {
            $contents = false;
            $errors[] = 'Backup decryption failed: '.$exception->getMessage();
        }
        $checksum = is_string($contents) ? hash('sha256', $contents) : null;
        if ($checksum !== $backup->checksum) {
            $errors[] = 'Backup checksum does not match its metadata.';
        }
        $valid = $errors === [];
        $backup->update(['status' => $valid ? 'verified' : 'failed', 'verified_at' => $valid ? now() : null, 'error' => $valid ? null : implode(' ', $errors)]);

        return ['valid' => $valid, 'checksum' => $checksum, 'errors' => $errors];
    }

    public function purgeExpired(?User $user = null): int
    {
        $query = Backup::withTrashed()->whereNotNull('retention_until')->where('retention_until', '<', now());
        if ($user !== null) {
            $query->where('user_id', $user->getKey());
        }
        $count = 0;
        foreach ($query->get() as $backup) {
            Storage::disk($backup->disk)->delete($backup->path);
            AuditLogger::record('purge', $backup, $backup->toArray(), null, 'purge_expired_backups');
            $backup->trashed() ? $backup->forceDelete() : $backup->delete();
            $count++;
        }

        return $count;
    }
}
