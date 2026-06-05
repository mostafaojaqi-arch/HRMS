<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\LdapAuthenticator;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Spatie\Permission\Models\Role;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            $login = (string) $request->input('login');
            $password = (string) $request->input('password');

            $user = User::where('email', $login)
                ->orWhere('username', $login)
                ->first();

            if (config('ldap.enabled') && app(LdapAuthenticator::class)->attempt($login, $password)) {
                $defaultRole = (string) config('ldap.default_role', '');

                if ($user) {
                    if ($defaultRole !== '' && ! $user->hasRole($defaultRole)) {
                        $user->assignRole(Role::query()->firstOrCreate(['name' => $defaultRole]));
                    }

                    return $user;
                }

                if (! config('ldap.auto_create_local_users', true)) {
                    return null;
                }

                $isEmailLogin = filter_var($login, FILTER_VALIDATE_EMAIL) !== false;
                $email = $isEmailLogin ? $login : null;
                $username = $isEmailLogin ? Str::before($login, '@') : $login;
                $username = trim($username) !== '' ? trim($username) : 'ldap_user';

                $baseUsername = Str::limit($username, 240, '');
                $candidateUsername = $baseUsername;
                $counter = 1;

                while (User::where('username', $candidateUsername)->exists()) {
                    $suffix = (string) $counter;
                    $candidateUsername = Str::limit($baseUsername, 240 - strlen($suffix), '').$suffix;
                    $counter++;
                }

                $displayName = Str::headline(str_replace(['.', '_', '-'], ' ', $candidateUsername));

                $createdUser = User::create([
                    'name' => $displayName,
                    'username' => $candidateUsername,
                    'email' => $email,
                    'password' => Hash::make(Str::random(64)),
                ]);

                if ($defaultRole !== '') {
                    $createdUser->assignRole(Role::query()->firstOrCreate(['name' => $defaultRole]));
                }

                return $createdUser;
            }

            if (config('ldap.enabled') && ! config('ldap.fallback_to_local', true)) {
                return null;
            }

            if ($user && Hash::check($password, $user->password)) {
                return $user;
            }

            return null;
        });

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::confirmPasswordsUsing(function (User $user, ?string $password = null): bool {
            return Hash::check((string) $password, $user->password);
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
