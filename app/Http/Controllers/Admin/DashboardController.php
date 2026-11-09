<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $revenue = Order::where('payment_status', 'verified')
            ->where('status', '!=', 'cancelled')
            ->sum('total');

        $statusCounts = Order::select('status', DB::raw('count(*) as c'))
            ->groupBy('status')->pluck('c', 'status');

        // Last 6 months of verified sales for the bar chart.
        $monthly = collect(range(5, 0))->map(function (int $back) {
            // startOfMonth first: subMonths() on the 31st would skip short months.
            $month = now()->startOfMonth()->subMonths($back);

            return [
                'label' => $month->format('M'),
                'total' => (float) Order::where('payment_status', 'verified')
                    ->where('status', '!=', 'cancelled')
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->sum('total'),
            ];
        });

        // Paid sales only, grouped by product so a rename doesn't split a row.
        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->where('orders.payment_status', 'verified')
            ->select(DB::raw('MAX(order_items.product_name) as product_name'), DB::raw('SUM(order_items.quantity) as sold'), DB::raw('SUM(order_items.line_total) as revenue'))
            ->groupBy(DB::raw('COALESCE(order_items.product_id, order_items.product_name)'))
            ->orderByDesc('sold')
            ->take(5)
            ->get();

        return view('admin.dashboard', [
            'revenue' => $revenue,
            'ordersTotal' => Order::count(),
            'pendingPayments' => Order::where('payment_status', 'submitted')->where('status', '!=', 'cancelled')->count(),
            'customersCount' => User::where('role', 'customer')->count(),
            'statusCounts' => $statusCounts,
            'monthly' => $monthly,
            'maxMonthly' => max(1.0, (float) $monthly->max('total')),
            'lowStock' => Product::active()->where('stock', '<=', 5)->orderBy('stock')->take(6)->get(),
            'recentOrders' => Order::with('user')->latest()->take(8)->get(),
            'topProducts' => $topProducts,
            'pendingReviews' => Review::where('is_approved', false)->count(),
            'unreadMessages' => ContactMessage::where('is_read', false)->count(),
        ]);
    }
}
