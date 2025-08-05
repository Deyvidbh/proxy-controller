<?php

namespace App\Http\Controllers\UserCredits;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

use App\Services\UserCredits\UserCreditService;
use App\Services\Payments\MercadoPago\MercadoPagoPro;
use App\Models\PaymentReference;
use App\Models\UserCredit;
use App\Jobs\CreditPurchaseRequestMailJob;

class CreditsController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            'throttle:10,1'
        ];
    }

    /**
     * Exibe a página de créditos, já passando os dados necessários como props.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $userCreditService = new UserCreditService();
        $creditData = $userCreditService->getSummary($user->id);

        // Renderiza o componente Vue e passa os dados diretamente
        return Inertia::render('Dashboard/Credits/Index', [
            'success'         => true,
            'balance'         => $user->credits_balance,
            'credits_summary' => $creditData['summary'],
            'transactions'    => $creditData['credits'],
        ]);
    }

    /**
     * Processa a solicitação de compra de créditos.
     */
    public function create(Request $request)
    {
        $validated = $request->validate([
            'quantity' => 'required|numeric|between:66,3000',
        ]);

        $quantity = intval($validated['quantity']);
        $user = $request->user();
        $mercadoPagoPro = new MercadoPagoPro();
        $userCreditService = new UserCreditService();
        $randomNumber = md5(uniqid(rand(), true));
        $refValue = "REF-{$user->id}-$randomNumber";
        $unit_price = 5.0;

        $paymentData = [
            "items" => [
                [
                    "title" => $quantity . " créditos",
                    "quantity" => $quantity,
                    "unit_price" => $unit_price,
                ]
            ],
            "payer" => [
                "email" => $user->email,
            ],
            "payment_methods" => [
                "excluded_payment_types" => [
                    ["id" => "ticket"],
                    ["id" => "atm"],
                    ["id" => "credit_card"],
                    ["id" => "debit_card"],
                    ["id" => "prepaid_card"]
                ],
                "installments" => 1,
            ],
            "external_reference" => $refValue,
        ];

        DB::beginTransaction();

        try {
            $preference = $mercadoPagoPro->createPreference($paymentData);

            if (!$preference['success']) {
                throw new \Exception("Erro ao criar preferência no Mercado Pago.");
            }

            $userCredit = $userCreditService->create([
                'balance'            => $user->credits_balance,
                'amount'             => $quantity,
                'price'              => $quantity * $unit_price,
                'type'               => 'credit',
                'external_reference' => $refValue,
                'description'        => $quantity . " créditos",
                'payment_id'         => $preference['id'],
                'status'             => 'pending',
                'user_id'            => $user->id,
            ]);

            $paymentReference = PaymentReference::create([
                'identifier'         => $preference['id'],
                'external_reference' => $refValue,
                'price'              => $quantity * $unit_price,
                'collector_id'       => $preference['collector_id'],
                'client_id'          => $preference['client_id'],
                'init_point'         => $preference['init_point'],
                'type'               => 'credit',
                'gateway'            => 'mercado_pago',
                'status'             => 'pending',
            ]);

            DB::commit();

            dispatch(new CreditPurchaseRequestMailJob($user, $userCredit, $paymentReference));

            return redirect()->route('dashboard.credits.index')
                ->with('success', 'Pedido de crédito criado! Verifique seu e-mail para o link de pagamento.');
        } catch (\Throwable $th) {
            DB::rollBack();

            return redirect()->back()
                ->with('error', 'Falha na criação da transação: ' . $th->getMessage());
        }
    }
}
