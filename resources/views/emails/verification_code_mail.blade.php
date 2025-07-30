<!DOCTYPE html>
<html>

<head>
    <title>Código de verificação</title>
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
        <div class="header">
            <h1>Verificação de Email</h1>
        </div>
        <div class="content">
            <h1>Olá, {{ $user->name }}!</h1>
            <p>Codigo de verificação: </p>
            <p>{{ $verificationToken }}</p>
            <div class="footer">
                <p>Este é um e-mail automático; por favor, não responda. Se você não solicitou este e-mail, por favor,
                    ignore-o ou entre em contato conosco em <a
                        href="mailto:suporte@bcopy.co">suporte@bcopy.co</a>.</p>
            </div>
        </div>
    </div>
</body>

</html>
