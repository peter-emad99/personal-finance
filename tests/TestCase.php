<?php

namespace Tests;

use App\Models\User;
use App\Support\OwnerContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $owner = User::query()->where('email', config('finance.owner_email'))->first();
        if ($owner !== null) {
            OwnerContext::set($owner);
            $this->actingAs($owner);
        }
    }
}
