@extends('emails.layouts.base')

@section('title', 'Seu plano vence em 3 dias')
@section('heading', 'Vencimento em 3 dias')

@section('content')
    <p>Olá, {{ $user->name }}! Suas portas expiram em
        <strong>{{ \Carbon\Carbon::parse($expiryDate)->format('d/m/Y') }}</strong>.
    </p>
    <p>Para evitar interrupções, recomendamos renovar antes do vencimento.</p>

    @if (!empty($details))
        <ul>
            <li>Quantidade de portas: {{ $details['ports_count'] ?? '—' }}</li>
            <li>Estimativa: R$ {{ number_format(($details['estimated_total_cents'] ?? 0) / 100, 2, ',', '.') }}</li>
        </ul>
    @endif
@endsection
