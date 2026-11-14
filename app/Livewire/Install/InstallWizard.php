<?php

namespace App\Livewire\Install;

use App\Actions\CreateOrResetAdmin;
use App\Models\User;
use App\Settings\DepositSettings;
use App\Settings\GeneralSettings;
use App\Settings\InstallSettings;
use App\Settings\IntegrationSettings;
use App\Support\InstallState;
use App\Support\Locales;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Wizard cài đặt lần đầu. Bảo vệ nhiều lớp vì mọi action đều là endpoint Livewire công khai:
 *  - `step` là #[Locked]: client không tự nhảy bước được.
 *  - Mỗi request (hydrate) và mỗi action (guard) kiểm lại cờ installed_at đọc thẳng DB → đã cài thì 404.
 *  - Mọi action trừ verifyToken đòi session `install.verified` (chỉ đặt khi đúng INSTALL_TOKEN).
 *  - finish() chạy trong transaction có advisory lock và kiểm lại cờ, nên 2 request song song chỉ 1 cái thắng.
 */
#[Layout('layouts.install')]
class InstallWizard extends Component
{
    public const SESSION_KEY = 'install.verified';

    public const FIRST_STEP = 1;

    public const SITE_STEP = 2;

    public const ADMIN_STEP = 3;

    public const OPTIONAL_STEP = 4;

    public const CONFIRM_STEP = 5;

    #[Locked]
    public int $step = self::FIRST_STEP;

    public string $token = '';

    public string $site_name = '';

    public string $default_locale = '';

    public string $support_email = '';

    public string $support_zalo = '';

    public string $support_telegram = '';

    public string $admin_name = '';

    public string $admin_email = '';

    public string $admin_password = '';

    public string $admin_password_confirmation = '';

    public string $bank_bin = '';

    public string $bank_name = '';

    public string $account_number = '';

    public string $account_name = '';

    public string $smtp_host = '';

    public string $smtp_port = '';

    public string $smtp_username = '';

    public string $smtp_password = '';

    public string $smtp_encryption = 'tls';

    public string $mail_from_address = '';

    public function mount(): void
    {
        abort_if(InstallState::isInstalled(), 404);

        // Đổi ngôn ngữ (POST /locale) hay F5 không bắt nhập lại token khi session đã xác thực.
        $this->step = $this->isVerified() ? self::SITE_STEP : self::FIRST_STEP;
        // install.sh in URL /install?token=...: điền sẵn ô token (vẫn phải bấm Tiếp tục để xác thực).
        $this->token = mb_substr(request()->string('token')->toString(), 0, 200);
        $this->default_locale = app()->getLocale();
        $this->site_name = (string) config('app.name');
    }

    /** Chạy ở đầu MỌI request Livewire tiếp theo (kể cả cập nhật property), không chỉ action. */
    public function hydrate(): void
    {
        abort_if(InstallState::isInstalled(), 404);
    }

    public function verifyToken(): void
    {
        $this->guard(requireVerified: false);

        $expected = (string) config('app.install_token');

        if ($expected === '') {
            $this->addError('token', __('install.token.not_configured'));

            return;
        }

        $this->validate(['token' => ['required', 'string', 'max:200']], [], ['token' => __('install.fields.token')]);

        if (! hash_equals($expected, trim($this->token))) {
            $this->addError('token', __('install.token.invalid'));

            return;
        }

        // Đổi session id khi vừa chứng minh có token: cookie phiên bị cài sẵn (session fixation) không thừa hưởng cờ xác thực.
        session()->regenerate();
        session()->put(self::SESSION_KEY, true);
        $this->token = '';
        $this->step = self::SITE_STEP;
    }

    public function next(): void
    {
        $this->guard();

        match ($this->step) {
            self::SITE_STEP => $this->validateWith($this->siteRules()),
            self::ADMIN_STEP => $this->validateWith($this->adminRules()),
            self::OPTIONAL_STEP => $this->validateWith($this->optionalRules()),
            default => abort(404),
        };

        $this->step++;
    }

    public function back(): void
    {
        $this->guard();

        if ($this->step > self::SITE_STEP) {
            $this->step--;
        }
    }

    /** Bước 4 cho phép bỏ qua: xoá giá trị đã nhập dở rồi sang bước xác nhận. */
    public function skipOptional(): void
    {
        $this->guard();

        abort_unless($this->step === self::OPTIONAL_STEP, 404);

        $this->reset(['bank_bin', 'bank_name', 'account_number', 'account_name', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'mail_from_address']);
        $this->smtp_encryption = 'tls';
        $this->step = self::CONFIRM_STEP;
    }

    public function finish(): void
    {
        $this->guard();

        abort_unless($this->step === self::CONFIRM_STEP, 404);

        // Validate lại toàn bộ: không tin vào việc các bước trước đã chạy.
        $this->admin_email = User::normalizeEmail($this->admin_email);
        $this->validateWith([...$this->siteRules(), ...$this->adminRules(), ...$this->optionalRules()]);

        DB::transaction(function () {
            // Khoá theo transaction: 2 finish() song song được tuần tự hoá, request sau thấy cờ và bị 404.
            DB::select("select pg_advisory_xact_lock(hashtext('app-install'))");
            abort_if(InstallState::isInstalled(), 404);

            $this->saveSettings();
            app(CreateOrResetAdmin::class)->handle($this->admin_email, trim($this->admin_name), $this->admin_password);

            $install = app(InstallSettings::class);
            $install->installed_at = now()->toIso8601String();
            $install->save();
        });

        session()->forget(self::SESSION_KEY);

        $this->redirect(route('filament.admin.auth.login'));
    }

    /** Cảnh báo (không chặn) khi APP_URL khác host đang truy cập: Google OAuth, link email sẽ trỏ sai. */
    public function urlMismatch(): ?array
    {
        $configured = parse_url((string) config('app.url'), PHP_URL_HOST);
        $actual = request()->getHost();

        if (! is_string($configured) || $configured === '' || strcasecmp($configured, $actual) === 0) {
            return null;
        }

        return ['configured' => $configured, 'actual' => $actual];
    }

    public function render()
    {
        return view('livewire.install.install-wizard', [
            'locales' => Locales::all(),
            'tokenConfigured' => (string) config('app.install_token') !== '',
            'urlMismatch' => $this->step === self::FIRST_STEP ? $this->urlMismatch() : null,
        ])->title(__('install.title'));
    }

    private function isVerified(): bool
    {
        return session(self::SESSION_KEY) === true;
    }

    /** Điều kiện chung của mọi action: chưa cài (đọc DB) và (trừ verifyToken) đã qua bước token. */
    private function guard(bool $requireVerified = true): void
    {
        abort_if(InstallState::isInstalled(), 404);
        abort_if($requireVerified && ! $this->isVerified(), 404);
    }

    /** @param  array<string, mixed>  $rules */
    private function validateWith(array $rules): void
    {
        $attributes = [];
        foreach (array_keys($rules) as $field) {
            $attributes[$field] = __("install.fields.{$field}");
        }

        $this->validate($rules, [], $attributes);
    }

    /** @return array<string, mixed> */
    private function siteRules(): array
    {
        return [
            'site_name' => ['required', 'string', 'max:60'],
            'default_locale' => ['required', Rule::in(Locales::supported())],
            'support_email' => ['nullable', 'email', 'max:255'],
            'support_zalo' => ['nullable', 'string', 'max:100'],
            'support_telegram' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, mixed> */
    private function adminRules(): array
    {
        // Email chuẩn hoá (chữ thường) trước khi so unique, khớp cách MakeAdmin lưu.
        $this->admin_email = User::normalizeEmail($this->admin_email);

        return [
            'admin_name' => ['required', 'string', 'max:50'],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin_password' => ['required', 'string', 'min:12', 'max:200', 'same:admin_password_confirmation'],
            'admin_password_confirmation' => ['required', 'string'],
        ];
    }

    /** @return array<string, mixed> */
    private function optionalRules(): array
    {
        $bank = filled($this->bank_bin) || filled($this->account_number) || filled($this->account_name) || filled($this->bank_name);
        $smtp = filled($this->smtp_host);

        return [
            'bank_bin' => [$bank ? 'required' : 'nullable', 'digits:6'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_number' => [$bank ? 'required' : 'nullable', 'max:30', 'regex:/^[0-9A-Za-z]+$/'],
            'account_name' => [$bank ? 'required' : 'nullable', 'string', 'max:100'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => [$smtp ? 'required' : 'nullable', 'integer', 'between:1,65535'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
        ];
    }

    private function saveSettings(): void
    {
        $general = app(GeneralSettings::class);
        $general->site_name = trim($this->site_name);
        $general->default_locale = $this->default_locale;
        $general->support_email = $this->nullable($this->support_email);
        $general->support_zalo = $this->nullable($this->support_zalo);
        $general->support_telegram = $this->nullable($this->support_telegram);
        $general->save();

        if (filled($this->bank_bin)) {
            $deposit = app(DepositSettings::class);
            $deposit->bank_bin = $this->bank_bin;
            $deposit->bank_name = $this->nullable($this->bank_name);
            $deposit->account_number = $this->account_number;
            $deposit->account_name = $this->account_name;
            $deposit->save();
        }

        if (filled($this->smtp_host)) {
            $integrations = app(IntegrationSettings::class);
            $integrations->smtp_host = trim($this->smtp_host);
            $integrations->smtp_port = (int) $this->smtp_port;
            $integrations->smtp_username = $this->nullable($this->smtp_username);
            $integrations->smtp_password = $this->nullable($this->smtp_password);
            $integrations->smtp_encryption = $this->smtp_encryption;
            $integrations->mail_from_address = $this->nullable($this->mail_from_address);
            $integrations->save();
        }
    }

    private function nullable(string $value): ?string
    {
        return filled(trim($value)) ? trim($value) : null;
    }
}
