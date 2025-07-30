<?php

namespace App\Services\UserCredits;

use App\Models\UserCredit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Validation\ValidationException;

use Exception;

class UserCreditService
{
    /**
     * Cria um novo crédito validando os dados fornecidos com base em regras específicas.
     * 
     * Este método valida os dados de entrada usando um conjunto de regras definidas para garantir que todos os campos necessários
     * estejam presentes e sejam válidos antes de criar um novo registro de crédito na base de dados. A validação inclui:
     * - Verificação da presença e formato numérico do campo 'amount'.
     * - Confirmação de que o 'type' é um dos valores permitidos ('withdraw', 'credit').
     * - Checagem de que 'external_reference' é único e está presente.
     * - Validação de que, se fornecido, 'payment_id' é único.
     * - Exigência de que o campo 'description' esteja presente e seja uma string.
     * - Restrição do campo 'status' para o valor 'pending' como inicialmente requerido.
     * - Verificação de que 'user_id' corresponde a um usuário existente na base de dados.
     * 
     * Se qualquer uma dessas validações falhar, o método retornará 'false'. Caso contrário, um novo crédito será criado com os dados fornecidos,
     * e 'true' será retornado para indicar sucesso na operação.
     *
     * @param  array $data Dados necessários para a criação do crédito, incluindo 'amount', 'type', 'external_reference',
     *                     'payment_id' (opcional, serve para identificar pagamentos no gateway de pagamento.), 'description', 'status' e 'user_id'.
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
            'status'                  => 'required|in:pending,completed',
            'user_id'                 => 'required|exists:users,id',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator);
        }

        $credit = UserCredit::create($validator->validated());

        if ($credit->type === 'withdraw') {
            $this->updateUserBalance($credit);
        }

        return $credit;
    }

    /**
     * Atualiza um crédito específico baseando-se em um identificador único e dados fornecidos.
     * 
     * Este método permite a atualização do 'status' e, opcionalmente, do 'payment_id' de um crédito específico, 
     * identificado pelo 'external_reference' ou 'payment_id'. Os dados de entrada são validados para assegurar:
     * - Que o 'status' esteja entre os valores permitidos: 'completed', 'pending', 'canceled'.
     * - Que, se um 'payment_id' for fornecido para atualização, ele seja único entre todos os créditos, exceto para o crédito que está sendo atualizado.
     *   Isso permite atribuir um 'payment_id' a um crédito que anteriormente não tinha um, e garante que não haverá dois créditos com o mesmo 'payment_id'.
     * 
     * A operação é realizada dentro de uma transação de banco de dados para manter a integridade dos dados. Se o 'status' do crédito for
     * atualizado para 'completed', o saldo do usuário associado será ajustado conforme o tipo de transação (crédito ou retirada).
     * 
     * A função tenta encontrar o crédito usando 'external_reference' ou 'payment_id'. Se o crédito não for encontrado ou se ocorrer falha na
     * validação dos dados, 'false' é retornado. Caso contrário, em sucesso na atualização, 'true' é retornado.
     * 
     * @param  string $identifier Identificador único (payment_id ou external_reference) utilizado para localizar o crédito específico a ser atualizado.
     * @param  array $data Array contendo os dados para atualização, especificamente o 'status' da transação e, opcionalmente, um novo 'payment_id'.
     */
    public function update($identifier, array $data)
    {
        $rules = [
            'status'     => 'required|in:completed,pending,canceled',
            'payment_id' => 'nullable|string|exists:user_credits,payment_id',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return DB::transaction(function () use ($identifier, $data) {
            try {
                $credit = UserCredit::where('external_reference', $identifier)
                    ->orWhere('payment_id', $identifier)
                    ->firstOrFail();

                if ($credit->status === 'completed') {
                    throw new HttpException(422, 'Não é possível atualizar um crédito que já foi completado.');
                }

                $credit->update($data);

                if ($data['status'] == 'completed') {
                    $this->updateUserBalance($credit);
                    $credit->update(['balance' => $credit->user->credits_balance]);
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
     * @param  string|null $userId Identificador do usuário para buscar todos os seus créditos.
     * @param  string|null $identifier Identificador único (payment_id ou unique_id) para buscar um crédito específico.
     * @return \Illuminate\Database\Eloquent\Collection|UserCredit|null
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

    protected function updateUserBalance(UserCredit $credit)
    {
        $user = $credit->user()->first();

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
}
