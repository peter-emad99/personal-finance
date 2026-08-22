<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\OwnerContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->where('email', config('finance.owner_email'))->firstOrFail();
        OwnerContext::set($owner);
        OwnerContext::clear();

        $this->call(DemoWorkspaceSeeder::class);
    }
}
