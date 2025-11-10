<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido de Créditos</title>
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

        .footer {
            margin-top: 20px;
            font-size: 12px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Pedido de Créditos Recebido</h1>
        <p>Olá, {{ $user->name }}! Recebemos seu pedido para adicionar créditos à sua conta.</p>
        <p>Detalhes do Pedido:</p>
        <ul>
            <li>Quantidade de créditos: {{ $userCredit->amount }}</li>
            <li>Valor total: R$ {{ $userCredit->price }}</li>
            <li>Referência do pedido: {{ $paymentReference->external_reference }}</li>
        </ul>
        <p>Para completar seu pedido e adicionar os créditos à sua conta, por favor, efetue o pagamento clicando no link abaixo:</p>
        <p><a href="{{ $paymentReference->init_point }}" target="_blank">Clique aqui para pagar</a></p>
        <p>Você receberá outro e-mail assim que o status do seu pagamento for atualizado.</p>
        <div class="footer">
            <p>Se você não solicitou esses créditos, por favor, entre em contato conosco imediatamente em <a href="mailto:contato@simplecorp.io">contato@simplecorp.io</a>.</p>
        </div>
    </div>
</body>


</html>