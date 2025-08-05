<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\PaymentReference;

class FaturamentoMensalController extends Controller
{
    public function dashboard()
    {
        // --- DADOS PARA O GRÁFICO ---
        // Busca o faturamento agrupado por mês, considerando apenas pagamentos aprovados.
        $revenueData = PaymentReference::where('status', 'approved')
            ->select(
                DB::raw('SUM(price) as total'),
                DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month")
            )
            ->groupBy('month')
            ->orderBy('month', 'asc')
            ->get();

        // Prepara os dados para o Chart.js
        $chartLabels = $revenueData->pluck('month');
        $chartValues = $revenueData->pluck('total');

        // --- MÉTRICAS DO MÊS ATUAL ---
        $currentMonthRevenue = PaymentReference::where('status', 'approved')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('price');

        $currentMonthPaymentsCount = PaymentReference::where('status', 'approved')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        $currentMonthAverageTicket = ($currentMonthPaymentsCount > 0)
            ? $currentMonthRevenue / $currentMonthPaymentsCount
            : 0;

        // Passa os dados para a view
        return view('admin.monthly_revenue', [
            'title'                     => 'Faturamento Mensal',
            'breadcrumbs'               => [
                trans('backpack::crud.admin') => backpack_url('dashboard'),
                'Faturamento Mensal'          => false,
            ],
            'chartLabels'               => $chartLabels,
            'chartValues'               => $chartValues,
            'currentMonthRevenue'       => $currentMonthRevenue,
            'currentMonthPaymentsCount' => $currentMonthPaymentsCount,
            'currentMonthAverageTicket' => $currentMonthAverageTicket,
        ]);
    }
}
