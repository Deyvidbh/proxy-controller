<?php

namespace App\Services\Payments\Asaas;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\JsonResponse;

class AsaasApi
{
  protected string $accessToken;
  protected Client $client;

  public function __construct()
  {
    $this->accessToken = (string) config('services.asaas.token');

    $this->client = new Client([
      'base_uri' => rtrim(config('services.asaas.base_url', 'https://api-sandbox.asaas.com'), '/') . '/v3/',
      'headers'  => [
        'Accept'       => 'application/json',
        'Content-Type' => 'application/json',
        'access_token' => $this->accessToken,
      ],
      'connect_timeout' => 5,
      'timeout'         => 12,
    ]);
  }

  /**
   * Cria um Checkout no Asaas.
   *
   * Parâmetros aceitos em $params:
   *
   * - billingTypes (array<string>) [opcional | default: ['PIX']]
   *     Tipos de cobrança aceitos no checkout. Ex.: ['PIX'], ['BOLETO'], ['CREDIT_CARD'].
   *
   * - chargeTypes (array<string>) [opcional | default: ['DETACHED']]
   *     Modalidade da cobrança. Exemplos comuns: ['DETACHED'].
   *
   * - callback (array) [opcional]
   *     - successUrl (string) [opcional | default: url('/payments/success')]
   *     - cancelUrl  (string) [opcional | default: url('/payments/error')]
   *     - expiredUrl (string) [opcional | default: url('/payments/expired')]
   *
   * - items (array<array>) [recomendado | sem default real]
   *     Cada item possui:
   *       - externalReference (string) [opcional]
   *       - description       (string) [opcional]
   *       - name              (string) [opcional]
   *       - quantity          (int)    [opcional | default: 1]
   *       - value             (number) [recomendado: valor unitário]
   *
   * - minutesToExpire (int) [opcional | default: 60]
   *     Tempo para expiração do checkout.
   *
   * - externalReference (string) [opcional]
   *     Referência externa do seu sistema para o checkout.
   *
   * Observações:
   * - Use 'items' para detalhar o que está sendo vendido; o Asaas valida formatos/valores.
   * - Caso precise, você pode incluir campos adicionais suportados pelo endpoint de checkouts do Asaas,
   *   bastando acrescentá-los no array $payload abaixo.
   *
   * @param  array  $params  Dados do checkout (ver lista acima).
   * @return \Illuminate\Http\JsonResponse JSON retornado pela API do Asaas (ou erro estruturado).
   */
  public function createCheckout(array $params): array
  {
    $payload = [
      'billingTypes' => $params['billingTypes'] ?? ['PIX'],
      'chargeTypes'  => $params['chargeTypes']  ?? ['DETACHED'],
      'callback'     => [
        'successUrl' => $params['callback']['successUrl'] ?? url('/payments/success'),
        'cancelUrl'  => $params['callback']['cancelUrl']  ?? url('/payments/error'),
        'expiredUrl' => $params['callback']['expiredUrl'] ?? url('/payments/expired'),
      ],
      'items'            => $params['items'] ?? [],
      'minutesToExpire'  => $params['minutesToExpire'] ?? 60,
      'externalReference' => $params['externalReference'] ?? null,
    ];
    $payload = array_filter($payload, fn($v) => $v !== null);

    try {
      $res = $this->client->post('checkouts', [
        'json'    => $payload,
        'headers' => ['access_token' => $this->accessToken],
      ]);
      return json_decode((string) $res->getBody(), true);
    } catch (RequestException $e) {
      $body = $e->getResponse()?->getBody()?->getContents();
      $msg  = $body ?: $e->getMessage();
      throw new \RuntimeException("Asaas error: {$msg}", $e->getCode() ?: 0, $e);
    }
  }
}
