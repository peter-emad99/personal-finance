<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\BackupService;
use App\Support\OwnerContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class Phase3SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_routes_require_authentication(): void
    {
        auth()->logout();
        OwnerContext::clear();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('assets.index'))->assertRedirect(route('login'));
    }

    public function test_login_rotates_a_session_and_security_headers_are_present(): void
    {
        $owner = User::query()->where('email', config('finance.owner_email'))->firstOrFail();
        $owner->update(['password' => Hash::make('a-strong-owner-password')]);
        auth()->logout();
        OwnerContext::clear();
        $sessionId = session()->getId();

        $response = $this->from(route('login'))->post(route('login.store'), ['email' => $owner->email, 'password' => 'a-strong-owner-password']);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($owner);
        $this->assertNotSame($sessionId, session()->getId());
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
    }

    public function test_financial_records_are_isolated_between_users(): void
    {
        $owner = User::query()->where('email', config('finance.owner_email'))->firstOrFail();
        $other = User::create(['name' => 'Other user', 'email' => 'other@example.test', 'password' => Hash::make('another-secure-password')]);
        $this->actingAs($owner);
        $owned = Asset::create(['name' => 'Owner cash', 'type' => 'Cash', 'currency' => 'EGP', 'current_value_egp' => 100, 'liquidity' => 'immediate']);
        $this->actingAs($other);
        $otherAsset = Asset::create(['name' => 'Other cash', 'type' => 'Cash', 'currency' => 'EGP', 'current_value_egp' => 200, 'liquidity' => 'immediate']);

        $this->assertSame(1, Asset::count());
        $this->assertSame($otherAsset->id, Asset::firstOrFail()->id);
        $this->assertTrue($owner->can('view', $owned));
        $this->assertFalse($other->can('view', $owned));
        $this->get(route('assets.index'))->assertOk()->assertInertia(fn ($page) => $page->where('assets.0.name', 'Other cash'));
        $this->get(route('assets.index', ['asset' => $owned->id]))->assertOk();
        $this->assertDatabaseHas('assets', ['id' => $owned->id, 'user_id' => $owner->id]);
    }

    public function test_a_user_cannot_spoof_another_owner_id_on_create_or_update(): void
    {
        $other = User::create(['name' => 'Other user', 'email' => 'spoof@example.test', 'password' => Hash::make('another-secure-password')]);

        $this->expectException(LogicException::class);
        Asset::create(['user_id' => $other->id, 'name' => 'Spoofed', 'type' => 'Cash', 'currency' => 'EGP', 'current_value_egp' => 1, 'liquidity' => 'immediate']);
    }

    public function test_mutations_create_owner_audit_records_with_before_and_after_state(): void
    {
        $asset = Asset::create(['name' => 'Audited cash', 'type' => 'Cash', 'currency' => 'EGP', 'current_value_egp' => 100, 'liquidity' => 'immediate']);
        $asset->update(['current_value_egp' => 125]);
        $asset->delete();

        $logs = AuditLog::query()->where('entity_id', $asset->id)->orderBy('id')->get();
        $this->assertTrue($logs->contains('action', 'create'));
        $this->assertTrue($logs->contains('action', 'update'));
        $this->assertTrue($logs->contains('action', 'archive'));
        $update = $logs->firstWhere('action', 'update');
        $this->assertSame(100, data_get($update?->before_state, 'current_value_egp'));
        $this->assertSame('125.00', data_get($update?->after_state, 'current_value_egp'));
        $this->assertSame(auth()->id(), $update?->user_id);
    }

    public function test_audit_log_is_append_only(): void
    {
        $asset = Asset::create(['name' => 'Immutable audit', 'type' => 'Cash', 'currency' => 'EGP', 'current_value_egp' => 10, 'liquidity' => 'immediate']);
        $audit = AuditLog::query()->where('entity_id', $asset->id)->latest()->firstOrFail();

        $this->expectException(LogicException::class);
        $audit->update(['action' => 'tampered']);
    }

    public function test_encrypted_backup_can_be_created_and_verified(): void
    {
        $backup = app(BackupService::class)->create(auth()->user());
        $this->assertTrue($backup->encrypted);
        $result = app(BackupService::class)->verify($backup);

        $this->assertTrue($result['valid']);
        $this->assertSame('verified', $backup->fresh()->status);
        $this->assertSame(auth()->id(), $backup->user_id);
    }

    public function test_expired_backup_purge_requires_retention_boundary(): void
    {
        $backup = app(BackupService::class)->create(auth()->user());
        $backup->update(['retention_until' => now()->subDay()]);

        $this->assertSame(1, app(BackupService::class)->purgeExpired(auth()->user()));
        $this->assertSoftDeleted('backups', ['id' => $backup->id]);
    }
}
