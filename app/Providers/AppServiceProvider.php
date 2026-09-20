<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\DemoResetPolicy;
use App\Services\DemoSessionService;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
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
        $this->configureDefaults();
        $this->configureAuthorization();
        $this->configureRateLimiting();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    protected function configureAuthorization(): void
    {
        Gate::define('prune-stale-demo', [DemoResetPolicy::class, 'pruneStale']);
        Gate::define('force-reset-demo', [DemoResetPolicy::class, 'forceReset']);
        Gate::define('view-raw-prompts', fn (User $user): bool => $user->isAdmin());
        Gate::define('view-secrets', fn (User $user): bool => $user->isAdmin());
        Gate::define('manage-users', fn (User $user): bool => $user->isAdmin());
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('tickets', function (Request $request) {
            $session = $request->cookie(DemoSessionService::COOKIE);

            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(5)->by($session ?: $request->ip()),
            ];
        });

        RateLimiter::for('chat', function (Request $request) {
            $ip = (string) $request->ip();
            $cookie = $request->cookie(DemoSessionService::COOKIE);
            $session = is_string($cookie) && $cookie !== '' ? $cookie : $ip;
            $tooMany = function (Request $request, array $headers) {
                $seconds = max(1, (int) ($headers['Retry-After'] ?? 60));
                $unit = $seconds === 1 ? 'second' : 'seconds';
                $message = "Chat limit reached. Try again in {$seconds} {$unit}.";

                return response()->json([
                    'message' => $message,
                    'errors' => [
                        'question' => [$message],
                    ],
                ], 429, $headers);
            };

            return [
                Limit::perMinute(10)->by('ip:'.$ip)->response($tooMany),
                Limit::perMinute(10)->by('session:'.$session)->response($tooMany),
            ];
        });

        RateLimiter::for('regenerate', function (Request $request) {
            $max = (int) config('supportflow.rate_limits.regenerate.max_attempts', 5);
            $decay = (int) config('supportflow.rate_limits.regenerate.decay_seconds', 600);

            return Limit::perMinutes(max(1, (int) ceil($decay / 60)), $max)
                ->by((string) ($request->user()?->id ?: $request->ip()));
        });

        RateLimiter::for('demo.enter-agent', function (Request $request) {
            return Limit::perMinute(10)->by((string) $request->ip());
        });

        RateLimiter::for('demo.prune-stale', function (Request $request) {
            return Limit::perMinute(3)->by((string) ($request->user()?->id ?: $request->ip()));
        });
    }
}
