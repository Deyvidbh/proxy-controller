<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atualização do Pedido de Créditos</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
            color: #333;
        }

        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 40px auto;
        }

        h1 {
            color: #007bff;
        }

        .status {
            font-size: 16px;
        }

        .pending {
            color: #FFA500;
            /* Laranja */
        }

        .approved {
            color: #008000;
            /* Verde */
        }

        .rejected {
            color: #FF0000;
            /* Vermelho */
        }

        .footer {
            margin-top: 20px;
            font-size: 12px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Atualização do Pedido de Créditos</h1>
        <p>Olá, {{ $user->name }}! Temos uma atualização sobre o seu pedido de créditos.</p>

        <p>Status do Pedido:
            <span class="status {{ $paymentReference->status }}">
                @switch($paymentReference->status)
                    @case('pending')
                        &#x1F551; Pendente
                    @break

                    @case('approved')
                        &#x2705; Aprovado
                    @break

                    @case('authorized')
                        &#x2705; Aprovado
                    @break

                    @case('rejected')
                        &#x274C; Não Aprovado
                    @break
                @endswitch
            </span>
        </p>

        <p>Detalhes do Pedido:</p>

        <ul>
            <li>Balanço atual: {{ $user->credits_balance }}</li>
    
            <li>Quantidade de créditos: {{ $userCredit->amount }}</li>
            <li>Valor total: R$ {{ $userCredit->price }}</li>
            <li>Referência do pedido: {{ $paymentReference->external_reference }}</li>
        </ul>

        @if ($paymentReference->status === 'pending')
            <p>Seu pagamento ainda está pendente. Para completar seu pedido, por favor, efetue o pagamento clicando no
                link abaixo:</p>
            <p><a href="{{ $paymentReference->init_point }}" target="_blank">Clique aqui para pagar</a></p>
        @elseif($paymentReference->status === 'approved' || $paymentReference->status === 'authorized')
            <p>Seu pagamento foi confirmado e seus créditos foram adicionados à sua conta. Obrigado por utilizar nossos
                serviços!</p>
        @endif

        <div class="footer">
            <p>Se você não solicitou esses créditos, ou se você tiver qualquer dúvida, por favor, entre em contato
                conosco em <a href="mailto:contato@simplecorp.io">contato@simplecorp.io</a>.</p>
        </div>
    </div>
</body>

</html>
