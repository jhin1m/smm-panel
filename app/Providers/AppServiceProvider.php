<?php

namespace App\Providers;

use App\Http\Middleware\CustomerMaintenance;
use App\Support\CustomOverrides;
use App\Support\RuntimeConfig;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Phải chạy đầu tiên: các service resolve sau đó đọc config đã được đè từ settings.
        RuntimeConfig::apply();

        // View/lang người dùng đặt trong custom/ (nếu có) ghi đè bản lõi.
        CustomOverrides::register();

        // Bảo trì trang khách áp cả cho action Livewire của component nằm trên route khách.
        Livewire::addPersistentMiddleware([CustomerMaintenance::class]);

        // Email đặt lại mật khẩu: chữ lấy từ lang/*/mail.php, locale theo user (HasLocalePreference).
        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = url(route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], false));
            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');
            $app = config('app.name');

            return (new MailMessage)
                ->subject(__('mail.reset_password.subject', ['app' => $app]))
                ->greeting(__('mail.reset_password.greeting'))
                ->line(__('mail.reset_password.intro'))
                ->action(__('mail.reset_password.action'), $url)
                ->line(__('mail.reset_password.expire', ['minutes' => $minutes]))
                ->line(__('mail.reset_password.ignore'))
                ->salutation(__('mail.reset_password.salutation', ['app' => $app]));
        });
    }
}
