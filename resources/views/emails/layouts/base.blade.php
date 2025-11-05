<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@yield('title', 'Notificação')</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
            color: #333
        }

        .container {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 40px auto
        }

        h1 {
            color: #007bff
        }

        .footer {
            margin-top: 20px;
            font-size: 12px;
            text-align: center
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            text-decoration: none;
            border-radius: 4px;
            border: 1px solid #007bff
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>@yield('heading', 'Notificação')</h1>

        @yield('content')

        <div class="footer">
            <p>Precisa de ajuda? Fale com: <a href="mailto:suporte@bcopy.co">suporte@bcopy.co</a>.</p>
        </div>
    </div>
</body>

</html>
