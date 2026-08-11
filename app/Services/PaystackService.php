<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackService
{
    public function initializeTransaction(
        string $email,
        string $amount,
        string $reference,
        array $metadata = []
    ): array {
        $payload = [
            'email' => $email,
            'amount' => $amount,
            'currency' => $this->currency(),
            'reference' => $reference,
        ];

        if ($metadata !== []) {
            $payload['metadata'] = json_encode(
                $metadata,
                JSON_THROW_ON_ERROR
            );
        }

        try {
            $response = $this->client()
                ->post('/transaction/initialize', $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'Could not connect to Paystack.',
                previous: $exception
            );
        }

        if (
            ! $response->successful()
            || $response->json('status') !== true
        ) {
            throw new RuntimeException(
                'Paystack could not initialize the transaction.'
            );
        }

        $data = $response->json('data');

        if (
            ! is_array($data)
            || empty($data['authorization_url'])
            || empty($data['access_code'])
            || empty($data['reference'])
        ) {
            throw new RuntimeException(
                'Paystack returned an invalid initialization response.'
            );
        }

        return $data;
    }

    public function verifyTransaction(
        string $reference
    ): array {
        try {
            $response = $this->client()
                ->get(
                    '/transaction/verify/'.rawurlencode($reference)
                );
        } catch (ConnectionException $exception) {
            throw new RuntimeException(
                'Could not connect to Paystack.',
                previous: $exception
            );
        }

        if (
            ! $response->successful()
            || $response->json('status') !== true
        ) {
            throw new RuntimeException(
                'Paystack could not verify the transaction.'
            );
        }

        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException(
                'Paystack returned an invalid verification response.'
            );
        }

        return $data;
    }

    public function currency(): string
    {
        return strtoupper(
            (string) config(
                'services.paystack.currency',
                'NGN'
            )
        );
    }

    private function client(): PendingRequest
    {
        $secretKey = (string) config(
            'services.paystack.secret_key'
        );

        if ($secretKey === '') {
            throw new RuntimeException(
                'Paystack secret key is not configured.'
            );
        }

        return Http::baseUrl(
            rtrim(
                (string) config(
                    'services.paystack.base_url',
                    'https://api.paystack.co'
                ),
                '/'
            )
        )
            ->withToken($secretKey)
            ->acceptJson()
            ->asJson()
            ->timeout(20);
    }
}