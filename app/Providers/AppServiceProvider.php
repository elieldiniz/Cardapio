<?php

namespace App\Providers;

use App\Contracts\MuxClient;
use App\Contracts\VideoGenerationProvider;
use App\Models\Restaurant;
use App\Models\Subscription;
use App\Models\SubscriptionItem;
use App\Services\Ai\VideoGenerationProviderResolver;
use App\Services\Mux\MuxApiClient;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MuxClient::class, function () {
            return new MuxApiClient(
                tokenId: (string) config('services.mux.token_id'),
                tokenSecret: (string) config('services.mux.token_secret'),
                webhookSecret: (string) config('services.mux.webhook_secret'),
                baseUrl: (string) config('services.mux.base_url'),
            );
        });

        $this->app->singleton(VideoGenerationProviderResolver::class);

        $this->app->bind(VideoGenerationProvider::class, function ($app) {
            return $app->make(VideoGenerationProviderResolver::class)->resolveDefault();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The billable is Restaurant, not the default Cashier User.
        Cashier::useCustomerModel(Restaurant::class);
        Cashier::useSubscriptionModel(Subscription::class);
        Cashier::useSubscriptionItemModel(SubscriptionItem::class);

        VerifyEmail::toMailUsing(function ($notifiable, string $url) {
            // A fresh sign-up gets the welcome; an e-mail change gets the short version.
            $newAccount = $notifiable->created_at?->gt(now()->subHour()) ?? true;

            return (new MailMessage)
                ->subject($newAccount ? 'Confirme seu e-mail e comece seu cardápio em vídeo' : 'Confirme seu novo e-mail no '.config('app.name'))
                ->markdown('mail.verify-email', [
                    'name' => $notifiable->firstName(),
                    'restaurant' => $notifiable->restaurant?->name,
                    'url' => $url,
                    'minutes' => config('auth.verification.expire', 60),
                    'newAccount' => $newAccount,
                ]);
        });

        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);

            return (new MailMessage)
                ->subject('Crie uma nova senha para o '.config('app.name'))
                ->markdown('mail.reset-password', [
                    'name' => $notifiable->firstName(),
                    'url' => $url,
                    'minutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
                ]);
        });
    }
}
