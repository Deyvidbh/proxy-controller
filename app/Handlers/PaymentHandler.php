<?php

namespace App\Handlers;

use App\Services\Payments\MercadoPago\MercadoPagoPro;
use App\Services\UserCredits\UserCreditService;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

use App\Jobs\PaymentConfirmationMailJob;
use App\Models\PaymentReference;



class PaymentHandler
{
    /**
     * Manipula a notificação de pagamento recebida.
     *
     * @param array $notification Dados da notificação recebida.
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle($notification)
    {
        if (!isset($notification['data']['id'])) {
            Log::error("Notificação inválida: campo 'data.id' ausente.");
            return response()->json(['error' => 'Dados da notificação inválidos'], 400);
        }

        $paymentId = (string) $notification['data']['id'];
        $mercadoPagoPro = new MercadoPagoPro();
        $userCreditService = new UserCreditService();

        try {
            $paymentDetails = $mercadoPagoPro->getPayment($paymentId);

            if (!$paymentDetails['success'] || empty($paymentDetails['external_reference'])) {
                Log::error("Pagamento não encontrado ou sem referência externa: {$paymentId}");
                return response()->json(['error' => 'Pagamento não encontrado ou inválido'], 404);
            }

            $this->updatePaymentStatus($paymentDetails, $userCreditService);

            return response()->json(['success' => true]);
        } catch (\Throwable $th) {
            Log::error("Erro ao processar notificação de pagamento: {$paymentId} - {$th->getMessage()} | Arquivo: {$th->getFile()} | Linha: {$th->getLine()}");
            return response()->json(['error' => 'Erro ao processar notificação de pagamento'], 500);
        }
    }

    /**
     * Atualiza o status do pagamento na referência de pagamento e do crédito do usuário.
     *
     * @param array $paymentDetails Detalhes do pagamento obtidos do Mercado Pago.
     * @param \App\Services\UserCredits\UserCreditService $userCreditService Serviço para manipulação de créditos de usuário.
     */
    private function updatePaymentStatus($paymentDetails, $userCreditService)
    {
        return DB::transaction(function () use ($paymentDetails, $userCreditService) {
            $externalReference = $paymentDetails['external_reference'];

            $paymentReference = PaymentReference::where('external_reference', $externalReference)->first();

            if (!$paymentReference) {
                Log::warning("Pagamento referenciado não encontrado: {$externalReference}");
                return response()->json(['error' => 'Pagamento não encontrado no banco'], 404);
            }

            $paymentReference->update(['status' => $paymentDetails['status']]);

            $status = $this->mapPaymentStatusToCreditStatus($paymentDetails['status']);
            $credit = $userCreditService->update($externalReference, ['status' => $status]);

            if (!$credit) {
                Log::warning("Crédito não encontrado para referência externa: {$externalReference}");
                return response()->json(['error' => 'Crédito não encontrado'], 500);
            }

            $user = $credit->user;

            if (!$user) {
                Log::error("Erro ao atualizar saldo: usuário não encontrado para o crédito ID {$credit->id}");
                return response()->json(['error' => 'Usuário não encontrado'], 500);
            }

            dispatch(new PaymentConfirmationMailJob($user, $credit, $paymentReference));

            Log::info("Pagamento atualizado com sucesso: {$externalReference} - Status: {$paymentDetails['status']} - Novo Status: $status");

            return response()->json(['success' => true]);
        });
    }

    /**
     * Mapeia o status do pagamento do Mercado Pago para o status do crédito do usuário.
     *
     * @param string $paymentStatus Status do pagamento do Mercado Pago.
     * @return string Status mapeado para o crédito do usuário.
     */
    private function mapPaymentStatusToCreditStatus($paymentStatus)
    {
        $statusMap = [
            'approved'     => 'completed',
            'authorized'   => 'completed',
            'pending'      => 'pending',
            'in_process'   => 'processing',
            'in_mediation' => 'disputed',
            'cancelled'    => 'canceled',
            'rejected'     => 'failed',
            'charged_back' => 'chargeback',
        ];

        return $statusMap[$paymentStatus] ?? 'canceled';
    }
}
