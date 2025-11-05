@extends('emails.layouts.base')

@section('title', 'Encerrando nossos lembretes')
@section('heading', 'Vamos encerrar os lembretes')

@section('content')
    <p>Olá, {{ $user->name }}! Essa é nossa última mensagem sobre a fatura vencida em
        {{ \Carbon\Carbon::parse($expiryDate)->format('d/m/Y') }}.</p>
    <p>Não vamos mais incomodar, mas estaremos aqui quando quiser voltar. Se decidir retomar, use o link abaixo:</p>

    <p>Obrigado pelo tempo e pela parceria até aqui.</p>
@endsection
