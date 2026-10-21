<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_their_orders_with_ordered_items(): void
    {
        $user = User::factory()->create(['is_verified' => true]);
        $otherUser = User::factory()->create(['is_verified' => true]);
        $order = $this->createOrder($user, 'ORDER-OWN');
        $this->createOrder($otherUser, 'ORDER-OTHER');
        $token = $user->createToken('order-history-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'orders')
            ->assertJsonPath('orders.0.id', $order->id)
            ->assertJsonPath('orders.0.items.0.product_name', 'Vogue Dress')
            ->assertJsonPath('orders.0.items.0.quantity', 2);
    }

    public function test_user_can_show_their_order_by_id_or_reference_but_not_another_users_order(): void
    {
        $user = User::factory()->create(['is_verified' => true]);
        $otherUser = User::factory()->create(['is_verified' => true]);
        $order = $this->createOrder($user, 'ORDER-OWN');
        $otherOrder = $this->createOrder($otherUser, 'ORDER-OTHER');
        $token = $user->createToken('order-show-test')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('reference', 'ORDER-OWN')
            ->assertJsonPath('items.0.product_name', 'Vogue Dress');

        $this->withToken($token)
            ->getJson('/api/orders/ORDER-OWN')
            ->assertOk()
            ->assertJsonPath('id', $order->id);

        $this->withToken($token)
            ->getJson("/api/orders/{$otherOrder->id}")
            ->assertNotFound();
    }

    private function createOrder(User $user, string $reference): Order
    {
        $order = Order::create([
            'user_id' => $user->id,
            'reference' => $reference,
            'subtotal' => 100,
            'shipping' => 15,
            'total_amount' => 115,
            'status' => 'completed',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => '1',
            'product_name' => 'Vogue Dress',
            'quantity' => 2,
            'price' => 50,
        ]);

        return $order;
    }
}
