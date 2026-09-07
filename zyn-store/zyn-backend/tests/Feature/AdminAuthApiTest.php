<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_receive_a_sanctum_token(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'admin@example.com',
            'password' => 'strong-password',
        ]);

        $response = $this->postJson('/api/admin/login', [
            'email' => $admin->email,
            'password' => 'strong-password',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonStructure(['data' => ['token', 'user' => ['id', 'name', 'email']]]);
    }

    public function test_regular_user_cannot_log_in_as_admin(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => 'strong-password',
        ]);

        $this->postJson('/api/admin/login', [
            'email' => $user->email,
            'password' => 'strong-password',
        ])->assertUnprocessable();
    }
}
