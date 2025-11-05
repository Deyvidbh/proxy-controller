@extends('emails.layouts.base')

@section('title', 'Sua fatura está em atraso')
@section('heading', 'Pagamento em atraso')

@section('content')
    <p>Olá, {{ $user->name }}! Constatamos que sua fatura com vencimento em
        <strong>{{ \Carbon\Carbon::parse($expiryDate)->format('d/m/Y') }}</strong> ainda não foi paga.
    </p>
@endsection
