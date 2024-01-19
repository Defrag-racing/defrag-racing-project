<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    public function django_hash_password ($password, $salt, $iterations = 260000) {
        $hash = hash_pbkdf2("sha256", $password, $salt, $iterations, 32, true);

        $encoded_hash = base64_encode($hash);

        return "pbkdf2_sha256$" . $iterations . "$" . $salt . "$" . $encoded_hash;
    }

    public function django_verify_password ($password, $old_hash) {
        list($algorithm, $iterations, $salt, $encoded_hash) = explode('$', $old_hash);

        $hash = base64_decode($encoded_hash, true);

        $hashed_password = $this->django_hash_password($password, $salt, (int)$iterations);

        return hash_equals($hashed_password, $old_hash);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
        
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('username', $request->username)->first();

            if (! $user) {
                return null;
            }

            if ($user->password !== 'NOPASS') {
                return Hash::check($request->password, $user->password) ? $user : null;
            }

            if (! $this->django_verify_password($request->password, $user->oldhash)) {
                return null;
            }

            $user->password = Hash::make($request->password);
            $user->save();

            
            return $user;
        });
    }
}
