<?php

namespace App\Filament\Pages;

use App\Exceptions\UpdateRejected;
use App\Services\UpdateService;
use App\Support\AuditLog;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Trang "Cập nhật": hiện phiên bản đang chạy, bản mới nhất + changelog, trạng thái updater và tiến trình.
 * Nút bấm chỉ ghi yêu cầu cho container updater; app không chạm docker.
 */
class ManageUpdates extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?int $navigationSort = 95;

    protected string $view = 'filament.pages.manage-updates';

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('updates.nav');
    }

    public function getTitle(): string
    {
        return __('updates.title');
    }

    /** Bản có thể cập nhật tới (mới hơn bản đang chạy, hợp lệ theo cấu hình), hoặc null. */
    private function updatableRelease(UpdateService $updates): ?array
    {
        $latest = $updates->latestRelease();

        return $latest !== null && $updates->rejectionFor($latest['version']) === null ? $latest : null;
    }

    protected function getViewData(): array
    {
        $updates = app(UpdateService::class);
        $latest = $updates->latestRelease();
        $state = $updates->state();
        $target = $this->updatableRelease($updates);

        return [
            'current' => $updates->currentVersion(),
            'latest' => $latest,
            'target' => $target,
            'state' => $state,
            'alive' => $updates->updaterAlive(),
            'busy' => in_array($state['state'], ['running', 'pending'], true),
            'history' => $updates->history(),
            'allowPrerelease' => (bool) config('updates.allow_prerelease'),
        ];
    }

    protected function getHeaderActions(): array
    {
        $updates = app(UpdateService::class);
        $target = $this->updatableRelease($updates);

        return [
            Action::make('refresh')
                ->label(__('updates.check_again'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(function () use ($updates) {
                    $updates->forgetLatestRelease();
                }),
            Action::make('update')
                ->label(__('updates.update_to', ['version' => $target['version'] ?? '']))
                ->icon(Heroicon::OutlinedArrowUpTray)
                ->requiresConfirmation()
                ->modalHeading(__('updates.confirm_heading'))
                ->modalDescription(__('updates.confirm_description'))
                ->visible(fn () => $target !== null)
                ->disabled(fn () => ! $updates->updaterAlive() || in_array($updates->state()['state'], ['running', 'pending'], true))
                ->action(fn () => $this->requestUpdate()),
        ];
    }

    /** Phiên bản lấy lại từ GitHub (cache), không nhận từ trình duyệt. */
    public function requestUpdate(): void
    {
        $updates = app(UpdateService::class);
        $target = $this->updatableRelease($updates);

        if ($target === null) {
            Notification::make()->warning()->title(__('updates.errors.nothing_to_update'))->send();

            return;
        }

        try {
            $updates->requestUpdate($target['version'], auth()->user());
        } catch (UpdateRejected $e) {
            Notification::make()->danger()->title(__('updates.request_failed'))->body($e->getMessage())->send();

            return;
        }

        AuditLog::record(auth()->user(), 'system.update_requested', null, [
            'from' => $updates->currentVersion(),
            'to' => $target['version'],
        ]);

        Notification::make()->success()->title(__('updates.requested', ['version' => $target['version']]))->send();
    }
}
