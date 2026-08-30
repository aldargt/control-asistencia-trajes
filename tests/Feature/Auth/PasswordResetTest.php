<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_link_can_be_requested(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo(
            $user,
            ResetPasswordNotification::class,
            function (ResetPasswordNotification $notification) use ($user): bool {
                $response = $this->post(route('password.store'), [
                    'token' => $notification->token,
                    'email' => $user->email,
                    'password' => 'nueva-clave',
                    'password_confirmation' => 'nueva-clave',
                ]);

                $response->assertRedirectToRoute('login');

                return true;
            }
        );

        $this->assertTrue(Hash::check('nueva-clave', $user->fresh()->password));
    }
}
