<?php

namespace App\Http\Controllers\Squid;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\SquidPort;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

use Inertia\Inertia;

class PortsController extends Controller
{
    public static function middleware(): array
    {
        return [
            'throttle:30,1'
        ];
    }

    /**
     * Exibe a página das pórtas, já passando os dados necessários como props.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $ports = $user->squidPorts()->get();

        // Renderiza o componente Vue e passa os dados diretamente
        return Inertia::render('Dashboard/Ports/Index', [
            'ports' => $ports,
        ]);
    }

    /**
     * Testa a conexão de uma porta proxy.
     */
    public function testProxy(Request $request, SquidPort $port)
    {
        if ($request->user()->id !== $port->user_id) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        $client = new Client([
            'timeout' => 10.0,
        ]);

        $proxyUrl = sprintf(
            'http://%s:%s@%s:%d',
            $port->username,
            $port->password,
            $port->host,
            $port->port
        );

        try {
            $response = $client->get('https://ipv4.icanhazip.com', [
                'proxy' => $proxyUrl,
            ]);

            $ip = trim($response->getBody()->getContents());

            return response()->json(['ip' => $ip]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                return response()->json(['error' => 'Erro: ' . $e->getResponse()->getStatusCode()], 500);
            }
            return response()->json(['error' => 'Não foi possível conectar. Verifique o host/firewall.'], 500);
        }
    }

    /**
     * Alterna o status de auto-renovação de uma porta.
     */
    public function toggleRenovation(Request $request, SquidPort $port)
    {
        // Valida que o usuário só pode modificar suas próprias portas
        if ($request->user()->id !== $port->user_id) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        // Valida o input
        $request->validate([
            'auto_renovation' => 'required|boolean',
        ]);

        // Atualiza o estado e salva no banco de dados
        $port->auto_renovation = $request->auto_renovation;
        $port->save();

        return back()->with('message', 'Status de renovação atualizado com sucesso!');
    }
}
