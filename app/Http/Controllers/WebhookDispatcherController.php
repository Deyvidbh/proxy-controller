<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Handlers\PaymentHandler;
use Illuminate\Http\JsonResponse;

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
}
