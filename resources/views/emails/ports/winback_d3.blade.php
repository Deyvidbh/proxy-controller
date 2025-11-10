@extends('emails.layouts.base')

@section('title', 'Tudo certo por aí?')
@section('heading', 'Podemos ajudar?')

@section('content')
    <p>Oi, {{ $user->name }}! Notamos que o pagamento da fatura (venc.
        {{ \Carbon\Carbon::parse($expiryDate)->format('d/m/Y') }}) segue pendente.</p>
    <p>Aconteceu algo? Podemos te ajudar com a renovação ou com algum problema técnico.</p>
    <p>
        ou fale com <a href="mailto:contato@simplecorp.io">contato@simplecorp.io</a>.
    </p>
@endsection
