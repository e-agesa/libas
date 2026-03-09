<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::query()->latest('date');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('paid_to', 'like', "%{$search}%");
            });
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('date', '<=', $to);
        }

        $expenses = $query->paginate(min((int) $request->input('per_page', 15), 100))->withQueryString();

        $summary = [
            'total' => Expense::when($from, fn ($q) => $q->whereDate('date', '>=', $from))
                              ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
                              ->sum('amount'),
            'count' => Expense::when($from, fn ($q) => $q->whereDate('date', '>=', $from))
                              ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
                              ->count(),
        ];

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'filters' => $request->only(['search', 'category', 'from', 'to', 'per_page']),
            'categories' => Expense::categories(),
            'summary' => $summary,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0.01|max:99999999',
            'date' => 'required|date',
            'paid_to' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);

        $validated['recorded_by'] = $request->user()->id;

        Expense::create($validated);

        return redirect()->back()
            ->with('success', 'Expense recorded successfully.');
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'description' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0.01|max:99999999',
            'date' => 'required|date',
            'paid_to' => 'nullable|string|max:255',
            'payment_method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);

        $expense->update($validated);

        return redirect()->back()
            ->with('success', 'Expense updated successfully.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return redirect()->back()
            ->with('success', 'Expense deleted successfully.');
    }
}
