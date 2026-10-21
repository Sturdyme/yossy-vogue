<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPinTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_management_requires_pin_verification_for_the_current_token(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_verified' => true,
        ]);
        $admin->forceFill(['admin_pin' => Hash::make('1234')])->save();
        $customer = User::factory()->create();
        $token = $admin->createToken('admin-pin-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/pin/status')
            ->assertOk()
            ->assertJson(['has_pin' => true, 'unlocked' => false]);

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertForbidden()
            ->assertJson(['code' => 'pin_required']);

        $this->withToken($token)
            ->postJson('/api/admin/pin/verify', ['pin' => '1234'])
            ->assertOk()
            ->assertJson(['verified' => true]);

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonFragment(['id' => $customer->id]);

        $this->withToken($token)
            ->putJson("/api/admin/users/{$customer->id}/role", ['role' => 'admin'])
            ->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'role' => 'admin',
        ]);

        $otherToken = $admin->createToken('another-session')->plainTextToken;
        $this->withToken($otherToken)
            ->getJson('/api/admin/users')
            ->assertForbidden()
            ->assertJson(['code' => 'pin_required']);
    }

    public function test_setting_a_pin_unlocks_user_management_for_that_token(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_verified' => true,
        ]);
        $token = $admin->createToken('admin-pin-setup')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/pin/status')
            ->assertOk()
            ->assertJson(['has_pin' => false, 'unlocked' => false]);

        $this->withToken($token)
            ->postJson('/api/admin/pin/set', [
                'pin' => '5678',
                'password' => 'password',
            ])
            ->assertOk()
            ->assertJson(['message' => 'PIN saved.']);

        $this->withToken($token)
            ->getJson('/api/admin/pin/status')
            ->assertOk()
            ->assertJson(['has_pin' => true, 'unlocked' => true]);

        $this->withToken($token)
            ->getJson('/api/admin/users')
            ->assertOk();
    }
}
