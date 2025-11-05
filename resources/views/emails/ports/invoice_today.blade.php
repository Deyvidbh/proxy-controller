@extends('emails.layouts.base')

@section('title', 'Fatura gerada')
@section('heading', 'Sua fatura está disponível')

@section('content')
    <p>Olá, {{ $user->name }}! Geramos sua fatura referente ao período que vence hoje
        (<strong>{{ \Carbon\Carbon::parse($expiryDate)->format('d/m/Y') }}</strong>).</p>
    <ul>
        <li>Número da fatura: {{ $invoiceNumber ?? '—' }}</li>
        <li>Valor: R$ {{ number_format(($totalCents ?? 0) / 100, 2, ',', '.') }}</li>
    </ul>
    @if (!empty($paymentUrl))
        <p><a class="btn" href="{{ $paymentUrl }}" target="_blank">Pagar agora</a></p>
    @else
        <p><a class="btn" href="{{ $invoiceUrl ?? url('/billing') }}" target="_blank">Ver fatura</a></p>
    @endif
@endsection
