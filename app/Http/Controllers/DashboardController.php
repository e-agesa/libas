<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $now = now();

        return Inertia::render('Dashboard', [
            'stats' => [
                'todayOrders' => Invoice::whereDate('date', $now->toDateString())->count(),
                'pendingInvoices' => Invoice::whereIn('status', ['issued', 'overdue'])->count(),
                'activeClients' => Client::where('status', 'active')->count(),
                'monthRevenue' => Invoice::where('status', 'paid')
                    ->whereMonth('date', $now->month)
                    ->whereYear('date', $now->year)
                    ->sum('total'),
            ],
            'recentOrders' => Invoice::with('client:id,name')
                ->select('id', 'client_id', 'invoice_number', 'date', 'total', 'status')
                ->latest('date')
                ->limit(10)
                ->get()
                ->map(fn ($inv) => [
                    'id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'client' => $inv->client->name ?? '—',
                    'date' => $inv->date->format('d M Y'),
                    'total' => $inv->total,
                    'status' => $inv->status,
                ]),
        ]);
    }
}
