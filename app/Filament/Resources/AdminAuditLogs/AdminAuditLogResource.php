<?php

namespace App\Filament\Resources\AdminAuditLogs;

use App\Filament\Resources\AdminAuditLogs\Pages\ListAdminAuditLogs;
use App\Models\AdminAuditLog;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;
use UnitEnum;

/** Nhật ký thao tác admin — chỉ đọc. */
class AdminAuditLogResource extends Resource
{
    protected static ?string $model = AdminAuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 60;

    public static function getModelLabel(): string
    {
        return __('admin.audit.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.audit.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.books');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** Nhãn hiển thị của mã hành động; mã chưa có bản dịch hiện nguyên mã. */
    private static function actionLabel(string $action): string
    {
        $key = "admin.audit.actions.{$action}";
        $label = Lang::has($key) ? __($key) : null;

        return is_string($label) ? $label : $action;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('admin'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('admin.common.time'))->dateTime('d/m/Y H:i:s'),
                TextColumn::make('admin.name')->label(__('admin.common.admin'))->placeholder(__('admin.audit.system')),
                TextColumn::make('action')->label(__('admin.audit.action'))->badge()->searchable()
                    ->formatStateUsing(fn (string $state) => self::actionLabel($state)),
                TextColumn::make('subject_type')->label(__('admin.audit.subject'))
                    ->state(fn ($record) => $record->subject_type ? class_basename($record->subject_type).' #'.$record->subject_id : '—'),
                TextColumn::make('changes')->label(__('admin.audit.changes'))
                    ->state(fn ($record) => $record->changes ? json_encode($record->changes, JSON_UNESCAPED_UNICODE) : '—')
                    ->limit(80)->tooltip(fn ($record) => $record->changes ? json_encode($record->changes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null)
                    ->fontFamily('mono')->size('xs'),
                TextColumn::make('ip')->label(__('admin.audit.ip'))->fontFamily('mono')->size('xs'),
            ])
            ->filters([
                SelectFilter::make('action')->label(__('admin.audit.action'))
                    ->options(fn () => AdminAuditLog::query()->distinct()->orderBy('action')->pluck('action')
                        ->mapWithKeys(fn (string $action) => [$action => self::actionLabel($action)])->all()),
                SelectFilter::make('admin_id')->label(__('admin.common.admin'))->relationship('admin', 'name', fn (Builder $query) => $query->where('is_admin', true)),
                Filter::make('created_at')->label(__('admin.common.date_range'))
                    ->schema([DatePicker::make('from')->label(__('admin.common.date_from')), DatePicker::make('until')->label(__('admin.common.date_until'))])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAdminAuditLogs::route('/')];
    }
}
