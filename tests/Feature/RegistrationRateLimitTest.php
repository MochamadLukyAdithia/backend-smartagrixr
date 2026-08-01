<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RegistrationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_rate_limited_after_5_attempts(): void
    {
        // Clear rate limit cache
        Cache::flush();

        $ip = '127.0.0.1';
        $validData = [
            'name' => 'Test User',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ];

        // First 5 registrations should succeed
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/api/register', array_merge($validData, [
                'email' => "user{$i}@example.com",
            ]));

            $this->assertEquals(201, $response->status(), "Registration $i should succeed");
        }

        // 6th registration should be rate limited (429)
        $response = $this->postJson('/api/register', array_merge($validData, [
            'email' => 'user6@example.com',
        ]));

        $this->assertEquals(429, $response->status());
        $this->assertJson($response->getContent());
        $response->assertJsonStructure(['message', 'retry_after']);
    }

    public function test_rate_limit_includes_retry_after(): void
    {
        Cache::flush();

        $validData = [
            'name' => 'Test User',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ];

        // Hit rate limit
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/register', array_merge($validData, [
                'email' => "user{$i}@example.com",
            ]));
        }

        $response = $this->postJson('/api/register', array_merge($validData, [
            'email' => 'user6@example.com',
        ]));

        $this->assertEquals(429, $response->status());
        $this->assertTrue($response->json('retry_after') > 0);
    }
}
