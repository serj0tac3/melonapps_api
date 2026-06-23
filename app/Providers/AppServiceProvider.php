<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail; // <-- Añadido para verificar email
use Illuminate\Notifications\Messages\MailMessage; // <-- Añadido para construir el mensaje
use Illuminate\Support\ServiceProvider;

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
        // 1. Recuperación de contraseña (lo que ya tenías)
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url')."/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        // 2. Verificación de Email (Lo nuevo)
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            
            // Construimos la URL hacia tu Angular usando tu configuración actual
            $frontendUrl = config('app.frontend_url') . '/verificar-correo?url=' . urlencode($url);

            return (new MailMessage)
                ->subject('¡Bienvenido a MelonCards! Verifica tu email')
                ->greeting('¡Hola!')
                ->line('Gracias por registrarte. Solo queda un paso para empezar.')
                ->action('Verificar mi cuenta', $frontendUrl)
                ->line('Si no fuiste tú, simplemente ignora este correo.');
        });
    }
}