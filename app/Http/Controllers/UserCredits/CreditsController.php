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

        // Busque os dados que a página precisa
        $balance = $user->credits_balance ?? 0;

        // Buscando as transações. Ajuste o nome do seu model se for diferente.
        $transactions = UserCredit::where('user_id', $user->id)->latest()->get();

        // Renderiza o componente Vue e passa os dados diretamente
        return Inertia::render('Dashboard/Credits/Index', [
            'balance'      => $balance,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Processa a solicitação de compra de créditos.
     */
    public function create(Request $request)
    {
        // Validação simplificada. Se falhar, o Laravel/Inertia redirecionam com os erros.
        $validated = $request->validate([
            'quantity' => 'required|numeric|between:50,300',
        ]);

        $quantity = intval($validated['quantity']);
        $user = $request->user();
        $mercadoPagoPro = new MercadoPagoPro();
        $userCreditService = new UserCreditService();
        $randomNumber = md5(uniqid(rand(), true));
        $refValue = "REF-{$user->id}-$randomNumber";
        $unit_price = 10.0; // Exemplo

        $paymentData = [
            "items" => [
                [
                    "title" => $quantity . " créditos",
                    "quantity" => 1, // A quantidade de itens é 1, o preço total é calculado
                    "unit_price" => $quantity * $unit_price,
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

            // Resposta de sucesso: redireciona com uma flash message.
            return redirect()->route('dashboard.credits.index')
                ->with('success', 'Pedido de crédito criado! Verifique seu e-mail para o link de pagamento.');
        } catch (\Throwable $th) {
            DB::rollBack();

            // Resposta de erro: redireciona de volta com uma flash message de erro.
            return redirect()->back()
                ->with('error', 'Falha na criação da transação: ' . $th->getMessage());
        }
    }
}
