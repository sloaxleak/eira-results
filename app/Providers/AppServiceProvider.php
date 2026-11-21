<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Notifications\ClickSendSmsSender;
use App\Services\Notifications\DemoOutbox;
use App\Services\Notifications\DisabledSmsSender;
use App\Services\Notifications\EmailSender;
use App\Services\Notifications\FakeEmailSender;
use App\Services\Notifications\FakeSmsSender;
use App\Services\Notifications\ResendEmailSender;
use App\Services\Notifications\SmsSender;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DemoOutbox::class);

        $this->app->singleton(EmailSender::class, fn ($app) => match (config('eira.email_driver')) {
            'resend' => new ResendEmailSender(config('eira.resend.key'), config('eira.resend.from')),
            default => new FakeEmailSender($app->make(DemoOutbox::class)),
        });

        $this->app->singleton(SmsSender::class, fn ($app) => match (config('eira.sms_driver')) {
            'fake' => new FakeSmsSender($app->make(DemoOutbox::class)),
            'clicksend' => new ClickSendSmsSender(
                config('eira.clicksend.username'),
                config('eira.clicksend.key'),
                config('eira.clicksend.from'),
            ),
            default => new DisabledSmsSender,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('staff.access', fn (User $user) => $user->isStaff());

        Gate::define('documents.intake', fn (User $user) => $user->role === User::ROLE_OPERATOR);

        Gate::define('documents.view-file', fn (User $user) => in_array($user->role, [User::ROLE_OPERATOR, User::ROLE_PUBLISHER], true));

        Gate::define('documents.publish', fn (User $user) => $user->role === User::ROLE_PUBLISHER
            || (config('eira.publish_on_review') && $user->role === User::ROLE_OPERATOR));

        Gate::define('users.manage', fn (User $user) => $user->isStaff() && $user->role === User::ROLE_ADMIN);
    }
}
