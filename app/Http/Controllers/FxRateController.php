<?php

namespace App\Http\Controllers;

use App\Models\FxRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FxRateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('fx-rates', ['rates' => FxRate::latest('rate_date')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        FxRate::create($this->rules($request));

        return back()->with('success', 'FX rate recorded.');
    }

    public function update(Request $request, FxRate $fxRate): RedirectResponse
    {
        $fxRate->update($this->rules($request));

        return back()->with('success', 'FX rate updated.');
    }

    public function destroy(FxRate $fxRate): RedirectResponse
    {
        $fxRate->delete();

        return back()->with('success', 'FX rate archived.');
    }

    public function restore(int $fxRate): RedirectResponse
    {
        FxRate::withTrashed()->findOrFail($fxRate)->restore();

        return back()->with('success', 'FX rate restored.');
    }

    /** @return array<string, mixed> */
    private function rules(Request $request): array
    {
        return $request->validate(['base_currency' => ['required', 'string', 'size:3'], 'quote_currency' => ['required', 'string', 'size:3', 'different:base_currency'], 'rate_date' => ['required', 'date'], 'rate' => ['required', 'numeric', 'gt:0'], 'source' => ['required', 'string', 'max:120'], 'method' => ['required', 'string', 'max:80'], 'notes' => ['nullable', 'string']]);
    }
}
