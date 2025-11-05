<?php

namespace App\Services\UserCredits;

use App\Models\UserCredit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class UserCreditService
{
    /**
     * Cria um novo crédito validando os dados fornecidos com base em regras específicas.
     *
     * @param  array $data
     * @return \App\Models\UserCredit
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create(array $data): UserCredit
    {
        $rules = [
            'balance'                 => 'required|numeric',
            'amount'                  => 'required|numeric',
            'price'                   => 'required|numeric',
            'type'                    => 'required|in:withdraw,credit',
            'external_reference'      => 'required|string|unique:user_credits,external_reference',
            'payment_id'              => 'nullable|string|unique:user_credits,payment_id',
            'description'             => 'required|string',
            'status'                  => 'required|in:pending,completed,cancelled,expired',
            'user_id'                 => 'required|exists:users,id',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $credit = UserCredit::create($validator->validated());

        // Se for retirada, atualiza saldo já aqui.
        if ($credit->type === 'withdraw') {
            $this->updateUserBalance($credit);
        }

        return $credit;
    }

    /**
     * Atualiza um crédito específico baseando-se em um identificador único e dados fornecidos.
     *
     * @param  string $identifier  payment_id ou external_reference
     * @param  array  $data
     * @return \App\Models\UserCredit
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update($identifier, array $data)
    {
        $rules = [
            'status'         => 'required|in:completed,pending,canceled,expired',
            'payment_id'     => 'nullable|string',
            'asaas_customer' => 'nullable|string',
        ];

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return DB::transaction(function () use ($identifier, $data) {
            try {
                /** @var UserCredit $credit */
                $credit = UserCredit::where('external_reference', $identifier)
                    ->orWhere('payment_id', $identifier)
                    ->firstOrFail();

                // Idempotência suave: se já completed, não faz nada.
                if ($credit->status === 'completed') {
                    Log::info('UserCredit update no-op (already completed)', [
                        'credit_id'  => $credit->id,
                        'identifier' => $identifier,
                    ]);
                    return $credit;
                }

                // Atualiza campos do crédito
                $credit->update($data);

                // Propaga asaas_customer para o usuário, se necessário
                if ($credit->user->asaas_customer === null && !empty($data['asaas_customer'])) {
                    $credit->user->update(['asaas_customer' => $data['asaas_customer']]);
                }

                // Se virou completed, efeitos colaterais
                if ($data['status'] === 'completed') {
                    // Ajusta saldo do usuário conforme type (credit/withdraw) com lock pessimista
                    $this->updateUserBalance($credit);

                    // Sincroniza o saldo atual no registro do crédito
                    $credit->update(['balance' => $credit->user->credits_balance]);

                    // Ação pós-pagamento (renovar portas) idempotente
                    if (is_null($credit->post_action_done_at)) {
                        $user = $credit->user()->first();

                        $result = $this->renewAllPorts($user); // Retorna DTO (array), sem HTTP

                        if (!($result['ok'] ?? false)) {
                            // Se quiser abortar toda a transação ao falhar a renovação:
                            throw new \RuntimeException($result['message'] ?? 'Falha na renovação das portas');
                        }

                        // Marca como executado para não rodar duas vezes
                        $credit->update(['post_action_done_at' => now()]);

                        Log::info('Post-payment action executed (renewAllPorts)', [
                            'credit_id'      => $credit->id,
                            'user_id'        => $user?->id,
                            'renewedCount'   => $result['renewedCount'] ?? null,
                            'newBalance'     => $result['newCreditsBalance'] ?? null,
                        ]);
                    } else {
                        Log::info('Post-payment action already executed (idempotent)', [
                            'credit_id' => $credit->id,
                        ]);
                    }
                }

                return $credit;
            } catch (ModelNotFoundException $e) {
                throw new HttpException(404, 'Crédito não encontrado.');
            }
        });
    }

    /**
     * Obtém créditos de um usuário específico ou um crédito específico.
     *
     * @param  string|null $userId
     * @param  string|null $identifier payment_id ou unique_id
     * @return \Illuminate\Database\Eloquent\Collection|\App\Models\UserCredit|array
     */
    public function get(?string $userId = null, ?string $identifier = null)
    {
        if ($identifier) {
            $credit = UserCredit::with('paymentReference')
                ->where('unique_id', $identifier)
                ->orWhere('payment_id', $identifier)
                ->first();

            if (!$credit) {
                return [];
            }

            return $credit;
        }

        if ($userId) {
            return UserCredit::with('paymentReference')
                ->where('user_id', $userId)->get();
        }

        return [];
    }

    /**
     * Retorna resumo e lista de créditos de um usuário.
     */
    public function getSummary($userId)
    {
        $credits = UserCredit::with('paymentReference')
            ->where('user_id', $userId)
            ->get();

        $summary = [
            'pending'   => $credits->where('status', 'pending')->count(),
            'canceled'  => $credits->where('status', 'canceled')->count(),
            'completed' => $credits->where('status', 'completed')->count(),
            'spent'     => $credits->where('type', 'withdraw')->sum('amount'),
        ];

        $creditsFormatted = $credits->map(function ($credit) {
            return [
                'id'                 => $credit->id,
                'amount'             => $credit->amount,
                'price'              => $credit->price,
                'balance'            => $credit->balance,
                'type'               => $credit->type,
                'status'             => $credit->status,
                'external_reference' => $credit->external_reference,
                'description'        => $credit->description,
                'updated_at'         => $credit->paymentReference ? $credit->paymentReference->updated_at->format('Y-m-d H:i:s') : $credit->updated_at->format('Y-m-d H:i:s'),
                'init_point'         => $credit->status == 'pending' && $credit->paymentReference ? $credit->paymentReference->init_point : null,
            ];
        });

        return [
            'summary' => $summary,
            'credits' => $creditsFormatted,
        ];
    }

    /**
     * Atualiza o saldo do usuário com lock pessimista.
     *
     * @throws \Exception
     */
    protected function updateUserBalance(UserCredit $credit)
    {
        /** @var User $user */
        $user = $credit->user()->lockForUpdate()->first();

        if (!$user) {
            throw new Exception("Usuário não encontrado para o crédito ID {$credit->id}");
        }

        switch ($credit->type) {
            case 'credit':
                $user->credits_balance += $credit->amount;
                break;
            case 'withdraw':
                $user->credits_balance -= $credit->amount;
                break;
            default:
                throw new Exception("Tipo de transação desconhecido para o crédito ID {$credit->id}");
        }

        $user->save();
    }

    /**
     * Renova todas as portas do usuário e debita os créditos.
     * NÃO abre nova transação nem retorna HTTP; devolve um DTO (array).
     *
     * @return array{ok:bool, code:int, message?:string, renewedCount?:int, renewedPorts?:array, newCreditsBalance?:float}
     */
    private function renewAllPorts(User $user): array
    {
        $ports = $user->squidPorts()->get();

        if ($ports->isEmpty()) {
            return ['ok' => false, 'code' => 400, 'message' => 'Você não possui portas para renovar.'];
        }

        $portCount       = $ports->count();
        $costPerPort     = $portCount >= 20 ? 66 : 70;
        $costPerPortReal = $portCount >= 20 ? 330 : 350;
        $totalCost       = $costPerPort * $portCount;

        // Garante leitura fresca do saldo (e se quiser, pode travar aqui também)
        $user->refresh();

        if ($user->credits_balance < $totalCost) {
            return [
                'ok'     => false,
                'code'   => 400,
                'message' => "Você precisa de {$totalCost} créditos para renovar {$portCount} porta(s). Saldo atual: {$user->credits_balance} créditos."
            ];
        }

        // Atualiza vencimentos
        foreach ($ports as $port) {
            $base = $port->expires_at ?: now();
            $port->expires_at      = $base->copy()->addDays(30);
            $port->last_renovation = now();
            $port->save();
        }

        // Lança um "withdraw" para registrar o débito da renovação
        $creditData = [
            'balance'            => $user->credits_balance - $totalCost,
            'amount'             => $totalCost,
            'price'              => $portCount * $costPerPortReal,
            'type'               => 'withdraw',
            'external_reference' => (string) Str::ulid(),
            'payment_id'         => null,
            'description'        => "Renovação de {$portCount} porta(s) proxy (R$ {$costPerPortReal} cada)",
            'status'             => 'completed',
            'user_id'            => $user->id,
        ];

        $this->create($creditData);

        $user->refresh();

        return [
            'ok'                => true,
            'code'              => 200,
            'renewedCount'      => $portCount,
            'renewedPorts'      => $ports->map(fn($p) => [
                'id'              => $p->id,
                'expires_at'      => optional($p->expires_at)->toIso8601String(),
                'last_renovation' => optional($p->last_renovation)->toIso8601String(),
            ])->values()->all(),
            'newCreditsBalance' => $user->credits_balance,
        ];
    }
}
