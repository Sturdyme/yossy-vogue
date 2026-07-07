<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_can_be_created_without_order_id(): void
    {
        $user = User::factory()->create();

        $payment = Payment::create([
            'user_id' => $user->id,
            'order_id' => null,
            'reference' => 'TEST_REF_123',
            'amount' => 1000,
            'status' => 'pending',
        ]);

        $this->assertNotNull($payment->id);
        $this->assertNull($payment->order_id);
    }
}
