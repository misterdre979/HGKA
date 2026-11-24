<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Provider webhooks. The signature is checked first, but even a valid webhook only *triggers* a server-side
 * verification with the provider; the payload itself is never trusted to mark a payment successful.
 */
class WebhookController extends Controller
{
    public function __construct(private PaymentService $payments)
    {
    }

    public function paystack(Request $request): JsonResponse
    {
        $secret = (string) config('hgka.paystack.secret');
        $expected = $secret !== '' ? hash_hmac('sha512', $request->getContent(), $secret) : '';
        $valid = $expected !== '' && hash_equals($expected, (string) $request->header('x-paystack-signature'));

        $event = (string) $request->input('event');
        $reference = $request->input('data.reference');
        $this->log('paystack', $event, $reference, $request, $valid);
        abort_unless($valid, 401, 'Invalid signature.');

        if ($reference && str_starts_with($event, 'charge.')) {
            $this->process('paystack', $reference);
        }

        return response()->json(['received' => true]);
    }

    public function flutterwave(Request $request): JsonResponse
    {
        $hash = (string) config('hgka.flutterwave.webhook_hash');
        $valid = $hash !== '' && hash_equals($hash, (string) $request->header('verif-hash'));

        $event = (string) ($request->input('event') ?? $request->input('event.type'));
        $reference = $request->input('data.tx_ref') ?? $request->input('txRef');
        $this->log('flutterwave', $event, $reference, $request, $valid);
        abort_unless($valid, 401, 'Invalid signature.');

        if ($reference) {
            $this->process('flutterwave', $reference);
        }

        return response()->json(['received' => true]);
    }

    private function process(string $provider, string $reference): void
    {
        $payment = Payment::where('reference', $reference)->where('method', $provider)->first();
        if ($payment && $payment->status === 'pending') {
            $this->payments->verifyOnline($payment);
        }
        PaymentEvent::where('provider', $provider)->where('reference', $reference)->latest('id')->first()?->update(['processed' => true]);
    }

    private function log(string $provider, string $event, ?string $reference, Request $request, bool $valid): void
    {
        PaymentEvent::create([
            'provider' => $provider, 'event' => mb_substr($event, 0, 80), 'reference' => $reference,
            'payload' => $valid ? $request->all() : ['note' => 'payload discarded: invalid signature'], 'signature_valid' => $valid,
        ]);
    }
}
