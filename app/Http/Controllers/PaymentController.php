<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function store(Request $request, Invoice $invoice)
    {
        // The ceiling is checked again under a lock below. This first pass only
        // catches obvious nonsense and gives a friendly message.
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,mpesa,bank_transfer,credit',
            'date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Recording a payment was a read-modify-write on a stale copy of the
        // invoice: two payments taken at the same moment each read the same
        // amount_paid and the second overwrote the first, losing one of them.
        // The row is now locked for the whole calculation, and the balance is
        // re-read under that lock rather than trusted from the request.
        DB::transaction(function () use ($invoice, $validated) {
            $fresh = Invoice::whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            $balance = (float) $fresh->balance;

            if ((float) $validated['amount'] > $balance + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => $balance <= 0
                        ? 'This invoice is already settled.'
                        : 'That is more than the outstanding balance of ' . number_format($balance, 2) . '.',
                ]);
            }

            $fresh->payments()->create($validated);

            $newAmountPaid = (float) $fresh->amount_paid + (float) $validated['amount'];
            $newBalance = (float) $fresh->total - $newAmountPaid;

            $fresh->update([
                'amount_paid' => $newAmountPaid,
                'balance' => $newBalance,
                'status' => $newBalance <= 0 ? 'paid' : $fresh->status,
            ]);
        }, 3);

        return redirect()->back()
            ->with('success', 'Payment recorded successfully.');
    }
}
