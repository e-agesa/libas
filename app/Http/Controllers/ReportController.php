<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Measurement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        // Revenue summary
        $invoicesInRange = Invoice::whereBetween('date', [$from, $to]);
        $totalRevenue = (clone $invoicesInRange)->where('status', 'paid')->sum('total');
        $totalInvoiced = (clone $invoicesInRange)->sum('total');
        $totalOutstanding = (clone $invoicesInRange)->whereIn('status', ['issued', 'overdue'])->sum('balance');
        $invoiceCount = (clone $invoicesInRange)->count();

        // Monthly revenue chart (last 6 months)
        $monthlyRevenue = Invoice::where('status', 'paid')
            ->where('date', '>=', now()->subMonths(5)->startOfMonth())
            ->select(
                DB::raw("DATE_FORMAT(date, '%Y-%m') as month"),
                DB::raw('SUM(total) as revenue')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => date('M Y', strtotime($row->month . '-01')),
                'revenue' => (float) $row->revenue,
            ]);

        // Garment breakdown
        $garmentBreakdown = Measurement::select('garment_type', DB::raw('COUNT(*) as count'))
            ->groupBy('garment_type')
            ->orderByDesc('count')
            ->get();

        // Top clients by revenue
        $topClients = Client::select('clients.id', 'clients.name')
            ->join('invoices', 'clients.id', '=', 'invoices.client_id')
            ->where('invoices.status', 'paid')
            ->whereBetween('invoices.date', [$from, $to])
            ->groupBy('clients.id', 'clients.name')
            ->selectRaw('SUM(invoices.total) as total_spent')
            ->orderByDesc('total_spent')
            ->limit(5)
            ->get();

        // Status distribution
        $statusDistribution = Invoice::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        // Expenses in date range
        $totalExpenses = Expense::whereBetween('date', [$from, $to])->sum('amount');
        $expensesByCategory = Expense::select('category', DB::raw('SUM(amount) as total'))
            ->whereBetween('date', [$from, $to])
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        // P&L
        $netProfit = (float) $totalRevenue - (float) $totalExpenses;

        // Quick counts
        $totalClients = Client::count();
        $totalContacts = Contact::count();
        $totalMeasurements = Measurement::count();

        return Inertia::render('Reports/Index', [
            'filters' => ['from' => $from, 'to' => $to],
            'summary' => [
                'totalRevenue' => (float) $totalRevenue,
                'totalInvoiced' => (float) $totalInvoiced,
                'totalOutstanding' => (float) $totalOutstanding,
                'invoiceCount' => $invoiceCount,
                'totalClients' => $totalClients,
                'totalContacts' => $totalContacts,
                'totalMeasurements' => $totalMeasurements,
                'totalExpenses' => (float) $totalExpenses,
                'netProfit' => $netProfit,
            ],
            'monthlyRevenue' => $monthlyRevenue,
            'garmentBreakdown' => $garmentBreakdown,
            'topClients' => $topClients,
            'statusDistribution' => $statusDistribution,
            'expensesByCategory' => $expensesByCategory,
        ]);
    }
}
