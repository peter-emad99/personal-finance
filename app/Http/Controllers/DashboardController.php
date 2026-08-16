<?php

namespace App\Http\Controllers;

use App\Services\FinanceService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(FinanceService $finance): Response
    {
        return Inertia::render('dashboard', $finance->dashboard());
    }
}
