<?php

namespace App\Http\Controllers;

use App\Models\Liability;
use App\Models\LiabilityPaymentRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LiabilityPaymentController extends Controller
{
    public function store(Request $request, Liability $liability): RedirectResponse
    {
        $liability->paymentRecords()->create($this->validated($request));

        return back()->with('success', 'Debt payment record saved.');
    }

    public function destroy(LiabilityPaymentRecord $payment): RedirectResponse
    {
        $payment->delete();

        return back()->with('success', 'Debt payment record archived.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'paid_on' => ['required', 'date'],
            'payment_egp' => ['required', 'numeric', 'gt:0'],
            'principal_egp' => ['required', 'numeric', 'min:0'],
            'interest_egp' => ['required', 'numeric', 'min:0'],
            'fees_egp' => ['nullable', 'numeric', 'min:0'],
            'balance_after_egp' => ['nullable', 'numeric', 'min:0'],
            'source' => ['required', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ]);

        $components = (float) $data['principal_egp'] + (float) $data['interest_egp'] + (float) ($data['fees_egp'] ?? 0);
        if (abs((float) $data['payment_egp'] - $components) > 0.01) {
            throw ValidationException::withMessages(['payment_egp' => 'Payment must equal principal plus interest plus fees.']);
        }

        return $data;
    }
}
