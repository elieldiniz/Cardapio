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

        VerifyEmail::toMailUsing(fn ($notifiable, string $url) => (new MailMessage)
            ->subject('Confirme seu e-mail')
            ->greeting('Olá!')
            ->line('Confirme seu e-mail para liberar o painel do seu restaurante.')
            ->action('Confirmar e-mail', $url)
            ->line('Se você não criou uma conta, ignore este e-mail.')
            ->salutation('Equipe '.config('app.name')));

        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);
            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Redefinição de senha')
                ->greeting('Olá!')
                ->line('Recebemos um pedido para redefinir a senha da sua conta.')
                ->action('Redefinir senha', $url)
                ->line("Este link expira em {$minutes} minutos.")
                ->line('Se você não pediu a redefinição, ignore este e-mail.')
                ->salutation('Equipe '.config('app.name'));
        });
    }
}
