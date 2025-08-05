<?php

namespace App\Http\Controllers\Squid;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

use App\Models\SquidPort;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

use Inertia\Inertia;

use App\Services\UserCredits\UserCreditService;

class PortsController extends Controller
{
    protected $userCreditService;

    public function __construct(UserCreditService $userCreditService)
    {
        $this->userCreditService = $userCreditService;
    }

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
            'user' => [
                'auto_renovation' => $user->auto_renovation,
                'squid_username'  => $user->squid_username,
                'squid_password'  => $user->squid_password,
                'credits_balance' => $user->credits_balance,
            ],
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

        $user = $request->user();

        if (!$user->squid_username || !$user->squid_password || !$port->host || !$port->port) {
            return response()->json(['error' => 'Dados incompletos para testar o proxy.'], 400);
        }

        $proxyUrl = sprintf(
            'http://%s:%s@%s:%d',
            $user->squid_username,
            $user->squid_password,
            $port->host,
            $port->port
        );

        try {
            $client = new Client(['timeout' => 10.0]);

            $response = $client->get('https://ipv4.icanhazip.com', [
                'proxy' => $proxyUrl,
            ]);

            $ip = trim($response->getBody()->getContents());

            $port->output_ip_address = $ip;
            $port->saveQuietly();

            return response()->json(['ip' => $ip]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                return response()->json(['error' => 'Erro: ' . $e->getResponse()->getStatusCode()], 500);
            }

            return response()->json(['error' => 'Não foi possível conectar. Verifique o host/firewall.'], 500);
        }
    }

    public function rotateIp(Request $request, SquidPort $port)
    {
        $user = $request->user();

        if ($port->user_id !== $user->id) {
            return response()->json(['error' => 'Não autorizado'], 403);
        }

        // Respeita intervalo de 48h
        if ($port->last_update_ip && Carbon::parse($port->last_update_ip)->diffInHours(now()) < 48) {
            return response()->json([
                'error' => 'O IP desta porta só pode ser rotacionado a cada 48 horas.'
            ], 429);
        }

        $rotated = $port->assignNewIpForUser();

        if (!$rotated) {
            return response()->json([
                'error' => 'Não foi possível encontrar um IP disponível para esta porta.'
            ], 500);
        }

        return response()->json([
            'message' => 'IP rotacionado com sucesso.',
            'portId' => $port->id,
            'newOutputIp' => $port->output_ip_address,
        ]);
    }

    public function rotateAllIps(Request $request)
    {
        $user = $request->user();
        $updatedPorts = [];
        $skippedPorts = [];

        foreach ($user->squidPorts as $port) {
            if ($port->last_update_ip && Carbon::parse($port->last_update_ip)->diffInHours(now()) < 48) {
                $skippedPorts[] = [
                    'portId' => $port->id,
                    'reason' => 'Aguarde 48h para nova rotação.',
                ];
                continue;
            }

            $success = $port->assignNewIpForUser();

            if ($success) {
                $updatedPorts[] = [
                    'portId' => $port->id,
                    'newOutputIp' => $port->output_ip_address,
                ];
            } else {
                $skippedPorts[] = [
                    'portId' => $port->id,
                    'reason' => 'Nenhum IP disponível.',
                ];
            }
        }

        return response()->json([
            'message' => 'Processo de rotação finalizado.',
            'updatedPorts' => $updatedPorts,
            'skippedPorts' => $skippedPorts,
        ]);
    }

    /**
     * Alterna o status de auto-renovação de uma porta.
     */
    public function toggleRenovation(Request $request)
    {
        $request->validate([
            'auto_renovation' => 'required|boolean',
        ]);

        $user = $request->user();
        $user->auto_renovation = $request->auto_renovation;
        $user->save();

        return back()->with('message', 'Auto-renovação atualizada com sucesso.');
    }

    public function renewAllPorts(Request $request)
    {
        $user = $request->user();

        $ports = $user->squidPorts()->get();

        if ($ports->isEmpty()) {
            return response()->json(['message' => 'Você não possui portas para renovar.'], 400);
        }

        // Filtra portas que estão vencidas (ou vencem hoje)
        $renewablePorts = $ports->filter(function ($port) {
            return $port->expires_at !== null && $port->expires_at->lte(now());
        });

        if ($renewablePorts->isEmpty()) {
            return response()->json([
                'message' => 'Nenhuma porta disponível para renovação hoje. A renovação só pode ser feita no dia do vencimento.'
            ], 400);
        }

        $portCount = $renewablePorts->count();
        $costPerPort = $portCount >= 20 ? 66 : 70;
        $costPerPortReal = $portCount >= 20 ? 330 : 350;
        $totalCost = $costPerPort * $portCount;

        if ($user->credits_balance < $totalCost) {
            return response()->json([
                'message' => "Você precisa de {$totalCost} créditos para renovar {$portCount} porta(s). Saldo atual: {$user->credits_balance} créditos."
            ], 400);
        }

        DB::beginTransaction();

        try {
            foreach ($renewablePorts as $port) {
                $port->expires_at = now()->addDays(30);
                $port->last_renovation = now();
                $port->save();
            }

            $creditData = [
                'balance'            => $user->credits_balance - $totalCost,
                'amount'             => $totalCost,
                'price'              => $portCount * $costPerPortReal,
                'type'               => 'withdraw',
                'external_reference' => uniqid(),
                'payment_id'         => null,
                'description'        => "Renovação de {$portCount} porta(s) proxy (R$ {$costPerPortReal} cada)",
                'status'             => 'completed',
                'user_id'            => $user->id,
            ];

            $this->userCreditService->create($creditData);

            $user->refresh();

            DB::commit();

            return response()->json([
                'message' => "Renovação de {$portCount} porta(s) realizada com sucesso.",
                'renewedCount' => $portCount,
                'renewedPorts' => $renewablePorts->map(function ($port) {
                    return [
                        'id' => $port->id,
                        'expires_at' => $port->expires_at->toIso8601String(),
                        'last_renovation' => $port->last_renovation->toIso8601String(),
                    ];
                }),
                'newCreditsBalance' => $user->credits_balance,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());

            return response()->json(['message' => 'Erro ao renovar as portas.'], 500);
        }
    }
}
