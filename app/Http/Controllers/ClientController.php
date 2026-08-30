<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\CompanySetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::query()
            ->withCount('contacts', 'invoices')
            ->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $clients = $query->paginate(min((int) $request->input('per_page', 10), 100))
            ->withQueryString();

        return Inertia::render('Clients/Index', [
            'clients' => $clients,
            'filters' => $request->only(['search', 'status', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:255',
            'phone' => 'required|string|max:20',
            'alt_phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'type' => 'nullable|in:individual,family,corporate,institution',
            'notes' => 'nullable|string|max:2000',
        ]);

        $client = Client::create($validated);

        // A client is a person too. Without this, every new client opened with an
        // empty Person list on the invoice screen and staff had to re-enter the
        // same name through "Add New Person" before they could bill anything.
        $self = $client->contacts()->create([
            'name' => $client->name,
            'relationship' => 'self',
            'phone' => $client->phone,
        ]);

        // Quick-add from the invoice wizard (axios): return the client instead
        // of redirecting, so the in-progress invoice is not lost. The shape must
        // match the wizard's own clients prop, contacts array included.
        if ($request->wantsJson()) {
            return response()->json([
                'id' => $client->id,
                'name' => $client->name,
                'phone' => $client->phone,
                'email' => $client->email,
                'contacts' => [$self->only(['id','client_id','name']) + ['measurements' => []]],
            ], 201);
        }

        return redirect()->route('clients.show', $client)
            ->with('success', 'Client created successfully.');
    }

    public function show(Client $client)
    {
        $client->loadCount('contacts', 'invoices');
        $client->load([
            'contacts.measurements',
            'invoices' => fn ($q) => $q->latest('date')->limit(20),
        ]);

        return Inertia::render('Clients/Show', [
            'client' => $client,
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:255',
            'phone' => 'required|string|max:20',
            'alt_phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1000',
            'type' => 'nullable|in:individual,family,corporate,institution',
            'notes' => 'nullable|string|max:2000',
        ]);

        $client->update($validated);

        return redirect()->back()
            ->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client)
    {
        if ($client->invoices()->whereNotIn('status', ['paid', 'voided'])->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete client with unpaid invoices.');
        }

        $client->delete();

        return redirect()->route('clients.index')
            ->with('success', 'Client deleted successfully.');
    }

    public function statement(Client $client)
    {
        $client->load([
            'invoices' => fn ($q) => $q->orderBy('date'),
        ]);

        $totalInvoiced = $client->invoices->where('status', '!=', 'voided')->sum('total');
        $totalPaid = $client->invoices->sum('amount_paid');
        $totalBalance = $client->invoices->sum('balance');

        $invoices = $client->invoices->map(fn ($inv) => [
            'id' => $inv->id,
            'invoice_number' => $inv->invoice_number,
            'date' => $inv->date->format('d M Y'),
            'due_date' => $inv->due_date?->format('d M Y'),
            'status' => $inv->status,
            'total' => $inv->total,
            'amount_paid' => $inv->amount_paid,
            'balance' => $inv->balance,
        ]);

        return Inertia::render('Clients/Statement', [
            'client' => $client->only('id', 'name', 'phone', 'email'),
            'invoices' => $invoices,
            'totalInvoiced' => $totalInvoiced,
            'totalPaid' => $totalPaid,
            'totalBalance' => $totalBalance,
        ]);
    }

    public function statementPdf(Client $client)
    {
        $client->load([
            'invoices' => fn ($q) => $q->with('payments')->orderBy('date'),
        ]);

        $company = CompanySetting::get();
        $totalInvoiced = $client->invoices->where('status', '!=', 'voided')->sum('total');
        $totalPaid = $client->invoices->sum('amount_paid');
        $totalBalance = $client->invoices->sum('balance');

        $pdf = Pdf::loadView('pdf.statement', compact('client', 'company', 'totalInvoiced', 'totalPaid', 'totalBalance'));

        return $pdf->download("Statement_{$client->name}_" . now()->format('Ymd') . '.pdf');
    }
}
