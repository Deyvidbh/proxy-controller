<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Handlers\PaymentHandler;
use App\Handlers\AsaasPaymentsHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;

use Illuminate\Support\Facades\Log;

class WebhookDispatcherController extends Controller
{
    public function mercadoPagoHandleWebhook(Request $request): JsonResponse
    {
        $notification = $request->all();

        if (!isset($notification['action'])) {
            return response()->json(['error' => 'Ação não informada'], 400);
        }

        $action = $notification['action'];

        switch ($action) {
            case 'payment.created':
            case 'payment.updated':
                $handler = new PaymentHandler();
                break;
            default:
                return response()->json(['error' => 'Tipo de evento não reconhecido'], 400);
        }

        try {
            Log::info("Recebida notificação de pagamento do Mercado Pago", $notification);
            return $handler->handle($notification);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao processar o webhook', 'details' => $e->getMessage()], 500);
        }
    }

    public function asaasWebhookHandle(Request $request): JsonResponse
    {
        $token = $request->header('asaas-access-token');
        $expected = env('ASAAS_WEBHOOK_TOKEN');

        if (empty($expected) || empty($token) || !hash_equals($expected, $token)) {
            Log::warning('ASAAS WEBHOOK unauthorized', [
                'ip' => $request->ip(),
                'has_token' => !empty($token),
            ]);

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        Log::info('ASAAS WEBHOOK', [
            'ip'      => $request->ip(),
            'raw'     => $request->getContent(),
        ]);

        $raw  = $request->getContent();
        $json = $request->json()->all();
        if (empty($json)) {
            $json = json_decode($raw, true) ?? [];
        }

        $checkoutSession = (string) data_get($json, 'payment.checkoutSession');
        $event           = (string) data_get($json, 'event');

        if ($checkoutSession === '') {
            $paymentId = (string) data_get($json, 'payment.id');
            $checkoutSession = $paymentId !== '' ? "fallback:$paymentId" : Str::uuid()->toString();
        }

        $idemKey = "asaas:webhook:{$checkoutSession}|{$event}";
        if (!Cache::add($idemKey, 1, now()->addDays(3))) {
            return response()->json(['status' => 'ok', 'duplicate' => true], 200);
        }

        $notification = $json;

        switch ($notification['event']) {
            case 'PAYMENT_RECEIVED':
            case 'PAYMENT_CREATED':
            case 'PAYMENT_OVERDUE':
            case 'PAYMENT_DELETED':
                $handler = new AsaasPaymentsHandler();
                break;
            default:
                return response()->json(['error' => 'Tipo de evento não tratado'], 200);
        }

        try {
            return $handler->handle($notification);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erro ao processar o webhook', 'details' => $e->getMessage()], 500);
        }
    }
}
