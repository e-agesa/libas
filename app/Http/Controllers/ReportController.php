<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Collection;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
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

        // Revenue by item source (custom vs collection)
        $paidInvoiceIds = Invoice::where('status', 'paid')
            ->whereBetween('date', [$from, $to])
            ->pluck('id');

        $customRevenue = InvoiceLineItem::whereIn('invoice_id', $paidInvoiceIds)
            ->where(function ($q) {
                $q->where('item_type', 'custom')->orWhereNull('item_type');
            })
            ->sum('line_total');

        $collectionRevenue = InvoiceLineItem::whereIn('invoice_id', $paidInvoiceIds)
            ->where('item_type', 'collection')
            ->sum('line_total');

        // Top selling collection items
        $topCollectionItems = InvoiceLineItem::where('item_type', 'collection')
            ->whereIn('invoice_id', $paidInvoiceIds)
            ->select('collection_id', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(line_total) as total_revenue'))
            ->groupBy('collection_id')
            ->orderByDesc('total_revenue')
            ->limit(5)
            ->with('collection:id,name,sku')
            ->get();

        // Collection stock summary
        $collectionStats = [
            'totalItems' => Collection::count(),
            'totalStockValue' => (float) Collection::selectRaw('SUM(price * stock_qty) as val')->value('val') ?? 0,
            'lowStockCount' => Collection::whereColumn('stock_qty', '<=', 'low_stock_threshold')->count(),
        ];

        // Monthly revenue chart (last 6 months) — split by source
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
                'customRevenue' => (float) $customRevenue,
                'collectionRevenue' => (float) $collectionRevenue,
                'totalClients' => $totalClients,
                'totalContacts' => $totalContacts,
                'totalMeasurements' => $totalMeasurements,
                'totalExpenses' => (float) $totalExpenses,
                'netProfit' => $netProfit,
            ],
            'collectionStats' => $collectionStats,
            'topCollectionItems' => $topCollectionItems,
            'monthlyRevenue' => $monthlyRevenue,
            'garmentBreakdown' => $garmentBreakdown,
            'topClients' => $topClients,
            'statusDistribution' => $statusDistribution,
            'expensesByCategory' => $expensesByCategory,
        ]);
    }
}
