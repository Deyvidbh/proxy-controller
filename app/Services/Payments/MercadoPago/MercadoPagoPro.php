<?php

namespace App\Services\Payments\MercadoPago;

use MercadoPago\MercadoPagoConfig;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\Client\Payment\PaymentClient;

class MercadoPagoPro
{
    public function __construct()
    {
        MercadoPagoConfig::setAccessToken(env('MERCADOPAGO_ACCESS_TOKEN'));
        MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
    }

    public function createPreference($paymentData): array
    {
        $client = new PreferenceClient();

        try {
            $preference = $client->create($paymentData);

            return [
                'success' => true,
                'id'                 => $preference->id,
                'init_point'         => $preference->init_point,
                'client_id'          => $preference->client_id,
                'external_reference' => $preference->external_reference,
                'date_created'       => $preference->date_created,
                'collector_id'       => $preference->collector_id,
            ];

        } catch (MPApiException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getPayment($paymentId): array
    {
        $client = new PaymentClient();

        try {
            $payment = $client->get($paymentId);

            return [
                'success' => true,
                'id'                 => $payment->id,
                'status'             => $payment->status,
                'status_detail'      => $payment->status_detail,
                'external_reference' => $payment->external_reference,
                'date_created'       => $payment->date_created,
                'date_approved'      => $payment->date_approved,
                'date_last_updated'  => $payment->date_last_updated,
                'collector_id'       => $payment->collector_id,
                'payment_method_id'  => $payment->payment_method_id,
                'transaction_amount' => $payment->transaction_amount,
                'installments'       => $payment->installments,
                'payer'              => $payment->payer,
            ];

        } catch (MPApiException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
