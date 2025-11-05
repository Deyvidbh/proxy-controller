<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;


use App\Models\User;
use App\Notifications\PortsBillingNotification;

use App\Services\BillingCheckoutService;

class NotifyUsersByExpiryOffsetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public $tries = 3;

    /** @param int $offsetDays -3, 0, +1, +3, +5
     *  @param string $action  'upcoming'|'invoice'|'overdue1'|'winback3'|'winback5'
     */
    public function __construct(public int $offsetDays, public string $action)
    {
        $this->onQueue('proxyMailQueue');
    }

    public function handle(): void
    {
        $tz = 'America/Sao_Paulo';
        $today = Carbon::today($tz);
        $targetDate = $today->copy()->addDays($this->offsetDays);

        // encontra usuários que tenham portas vencendo nessa data (todas vencem juntas)
        User::query()
            ->whereHas('squidPorts', function (Builder $q) use ($targetDate) {
                $q->whereDate('expires_at', $targetDate);
            })
            ->withCount(['squidPorts' => function ($q) use ($targetDate) {
                $q->whereDate('expires_at', $targetDate);
            }])
            ->orderBy('id')
            ->chunkById(500, function (Collection $users) use ($targetDate) {
                foreach ($users as $user) {
                    $key = sprintf('expiry:user:%d:%s:%s', $user->id, $targetDate->toDateString(), $this->action);
                    if (!Cache::add($key, 1, now()->addDays(7))) {
                        continue; // já processado
                    }

                    // recupere dados que você já tem (url de pagamento/fatura, valor etc.)
                    // estas 3 helpers são só exemplos: substitua pelos seus services reais se já existem.
                    $paymentUrl = $this->getPaymentUrl($user, $targetDate);
                    $invoice    = $this->ensureInvoiceIfNeeded($user, $targetDate); // cria no dia 0, NO-OP nos demais
                    $estimate   = $this->estimateTotalCents($user, $targetDate);

                    switch ($this->action) {
                        case 'upcoming':   // -3 dias
                            $user->notify(new PortsBillingNotification('expiring_3days', [
                                'expiryDate' => $targetDate,
                                'details'    => [
                                    'ports_count'            => $user->squid_ports_count ?? $user->squidPorts_count ?? null,
                                    'estimated_total_cents'  => $estimate,
                                ],
                            ]));
                            break;

                        case 'invoice':    // 0 dia
                            $user->notify(new PortsBillingNotification('invoice_today', [
                                'expiryDate'    => $targetDate,
                                'invoiceNumber' => $invoice['number'] ?? null,
                                'totalCents'    => $invoice['total_cents'] ?? $estimate,
                                'paymentUrl'    => $invoice['payment_url'] ?? $paymentUrl,
                                'invoiceUrl'    => $invoice['invoice_url'] ?? null,
                            ]));
                            break;

                        case 'overdue1':   // +1
                            $user->notify(new PortsBillingNotification('overdue_d1', [
                                'expiryDate' => $targetDate,
                            ]));
                            break;

                        case 'winback3':   // +3
                            $user->notify(new PortsBillingNotification('winback_d3', [
                                'expiryDate' => $targetDate,
                            ]));
                            break;

                        case 'winback5':   // +5
                            $user->notify(new PortsBillingNotification('winback_d5', [
                                'expiryDate' => $targetDate,
                            ]));
                            break;
                    }
                }
            });
    }

    // ========== Helpers de exemplo (troque pelos seus serviços reais) ==========
    protected function getPaymentUrl(User $user, \DateTimeInterface $date): ?string
    {
        return null;
    }

    protected function ensureInvoiceIfNeeded(User $user, \DateTimeInterface $date): array
    {
        if ($this->action !== 'invoice') {
            return [];
        }

        /** @var BillingCheckoutService $billing */
        $billing = app(BillingCheckoutService::class);

        return $billing->createRenewalCheckoutForUser(
            $user,
            Carbon::parse($date)->timezone('America/Sao_Paulo')
        );
    }

    protected function estimateTotalCents(User $user, \DateTimeInterface $date): int
    {
        // opcional: estimativa baseada nas suas regras (ex.: 70/66 créditos por porta)
        return 0;
    }
}
