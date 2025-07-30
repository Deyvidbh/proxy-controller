<?php

namespace App\Mail;

use App\Models\User;
use App\Models\UserCredit;
use App\Models\PaymentReference;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CreditPurchaseRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $userCredit;
    public $paymentReference;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user, UserCredit $userCredit, PaymentReference $paymentReference) {
        $this->user = $user;
        $this->userCredit = $userCredit;
        $this->paymentReference = $paymentReference;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.credit_purchase_request')
            ->subject('Pedido de Créditos')
            ->with([
                'user' => $this->user,
                'userCredit' => $this->userCredit,
                'paymentReference' => $this->paymentReference
            ]);
    }
}
