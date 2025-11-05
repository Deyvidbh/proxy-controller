<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PortsBillingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * $type: 'expiring_3days' | 'invoice_today' | 'overdue_d1' | 'winback_d3' | 'winback_d5'
     * $data: ['expiryDate'=>..., 'paymentUrl'=>..., 'invoiceNumber'=>..., 'totalCents'=>..., 'details'=>..., 'invoiceUrl'=>..., 'renewUrl'=>...]
     */
    public function __construct(public string $type, public array $data = []) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return [
            'mail' => 'proxyMailQueue',
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $viewMap = [
            'expiring_3days' => ['view' => 'emails.ports.expiring_3days', 'subject' => 'Seu plano vence em 3 dias'],
            'invoice_today'  => ['view' => 'emails.ports.invoice_today',  'subject' => 'Sua fatura está disponível'],
            'overdue_d1'     => ['view' => 'emails.ports.overdue_d1',     'subject' => 'Fatura em atraso'],
            'winback_d3'     => ['view' => 'emails.ports.winback_d3',     'subject' => 'Podemos ajudar?'],
            'winback_d5'     => ['view' => 'emails.ports.winback_d5',     'subject' => 'Encerrando nossos lembretes'],
        ];

        $conf = $viewMap[$this->type] ?? $viewMap['expiring_3days'];
        $data = array_merge($this->data, ['user' => $notifiable]);

        return (new MailMessage)
            ->subject($conf['subject'])
            ->view($conf['view'], $data);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
