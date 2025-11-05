<?php

namespace App\Services;

use App\Models\User;
use App\Models\PaymentReference;
use App\Services\Payments\Asaas\AsaasApi;
use App\Services\UserCredits\UserCreditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BillingCheckoutService
{
    public function __construct(
        private AsaasApi $asaas,
        private UserCreditService $userCreditService,
    ) {}

    /**
     * Gera o checkout de renovação (mesma lógica do storeCheckout) e retorna dados
     * p/ o e-mail "invoice_today".
     *
     * @return array{
     *   number: ?string,
     *   total_cents: int,
     *   payment_url: ?string,
     *   invoice_url: ?string
     * }
     */
    public function createRenewalCheckoutForUser(User $user, Carbon $expiryDate): array
    {
        // 1) Portas associadas a este ciclo de vencimento
        $ports = $user->squidPorts()
            ->whereDate('expires_at', $expiryDate) 
            ->get();

        if ($ports->isEmpty()) {
            return ['number' => null, 'total_cents' => 0, 'payment_url' => null, 'invoice_url' => null];
        }

        // 2) Idempotência por ciclo (inclui YYYYMMDD no ref)
        $dateToken   = $expiryDate->format('Ymd');    // <-- token de data
        $randomToken = Str::lower(Str::random(10));
        $refValue    = "REF-{$user->id}-{$dateToken}-{$randomToken}";  // <-- inclui a data
        $uri_link_ref = Str::uuid();

        $portCount        = $ports->count();
        $costPerPort      = $portCount >= 20 ? 66 : 70;
        $costPerPortReal  = $portCount >= 20 ? 330 : 350;
        $totalCost        = $costPerPortReal * $portCount; // R$
        $totalCostCredit  = $costPerPort * $portCount;

        $payload = [
            'billingTypes' => ['PIX'],
            'chargeTypes'  => ['DETACHED'],
            'callback'     => [
                'successUrl' => route('payments.success'),
                'cancelUrl'  => route('payments.error'),
                'expiredUrl' => route('payments.expired'),
            ],
            'items' => [[
                'externalReference' => $refValue,
                // 3) Semântica: descrição com a data de vencimento
                'description'       => 'Renovação do serviço de ' . $portCount . ' portas proxy - venc. ' . $expiryDate->format('d/m/Y'),
                'name'              => 'portas proxy',
                'quantity'          => $portCount,
                'value'             => $costPerPortReal,
            ]],
            'minutesToExpire'   => 1400,
            'externalReference' => $uri_link_ref,
        ];

        DB::beginTransaction();

        try {
            $asaas_response = $this->asaas->createCheckout($payload);

            $this->userCreditService->create([
                'balance'            => $user->credits_balance,
                'amount'             => $totalCostCredit,
                'price'              => $totalCost,
                'type'               => 'credit',
                'external_reference' => $refValue,
                'description'        => $portCount . " Portas proxy (venc. " . $expiryDate->format('d/m/Y') . ")",
                'payment_id'         => $asaas_response['externalReference'],
                'status'             => 'pending',
                'user_id'            => $user->id,
            ]);

            $paymentReference = PaymentReference::create([
                'identifier'         => $asaas_response['id'],
                'external_reference' => $refValue,
                'price'              => $totalCost,
                'init_point'         => $asaas_response['link'],
                'type'               => 'credit',
                'gateway'            => 'asaas',
                'status'             => 'pending',
            ]);

            DB::commit();

            return [
                'number'       => null,
                'total_cents'  => (int) round($totalCost * 100),
                'payment_url'  => $paymentReference->init_point,
                'invoice_url'  => null,
            ];
        } catch (\Throwable $th) {
            DB::rollBack();
            return ['number' => null, 'total_cents' => 0, 'payment_url' => null, 'invoice_url' => null];
        }
    }
}
