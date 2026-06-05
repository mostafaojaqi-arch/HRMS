<?php

namespace Tests\Feature;

use App\Actions\Fortify\LdapAuthenticator;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create([
            'username' => 'jane.doe',
        ]);

        $response = $this->post('/login', [
            'login' => $user->username,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'username' => 'jane.doe',
        ]);

        $this->post('/login', [
            'login' => $user->username,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_authenticate_with_ldap_when_enabled(): void
    {
        config()->set('ldap.enabled', true);
        config()->set('ldap.fallback_to_local', false);

        $user = User::factory()->create([
            'username' => 'ldap.user',
        ]);

        $ldapAuthenticator = Mockery::mock(LdapAuthenticator::class);
        $ldapAuthenticator
            ->shouldReceive('attempt')
            ->once()
            ->with('ldap.user', 'ldap-password')
            ->andReturn(true);

        $this->app->instance(LdapAuthenticator::class, $ldapAuthenticator);

        $response = $this->post('/login', [
            'login' => 'ldap.user',
            'password' => 'ldap-password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_local_password_authentication_can_be_used_as_fallback_when_ldap_fails(): void
    {
        config()->set('ldap.enabled', true);
        config()->set('ldap.fallback_to_local', true);

        $user = User::factory()->create([
            'username' => 'fallback.user',
        ]);

        $ldapAuthenticator = Mockery::mock(LdapAuthenticator::class);
        $ldapAuthenticator
            ->shouldReceive('attempt')
            ->once()
            ->with('fallback.user', 'password')
            ->andReturn(false);

        $this->app->instance(LdapAuthenticator::class, $ldapAuthenticator);

        $response = $this->post('/login', [
            'login' => 'fallback.user',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_ldap_can_auto_create_a_local_user_when_enabled(): void
    {
        config()->set('ldap.enabled', true);
        config()->set('ldap.fallback_to_local', false);
        config()->set('ldap.auto_create_local_users', true);

        $ldapAuthenticator = Mockery::mock(LdapAuthenticator::class);
        $ldapAuthenticator
            ->shouldReceive('attempt')
            ->once()
            ->with('new.ldap.user', 'ldap-password')
            ->andReturn(true);

        $this->app->instance(LdapAuthenticator::class, $ldapAuthenticator);

        $response = $this->post('/login', [
            'login' => 'new.ldap.user',
            'password' => 'ldap-password',
        ]);

        $createdUser = User::where('username', 'new.ldap.user')->first();

        $this->assertNotNull($createdUser);
        $this->assertAuthenticatedAs($createdUser);
        $response->assertRedirect(RouteServiceProvider::HOME);
    }
}
