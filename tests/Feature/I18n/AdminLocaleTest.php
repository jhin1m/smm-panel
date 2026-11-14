<?php

use App\Filament\Pages\ManageDepositSettings;
use App\Filament\Resources\AdminAuditLogs\Pages\ListAdminAuditLogs;
use App\Filament\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Jobs\SendTelegramMessage;
use App\Models\AdminAuditLog;
use App\Models\BankTransaction;
use App\Models\User;
use App\Services\ChatService;
use App\Services\DepositService;
use App\Support\AuditLog;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['password' => 'admin-pass-123']);
});

it('admin chọn en: menu, bảng khách hàng và user menu đều tiếng Anh', function () {
    $this->actingAs($this->admin)->post('/locale', ['locale' => 'en'])->assertRedirect();
    expect($this->admin->fresh()->locale)->toBe('en');

    $this->actingAs($this->admin->fresh())->get('/admin/users')
        ->assertOk()
        ->assertSee('Customers')
        ->assertSee('Balance')
        ->assertDontSee('Khách hàng')
        ->assertDontSee('Số dư');
});

it('admin vi mặc định vẫn thấy nhãn tiếng Việt như cũ', function () {
    $this->actingAs($this->admin)->get('/admin/users')
        ->assertOk()
        ->assertSee('Khách hàng')
        ->assertSee('Số dư');
});

it('user menu chỉ hiện ngôn ngữ khác ngôn ngữ hiện tại và POST tới locale.update', function () {
    $this->actingAs($this->admin)->get('/admin')
        ->assertOk()
        ->assertSee('English')
        ->assertSee(route('locale.update', ['locale' => 'en']), escape: false)
        ->assertDontSee(route('locale.update', ['locale' => 'vi']), escape: false);

    $this->admin->forceFill(['locale' => 'en'])->save();

    $this->actingAs($this->admin->fresh())->get('/admin')
        ->assertOk()
        ->assertSee('Tiếng Việt')
        ->assertSee(route('locale.update', ['locale' => 'vi']), escape: false)
        ->assertDontSee(route('locale.update', ['locale' => 'en']), escape: false);
});

it('khách locale en gửi tin chat → Telegram gửi admin vẫn theo locale mặc định của site (vi)', function () {
    Queue::fake();
    $customer = User::factory()->create(['locale' => 'en']);
    app()->setLocale('en');

    app(ChatService::class)->postCustomerMessage($customer, 'hello');

    Queue::assertPushed(SendTelegramMessage::class, fn ($job) => str_contains($job->html, 'Tin nhắn mới')
        && ! str_contains($job->html, 'New message'));
});

it('admin locale en đổi tài khoản nhận tiền → Telegram vẫn tiếng Việt, trang cài đặt tiếng Anh', function () {
    configureDeposit();
    Queue::fake();
    $this->admin->forceFill(['locale' => 'en'])->save();
    $this->actingAs($this->admin->fresh());
    filament()->setCurrentPanel('admin');
    app()->setLocale('en');

    Livewire::test(ManageDepositSettings::class)
        ->assertSee('Receiving account (VietQR)')
        ->assertDontSee('Tài khoản nhận tiền (VietQR)')
        ->fillForm(['account_number' => '999888777', 'current_password' => 'admin-pass-123'])
        ->call('save')
        ->assertHasNoFormErrors();

    Queue::assertPushed(SendTelegramMessage::class, fn ($job) => str_contains($job->html, 'Tài khoản nhận tiền vừa đổi')
        && ! str_contains($job->html, 'receiving bank account'));
});

it('ghi chú hệ thống dạng key hiển thị theo locale; dòng cũ và ghi chú tay giữ nguyên văn', function () {
    $key = new BankTransaction(['note' => 'deposit.notes.transfer_out']);
    $legacy = new BankTransaction(['note' => 'Sai số tài khoản nhận']);
    $manual = new BankTransaction(['note' => 'khách chuyển nhầm']);

    app()->setLocale('vi');
    expect($key->noteLabel())->toBe('Tiền ra (transfer out)');

    app()->setLocale('en');
    expect($key->noteLabel())->toBe('Outgoing transfer')
        ->and($legacy->noteLabel())->toBe('Sai số tài khoản nhận')
        ->and($manual->noteLabel())->toBe('khách chuyển nhầm')
        ->and((new BankTransaction)->noteLabel())->toBeNull();
});

it('DepositService lưu ghi chú hệ thống dưới dạng key dịch', function () {
    configureDeposit();

    app(DepositService::class)->handleWebhook(sepayPayload(['id' => 901, 'transferType' => 'out']), 'webhook');
    app(DepositService::class)->handleWebhook(sepayPayload(['id' => 902, 'accountNumber' => '000']), 'webhook');

    expect(BankTransaction::firstWhere('sepay_id', 901)->note)->toBe('deposit.notes.transfer_out')
        ->and(BankTransaction::firstWhere('sepay_id', 902)->note)->toBe('deposit.notes.wrong_account');
});

it('nhãn trạng thái và nguồn giao dịch ngân hàng theo locale', function () {
    app()->setLocale('en');
    expect(BankTransaction::statusLabel('unmatched'))->toBe('Unmatched')
        ->and(BankTransaction::sourceLabel('manual'))->toBe('Manual entry')
        ->and(BankTransaction::statusLabel('lạ'))->toBe('lạ');

    app()->setLocale('vi');
    expect(BankTransaction::statusLabel('unmatched'))->toBe('Chưa khớp')
        ->and(BankTransaction::sourceLabel('reconcile'))->toBe('Đối soát');
});

it('danh sách giao dịch ngân hàng ở en không còn tab tiếng Việt', function () {
    $this->actingAs($this->admin);
    filament()->setCurrentPanel('admin');
    app()->setLocale('en');

    Livewire::test(ListBankTransactions::class)
        ->assertSee('Unmatched')
        ->assertSee('Ignored')
        ->assertDontSee('Chưa khớp');
});

it('nhật ký admin hiển thị nhãn hành động theo locale, mã lưu DB không đổi', function () {
    AuditLog::record($this->admin, 'user.lock');
    AuditLog::record($this->admin, 'ma.chua.dich');
    $this->actingAs($this->admin);
    filament()->setCurrentPanel('admin');
    app()->setLocale('en');

    Livewire::test(ListAdminAuditLogs::class)
        ->assertSee('Locked account')
        ->assertSee('ma.chua.dich');

    expect(AdminAuditLog::where('action', 'user.lock')->exists())->toBeTrue();
});
