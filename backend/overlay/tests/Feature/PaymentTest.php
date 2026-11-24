<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    private Invoice $invoice;
    private Student $student;
    private User $parent;

    protected function setUp(): void
    {
        parent::setUp();
        config(['hgka.paystack.secret' => 'sk_test_secret', 'mail.default' => 'array']);

        $session = AcademicSession::create(['name' => '2026/2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-07-01', 'is_current' => true]);
        $term = Term::create(['academic_session_id' => $session->id, 'name' => 'First Term', 'sequence' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-18', 'is_current' => true]);
        $class = SchoolClass::create(['name' => 'JSS 1', 'level' => 'secondary', 'sequence' => 7]);
        $this->student = Student::create(['admission_no' => 'S1', 'first_name' => 'Ada', 'last_name' => 'Obi', 'gender' => 'female', 'school_class_id' => $class->id, 'status' => 'active']);
        $this->invoice = Invoice::create(['number' => 'INV-1', 'student_id' => $this->student->id, 'term_id' => $term->id, 'total' => 100000, 'discount' => 0, 'amount_paid' => 0, 'balance' => 100000, 'status' => 'unpaid']);
        $this->invoice->items()->create(['description' => 'Tuition', 'category' => 'tuition', 'amount' => 100000]);

        $this->parent = $this->userWithRole('parent');
        $g = \App\Models\Guardian::create(['first_name' => 'P', 'last_name' => 'Obi', 'relationship' => 'Father', 'phone' => '0800', 'user_id' => $this->parent->id]);
        $this->student->guardians()->attach($g->id, ['is_primary' => true]);
    }

    private function start(float $amount = 100000): Payment
    {
        Http::fake(['api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.test/abc']])]);
        $r = $this->postJson('/api/v1/payments/online/initialize', ['invoice_id' => $this->invoice->id, 'amount' => $amount, 'gateway' => 'paystack'], $this->tokenFor($this->parent))->assertOk();

        return Payment::where('reference', $r->json('reference'))->firstOrFail();
    }

    private function fakeVerify(string $status, float $naira, string $currency = 'NGN'): void
    {
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => true, 'data' => ['status' => $status, 'amount' => (int) round($naira * 100), 'currency' => $currency, 'id' => 99]])]);
    }

    public function test_starting_online_payment_creates_a_pending_payment_and_never_credits_the_invoice(): void
    {
        $p = $this->start();

        $this->assertSame('pending', $p->status);
        $this->assertEquals(100000, $this->invoice->fresh()->balance);
    }

    public function test_callback_url_alone_does_not_mark_a_payment_paid(): void
    {
        $p = $this->start();
        $this->fakeVerify('abandoned', 0);

        $this->getJson('/api/v1/payments/verify/'.$p->reference, $this->tokenFor($this->parent))->assertOk();

        $this->assertNotSame('successful', $p->fresh()->status);
        $this->assertEquals(100000, $this->invoice->fresh()->balance);
    }

    public function test_verified_payment_credits_invoice_once_even_if_verified_twice(): void
    {
        $p = $this->start(40000);
        $this->fakeVerify('success', 40000);
        $h = $this->tokenFor($this->parent);

        $this->getJson('/api/v1/payments/verify/'.$p->reference, $h)->assertOk()->assertJsonPath('status', 'successful');
        $this->getJson('/api/v1/payments/verify/'.$p->reference, $h)->assertOk();

        $inv = $this->invoice->fresh();
        $this->assertEquals(40000, $inv->amount_paid);
        $this->assertEquals(60000, $inv->balance);
        $this->assertSame('partial', $inv->status);
        $this->assertNotNull($p->fresh()->receipt_no);
    }

    public function test_amount_mismatch_from_provider_is_rejected(): void
    {
        $p = $this->start(100000);
        $this->fakeVerify('success', 500); // provider says only ₦500 was taken

        $this->getJson('/api/v1/payments/verify/'.$p->reference, $this->tokenFor($this->parent))->assertOk();

        $this->assertSame('failed', $p->fresh()->status);
        $this->assertEquals(100000, $this->invoice->fresh()->balance);
    }

    public function test_currency_mismatch_is_rejected(): void
    {
        $p = $this->start(100000);
        $this->fakeVerify('success', 100000, 'USD');

        $this->getJson('/api/v1/payments/verify/'.$p->reference, $this->tokenFor($this->parent));

        $this->assertSame('failed', $p->fresh()->status);
    }

    public function test_cannot_pay_more_than_the_balance(): void
    {
        Http::fake();
        $this->postJson('/api/v1/payments/online/initialize', ['invoice_id' => $this->invoice->id, 'amount' => 100001, 'gateway' => 'paystack'], $this->tokenFor($this->parent))->assertStatus(422);
    }

    public function test_parent_cannot_pay_or_verify_someone_elses_invoice(): void
    {
        $stranger = $this->userWithRole('parent');
        Http::fake();

        $this->postJson('/api/v1/payments/online/initialize', ['invoice_id' => $this->invoice->id, 'amount' => 1000, 'gateway' => 'paystack'], $this->tokenFor($stranger))->assertForbidden();

        $p = $this->start();
        $this->getJson('/api/v1/payments/verify/'.$p->reference, $this->tokenFor($stranger))->assertForbidden();
    }

    public function test_webhook_with_bad_signature_is_rejected(): void
    {
        $p = $this->start();
        $this->fakeVerify('success', 100000);

        $this->postJson('/api/v1/webhooks/paystack', ['event' => 'charge.success', 'data' => ['reference' => $p->reference]], ['x-paystack-signature' => 'forged'])->assertUnauthorized();

        $this->assertSame('pending', $p->fresh()->status);
    }

    public function test_webhook_with_valid_signature_triggers_server_side_verification(): void
    {
        $p = $this->start();
        $this->fakeVerify('success', 100000);
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $p->reference]]);
        $sig = hash_hmac('sha512', $body, 'sk_test_secret');

        $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $sig], $body)->assertOk();

        $this->assertSame('successful', $p->fresh()->status);
        $this->assertSame('paid', $this->invoice->fresh()->status);
    }

    public function test_webhook_claiming_success_is_ignored_if_provider_says_otherwise(): void
    {
        $p = $this->start();
        $this->fakeVerify('failed', 0);
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $p->reference]]);
        $sig = hash_hmac('sha512', $body, 'sk_test_secret');

        $this->call('POST', '/api/v1/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $sig], $body);

        $this->assertSame('failed', $p->fresh()->status);
    }

    public function test_manual_payment_by_clerk_waits_for_approval_then_credits_invoice(): void
    {
        $clerk = $this->userWithRole('accountant'); // can record but not approve
        $svc = app(PaymentService::class);

        $payment = $svc->recordManual($this->invoice, ['method' => 'cash', 'amount' => 30000], $clerk);
        $this->assertSame('awaiting_approval', $payment->status);
        $this->assertEquals(100000, $this->invoice->fresh()->balance);

        $bursar = $this->userWithRole('bursar');
        $svc->approve($payment, $bursar);

        $this->assertEquals(70000, $this->invoice->fresh()->balance);
        $this->assertNotNull($payment->fresh()->receipt_no);
    }

    public function test_approving_two_payments_cannot_overpay_the_invoice(): void
    {
        $svc = app(PaymentService::class);
        $clerk = $this->userWithRole('accountant');
        $a = $svc->recordManual($this->invoice, ['method' => 'cash', 'amount' => 80000], $clerk);
        $b = $svc->recordManual($this->invoice, ['method' => 'cash', 'amount' => 80000], $clerk);
        $bursar = $this->userWithRole('bursar');

        $svc->approve($a, $bursar);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $svc->approve($b, $bursar);
    }
}
