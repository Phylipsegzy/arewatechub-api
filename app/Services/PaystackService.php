<?php

namespace App\Services;

use App\Models\ApiKey;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Single shared Paystack integration used by wallet funding, workspace bookings,
 * and (later) course/cohort payments. Keeps the secret key in the api_keys table
 * (matches your original setup) rather than scattering it across controllers.
 */
class PaystackService
{
    protected string $secretKey;
    protected string $baseUrl = 'https://api.paystack.co';

    public function __construct(?string $mode = null)
    {
        // Reads through config(), not env() directly — env() calls outside of
        // config files silently return null once you run `php artisan
        // config:cache` (common in production), which is exactly the kind of
        // "works locally, breaks on deploy" bug this avoids.
        $mode = $mode ?? config('services.paystack.mode', 'test');

        $envKey = $mode === 'live'
            ? config('services.paystack.live_secret')
            : config('services.paystack.test_secret');

        if ($envKey) {
            $this->secretKey = trim($envKey);
            return;
        }

        $key = ApiKey::where('provider', 'paystack')->where('mode', $mode)->first();

        if (! $key) {
            throw new RuntimeException("No Paystack secret key found for mode [$mode]. Set PAYSTACK_{$mode}_SECRET_KEY (uppercase) in .env, or seed the api_keys table.");
        }

        $this->secretKey = $key->secret_key;
    }

    public function initializeTransaction(string $email, float $amountNaira, string $reference, array $metadata = []): array
    {
        $response = Http::withToken($this->secretKey)
            ->post("{$this->baseUrl}/transaction/initialize", [
                'email' => $email,
                'amount' => (int) round($amountNaira * 100), // kobo
                'reference' => $reference,
                'metadata' => $metadata,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Paystack initialize failed: ' . $response->body());
        }

        return $response->json('data');
    }

    public function verifyTransaction(string $reference): array
    {
        $response = Http::withToken($this->secretKey)
            ->get("{$this->baseUrl}/transaction/verify/{$reference}");

        if (! $response->successful()) {
            throw new RuntimeException('Paystack verify failed: ' . $response->body());
        }

        return $response->json('data');
    }

    public function verifyWebhookSignature(string $rawPayload, ?string $signatureHeader): bool
    {
        if (! $signatureHeader) {
            return false;
        }

        return hash_equals(
            hash_hmac('sha512', $rawPayload, $this->secretKey),
            $signatureHeader
        );
    }

    /**
     * Registers the customer with Paystack (required before a dedicated
     * account can be assigned). Safe to call repeatedly — Paystack returns
     * the existing customer if one already exists for that email.
     */
    public function createCustomer(string $email, string $firstName, string $lastName, ?string $phone = null): array
    {
        $response = Http::withToken($this->secretKey)->post("{$this->baseUrl}/customer", [
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Paystack create customer failed: ' . $response->body());
        }

        return $response->json('data');
    }

    /**
     * Requests a dedicated (permanent) bank account number for a Paystack
     * customer — this is what customers transfer to directly to fund their
     * wallet, instead of going through the card/checkout flow.
     *
     * Requires your Paystack business to have Dedicated Virtual Accounts
     * enabled (Nigeria only, needs KYB approval from Paystack) and a
     * preferred_bank slug such as 'wema-bank' or 'titan-paystack'.
     */
    public function createDedicatedAccount(string|int $paystackCustomerId, string $preferredBank = 'wema-bank'): array
    {
        $response = Http::withToken($this->secretKey)->post("{$this->baseUrl}/dedicated_account", [
            'customer' => $paystackCustomerId,
            'preferred_bank' => $preferredBank,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Paystack create dedicated account failed: ' . $response->body());
        }

        return $response->json('data');
    }

    /**
     * Fetches a customer's real record from Paystack by email — used to
     * correct dedicated account rows that were imported with the wrong
     * identifier (Paystack's internal numeric customer id instead of the
     * public customer_code, e.g. "CUS_xxxxx", which is what webhooks
     * actually send).
     */
    public function getCustomerByEmail(string $email): array
    {
        $response = Http::withToken($this->secretKey)->get("{$this->baseUrl}/customer/" . urlencode($email));

        if (! $response->successful()) {
            throw new RuntimeException("Paystack customer lookup failed for {$email}: " . $response->body());
        }

        return $response->json('data');
    }
}
