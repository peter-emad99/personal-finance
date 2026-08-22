<?php

namespace App\Http\Controllers;

use Database\Seeders\DemoWorkspaceSeeder;
use Illuminate\Http\RedirectResponse;

class DemoWorkspaceController extends Controller
{
    public function reset(): RedirectResponse
    {
        abort_unless(! app()->isProduction(), 404);
        abort_unless(request()->user()?->email === config('finance.demo_email'), 403);

        app(DemoWorkspaceSeeder::class)->reset();

        return back()->with('success', 'The demo workspace was reset to its original learning data.');
    }
}
