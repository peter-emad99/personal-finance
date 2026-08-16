<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetValuation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssetValuationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('valuations', ['assets' => Asset::query()->orderBy('name')->get(), 'valuations' => AssetValuation::with('asset')->latest('valued_on')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        AssetValuation::create($this->rules($request));

        return back()->with('success', 'Dated valuation saved without overwriting history.');
    }

    public function update(Request $request, AssetValuation $valuation): RedirectResponse
    {
        $valuation->update($this->rules($request));

        return back()->with('success', 'Valuation updated.');
    }

    public function destroy(AssetValuation $valuation): RedirectResponse
    {
        $valuation->delete();

        return back()->with('success', 'Valuation archived.');
    }

    public function restore(int $valuation): RedirectResponse
    {
        AssetValuation::withTrashed()->findOrFail($valuation)->restore();

        return back()->with('success', 'Valuation restored.');
    }

    /** @return array<string, mixed> */
    private function rules(Request $request): array
    {
        return $request->validate(['asset_id' => ['required', 'exists:assets,id'], 'valued_on' => ['required', 'date'], 'value_egp' => ['required', 'numeric', 'min:0'], 'quantity' => ['nullable', 'numeric', 'min:0'], 'currency' => ['required', 'string', 'size:3'], 'source' => ['required', 'string', 'max:120'], 'valuation_method' => ['required', 'string', 'max:80'], 'notes' => ['nullable', 'string']]);
    }
}
