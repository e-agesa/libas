<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        // Registration routes are disabled — users managed via admin panel
        $response = $this->get('/register');

        $response->assertStatus(404);
    }

    public function test_new_users_cannot_register_when_disabled(): void
    {
        // Registration routes are disabled — users managed via admin panel
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(404);
    }
}
