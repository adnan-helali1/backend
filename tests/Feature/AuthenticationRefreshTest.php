<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_rotate_an_access_token(): void
    {
        Admin::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $oldToken = $this->postJson('/api/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->json('data.token');

        $response = $this->withToken($oldToken)->postJson('/api/admin/refresh');

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Token refreshed')
            ->assertJsonPath('data.token_type', 'bearer')
            ->assertJsonPath('data.expires_in', config('jwt.ttl') * 60);

        $this->assertNotSame($oldToken, $response->json('data.token'));
        $this->withToken($response->json('data.token'))
            ->getJson('/api/admin/stats/overview')
            ->assertOk();
    }

    public function test_store_can_rotate_an_access_token(): void
    {
        Store::create([
            'name' => 'Test Store',
            'owner_name' => 'Owner',
            'phone' => '123456789',
            'email' => 'store@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);

        $oldToken = $this->postJson('/api/store/login', [
            'email' => 'store@example.com',
            'password' => 'password',
        ])->json('data.token');

        $response = $this->withToken($oldToken)->postJson('/api/store/refresh');

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Token refreshed')
            ->assertJsonPath('data.token_type', 'bearer')
            ->assertJsonPath('data.expires_in', config('jwt.ttl') * 60);

        $this->assertNotSame($oldToken, $response->json('data.token'));
        $this->withToken($response->json('data.token'))
            ->getJson('/api/store/profile')
            ->assertOk();
    }

    public function test_refresh_requires_a_valid_token(): void
    {
        $this->postJson('/api/admin/refresh')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Token cannot be refreshed');

        $this->withToken('not-a-jwt')->postJson('/api/store/refresh')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Token cannot be refreshed');
    }
}
