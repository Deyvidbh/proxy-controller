@props([
    'status' => 'success', // success | error | expired
    'title' => 'Pagamento',
    'message' => '',
])

@php
    $palette = [
        'success' => ['bg' => '#ecfdf5', 'fg' => '#065f46', 'accent' => '#10b981', 'icon' => '✔'],
        'error' => ['bg' => '#fef2f2', 'fg' => '#991b1b', 'accent' => '#ef4444', 'icon' => '✖'],
        'expired' => ['bg' => '#fff7ed', 'fg' => '#9a3412', 'accent' => '#f59e0b', 'icon' => '⏰'],
    ][$status] ?? ['bg' => '#eef2ff', 'fg' => '#3730a3', 'accent' => '#6366f1', 'icon' => 'ℹ'];
@endphp

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        :root {
            --bg: {{ $palette['bg'] }};
            --fg: {{ $palette['fg'] }};
            --accent: {{ $palette['accent'] }};
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
            background: #0b1020;
            color: #0e1116;
        }

        .wrap {
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px;
        }

        .card {
            width: 100%;
            max-width: 560px;
            background: #fff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .18);
        }

        .banner {
            background: var(--bg);
            color: var(--fg);
            padding: 28px;
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: #fff;
            color: var(--accent);
            display: grid;
            place-items: center;
            font-size: 28px;
            border: 2px solid var(--accent);
        }

        .content {
            padding: 28px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 28px;
            color: #0f172a;
        }

        p {
            margin: 0;
            color: #334155;
            line-height: 1.6;
        }

        .actions {
            padding: 24px 28px 32px;
            display: flex;
            gap: 12px;
        }

        .btn {
            appearance: none;
            border: 0;
            padding: 12px 18px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-home {
            background: var(--accent);
            color: #0b0f19;
        }

        .btn-home:hover {
            filter: brightness(0.95);
        }

        .muted {
            color: #64748b;
            font-size: 13px;
            margin-left: auto;
        }

        @media (max-width: 420px) {
            .banner {
                flex-direction: column;
                align-items: flex-start;
            }

            .icon {
                width: 48px;
                height: 48px;
                font-size: 24px;
            }
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="card">
            <div class="banner">
                <div class="icon">{{ $palette['icon'] }}</div>
                <div>
                    <strong
                        style="display:block; font-size:14px; letter-spacing:.02em; color: var(--fg); text-transform:uppercase;">
                        {{ $status === 'success' ? 'Pagamento aprovado' : ($status === 'error' ? 'Falha no pagamento' : ($status === 'expired' ? 'Pagamento expirado' : 'Status')) }}
                    </strong>
                    <div style="height:6px;"></div>
                    <div style="width:56px; height:4px; background: var(--accent); border-radius:4px;"></div>
                </div>
            </div>

            <div class="content">
                <h1>{{ $title }}</h1>
                <p>{{ $message }}</p>
            </div>

            <div class="actions">
                <a class="btn btn-home" href="{{ url('/') }}">← Voltar para a página inicial</a>
            </div>
        </div>
    </div>
</body>

</html>
