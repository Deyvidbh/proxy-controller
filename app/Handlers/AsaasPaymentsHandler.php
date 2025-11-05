<?php

namespace App\Handlers;

use App\Services\UserCredits\UserCreditService;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

use App\Jobs\PaymentConfirmationMailJob;
use App\Models\PaymentReference;

class AsaasPaymentsHandler
{
  /**
   * Manipula a notificação de pagamento recebida.
   *
   * @param array $notification Dados da notificação recebida.
   * @return \Illuminate\Http\JsonResponse
   */
  public function handle($notification)
  {
    if (!isset($notification['event'])) {
      Log::error("Notificação inválida: campo 'event' ausente.");
      return response()->json(['error' => 'Dados da notificação inválidos'], 400);
    }

    $paymentId = (string) $notification['payment']['id'];
    $userCreditService = new UserCreditService();

    try {
      $this->updatePaymentStatus($notification, $userCreditService);

      return response()->json(['status' => 'ok'], 200);
    } catch (\Throwable $th) {
      Log::error("Erro ao processar notificação de pagamento: {$paymentId} - {$th->getMessage()} | Arquivo: {$th->getFile()} | Linha: {$th->getLine()}");
      return response()->json(['error' => 'Erro ao processar notificação de pagamento'], 500);
    }
  }

  /**
   * Atualiza o status do pagamento na referência de pagamento e do crédito do usuário.
   *
   * @param array $paymentDetails Detalhes do pagamento obtidos do Asaas.
   * @param \App\Services\UserCredits\UserCreditService $userCreditService Serviço para manipulação de créditos de usuário.
   */
  private function updatePaymentStatus($paymentDetails, $userCreditService)
  {
    return DB::transaction(function () use ($paymentDetails, $userCreditService) {

      $checkout_session = $paymentDetails['payment']['checkoutSession'];

      $paymentReference = PaymentReference::where('identifier', $checkout_session)->first();
      
      if (!$paymentReference) {
        Log::warning("Pagamento referenciado não encontrado: {$checkout_session}");
        return response()->json(['error' => 'Pagamento não encontrado no banco'], 404);
      }

      $externalReference = $paymentReference->external_reference;

      $payment_reference_status = $this->mapPaymentStatusToReferenceStatus($paymentDetails['event']);

      $paymentReference->update(
        [
          'status'         => $payment_reference_status,
          'payment_id'     => $paymentDetails['payment']['id'],
          'asaas_customer' => $paymentDetails['payment']['customer'],
        ]
      );

      $credit_status = $this->mapPaymentStatusToCreditStatus($paymentDetails['event']);

      $credit = $userCreditService->update($externalReference, [
        'status'         => $credit_status,
        'payment_id'     => $paymentDetails['payment']['id'],
        'asaas_customer' => $paymentDetails['payment']['customer'],
      ]);

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

      Log::info("Pagamento atualizado com sucesso: {$externalReference} - Status: {$paymentDetails['event']} - Novo Status: $credit_status");

      return response()->json(['success' => true]);
    });
  }

  /**
   * Mapeia o status do pagamento do Asaas para o status do crédito do usuário.
   *
   * @param string $paymentStatus Status do pagamento do Asaas.
   * @return string Status mapeado para o crédito do usuário.
   */
  private function mapPaymentStatusToCreditStatus($paymentStatus)
  {
    $statusMap = [
      'PAYMENT_RECEIVED' => 'completed',
      'PAYMENT_CREATED'  => 'pending',
      'PAYMENT_OVERDUE'  => 'expired',
      'PAYMENT_DELETED'  => 'canceled',
    ];

    return $statusMap[$paymentStatus] ?? 'canceled';
  }

  /**
   * Mapeia o status do pagamento do Asaas para o status da referencia di crédito do usuário.
   *
   * @param string $paymentStatus Status do pagamento do Asaas.
   * @return string Status mapeado para o referencia do crédito do usuário.
   */
  private function mapPaymentStatusToReferenceStatus($paymentStatus)
  {
    $statusMap = [
      'PAYMENT_RECEIVED' => 'approved',
      'PAYMENT_CREATED'  => 'pending',
      'PAYMENT_OVERDUE'  => 'expired',
      'PAYMENT_DELETED'  => 'cancelled',
    ];

    return $statusMap[$paymentStatus] ?? 'cancelled';
  }
}
