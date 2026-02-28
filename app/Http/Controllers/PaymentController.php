<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function store(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $invoice->balance,
            'method' => 'required|in:cash,mpesa,bank_transfer,credit',
            'date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $invoice->payments()->create($validated);

        // Update invoice totals
        $newAmountPaid = $invoice->amount_paid + $validated['amount'];
        $newBalance = $invoice->total - $newAmountPaid;

        $invoice->update([
            'amount_paid' => $newAmountPaid,
            'balance' => $newBalance,
            'status' => $newBalance <= 0 ? 'paid' : $invoice->status,
        ]);

        return redirect()->back()
            ->with('success', 'Payment recorded successfully.');
    }
}
