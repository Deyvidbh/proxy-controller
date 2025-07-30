<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\User;
use App\Models\UserCredit;
use App\Models\PaymentReference;

use App\Mail\PaymentConfirmationMail;

use Illuminate\Support\Facades\Mail;

class PaymentConfirmationMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $userCredit;
    public $paymentReference;

    public $tries = 5;
    public $backoff = [5, 5, 5, 20, 60];

    public function __construct(User $user, UserCredit $userCredit, PaymentReference $paymentReference)
    {
        $this->onQueue('proxyMailQueue');
        $this->user = $user;
        $this->userCredit = $userCredit;
        $this->paymentReference = $paymentReference;
    }

    public function handle()
    {
        $email = new PaymentConfirmationMail($this->user, $this->userCredit, $this->paymentReference);
        Mail::to($this->user->email)->send($email);
    }
}
