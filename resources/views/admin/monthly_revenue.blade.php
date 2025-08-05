@extends(backpack_view('blank'))

@php
    // Usa o Carbon, que já vem com o Laravel, e traduz o nome do mês
    $currentMonthName = \Carbon\Carbon::now()->translatedFormat('F \d\e Y');
@endphp

@section('header')
    <div class="container-fluid">
        <h2>
            <span class="text-capitalize">{{ $title }}</span>
        </h2>
    </div>
@endsection

@section('content')
    {{-- Widgets com resumos do mês atual --}}
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">💰 Faturamento ({{ $currentMonthName }})</div>
                <div class="card-body">
                    <h4 class="card-title">R$ {{ number_format($currentMonthRevenue, 2, ',', '.') }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">✅ Pagamentos Aprovados ({{ $currentMonthName }})</div>
                <div class="card-body">
                    <h4 class="card-title">{{ $currentMonthPaymentsCount }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">📊 Ticket Médio ({{ $currentMonthName }})</div>
                <div class="card-body">
                    <h4 class="card-title">R$ {{ number_format($currentMonthAverageTicket, 2, ',', '.') }}</h4>
                </div>
            </div>
        </div>
    </div>

    {{-- Gráfico de Faturamento Mensal --}}
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">Evolução do Faturamento Mensal</div>
                <div class="card-body">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after_scripts')
    {{-- Inclui o Chart.js (se ainda não estiver globalmente disponível) --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('revenueChart').getContext('2d');

            // Converte os dados do PHP para JavaScript
            const chartLabels = @json($chartLabels);
            const chartValues = @json($chartValues);

            const revenueChart = new Chart(ctx, {
                type: 'bar', // Tipo de gráfico: barra
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Faturamento Mensal (R$)',
                        data: chartValues,
                        backgroundColor: 'rgba(75, 192, 192, 0.6)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                // Formata o eixo Y para parecer com dinheiro
                                callback: function(value, index, values) {
                                    return 'R$ ' + value.toLocaleString('pt-BR');
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += new Intl.NumberFormat('pt-BR', {
                                            style: 'currency',
                                            currency: 'BRL'
                                        }).format(context.parsed.y);
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
@endpush
