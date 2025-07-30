<!DOCTYPE html>
<html>

<head>
    <title>Sua conta foi criada</title>
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
            <h1>Bem-vindo ao Bcopy Proxy!</h1>
        </div>
        <div class="content">
            <h1>Olá, {{ $user->name }}!</h1>
            <p>Sua conta foi criada com sucesso.</p>
        </div>
        <div class="footer">
            <p>Obrigado por se juntar a nós!</p>
        </div>
    </div>
</body>

</html>
