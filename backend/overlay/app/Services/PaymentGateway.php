<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for Paystack and Flutterwave. The browser is never trusted for the outcome of a payment:
 * success is only ever derived from verify(), which asks the provider's API directly.
 */
class PaymentGateway
{
    public const GATEWAYS = ['paystack', 'flutterwave'];

    public function configured(): array
    {
        return [
            'paystack' => (bool) config('hgka.paystack.secret'),
            'flutterwave' => (bool) config('hgka.flutterwave.secret'),
        ];
    }

    /** @return string hosted checkout URL the customer should be sent to */
    public function initialize(string $gateway, Payment $payment, Invoice $invoice, string $email, string $name, string $callbackUrl): string
    {
        return match ($gateway) {
            'paystack' => $this->paystackInit($payment, $invoice, $email, $callbackUrl),
            'flutterwave' => $this->flutterwaveInit($payment, $invoice, $email, $name, $callbackUrl),
            default => throw new RuntimeException('Unsupported payment gateway.'),
        };
    }

    /**
     * @return array{status:'success'|'failed'|'pending', amount:float, currency:string, gateway_reference:?string, raw:array}
     */
    public function verify(string $gateway, string $reference): array
    {
        return match ($gateway) {
            'paystack' => $this->paystackVerify($reference),
            'flutterwave' => $this->flutterwaveVerify($reference),
            default => throw new RuntimeException('Unsupported payment gateway.'),
        };
    }

    // ------------------------------------------------------------ Paystack
    private function paystack()
    {
        $secret = config('hgka.paystack.secret');
        if (! $secret) {
            throw new RuntimeException('Paystack is not configured.');
        }

        return Http::withToken($secret)->acceptJson()->timeout(20)->baseUrl(config('hgka.paystack.base_url'));
    }

    private function paystackInit(Payment $payment, Invoice $invoice, string $email, string $callbackUrl): string
    {
        $res = $this->paystack()->post('/transaction/initialize', [
            'email' => $email,
            'amount' => (int) round(((float) $payment->amount) * 100), // kobo
            'currency' => 'NGN',
            'reference' => $payment->reference,
            'callback_url' => $callbackUrl,
            'metadata' => ['payment_id' => $payment->id, 'invoice' => $invoice->number, 'student_id' => $payment->student_id],
        ]);

        if (! $res->successful() || ! $res->json('status') || ! $res->json('data.authorization_url')) {
            throw new RuntimeException($res->json('message') ?: 'Paystack could not start the payment.');
        }

        return $res->json('data.authorization_url');
    }

    private function paystackVerify(string $reference): array
    {
        $res = $this->paystack()->get('/transaction/verify/'.rawurlencode($reference));

        if ($res->status() === 404) {
            return ['status' => 'pending', 'amount' => 0.0, 'currency' => 'NGN', 'gateway_reference' => null, 'raw' => $res->json() ?? []];
        }
        if (! $res->successful() || ! $res->json('status')) {
            throw new RuntimeException($res->json('message') ?: 'Paystack verification failed.');
        }

        $d = $res->json('data');
        $status = match ($d['status'] ?? '') {
            'success' => 'success',
            'failed', 'abandoned', 'reversed' => 'failed',
            default => 'pending',
        };

        return [
            'status' => $status, 'amount' => ((float) ($d['amount'] ?? 0)) / 100, 'currency' => $d['currency'] ?? 'NGN',
            'gateway_reference' => isset($d['id']) ? (string) $d['id'] : null, 'raw' => $this->scrub($d),
        ];
    }

    // ------------------------------------------------------------ Flutterwave
    private function flutterwave()
    {
        $secret = config('hgka.flutterwave.secret');
        if (! $secret) {
            throw new RuntimeException('Flutterwave is not configured.');
        }

        return Http::withToken($secret)->acceptJson()->timeout(20)->baseUrl(config('hgka.flutterwave.base_url'));
    }

    private function flutterwaveInit(Payment $payment, Invoice $invoice, string $email, string $name, string $callbackUrl): string
    {
        $res = $this->flutterwave()->post('/payments', [
            'tx_ref' => $payment->reference,
            'amount' => (float) $payment->amount,
            'currency' => 'NGN',
            'redirect_url' => $callbackUrl,
            'customer' => ['email' => $email, 'name' => $name],
            'customizations' => ['title' => 'HGKA School Fees', 'description' => 'Invoice '.$invoice->number],
            'meta' => ['payment_id' => $payment->id, 'student_id' => $payment->student_id],
        ]);

        if (! $res->successful() || $res->json('status') !== 'success' || ! $res->json('data.link')) {
            throw new RuntimeException($res->json('message') ?: 'Flutterwave could not start the payment.');
        }

        return $res->json('data.link');
    }

    private function flutterwaveVerify(string $reference): array
    {
        $res = $this->flutterwave()->get('/transactions/verify_by_reference', ['tx_ref' => $reference]);

        if (! $res->successful() || $res->json('status') !== 'success') {
            // "No transaction was found" means the customer has not reached the provider yet.
            return ['status' => 'pending', 'amount' => 0.0, 'currency' => 'NGN', 'gateway_reference' => null, 'raw' => $res->json() ?? []];
        }

        $d = $res->json('data');
        $status = match (strtolower($d['status'] ?? '')) {
            'successful' => 'success',
            'failed', 'cancelled' => 'failed',
            default => 'pending',
        };

        return [
            'status' => $status, 'amount' => (float) ($d['amount'] ?? 0), 'currency' => $d['currency'] ?? 'NGN',
            'gateway_reference' => isset($d['id']) ? (string) $d['id'] : null, 'raw' => $this->scrub($d),
        ];
    }

    /** Keeps provider responses for the audit trail without card or authorisation data. */
    private function scrub(array $data): array
    {
        unset($data['authorization'], $data['card'], $data['customer'], $data['log'], $data['plan_object'], $data['subaccount']);

        return $data;
    }
}
