<?php

namespace App\Filament\Resources\Services;

use App\Enums\ServiceUnit;
use App\Filament\Resources\Services\Pages\ManageServices;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 21;

    public static function getModelLabel(): string
    {
        return __('admin.services.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.services.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.services');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('category_id')->label(__('admin.common.platform'))->relationship('category', 'name')->required()->searchable()->preload(),
            TextInput::make('name')->label(__('admin.services.name'))->required()->maxLength(200),
            Select::make('unit')->label(__('admin.services.unit'))->options(ServiceUnit::options())->default(ServiceUnit::Per1000->value)->required(),
            TextInput::make('price')->label(__('admin.services.price'))->integer()->required()->minValue(0)->maxValue(100_000_000)->suffix('đ'),
            TextInput::make('min_quantity')->label(__('admin.services.min'))->integer()->required()->minValue(1)->maxValue(10_000_000)->live(onBlur: true),
            TextInput::make('max_quantity')->label(__('admin.services.max'))->integer()->required()->maxValue(10_000_000)
                ->minValue(fn (Get $get) => max(1, (int) $get('min_quantity'))),
            TextInput::make('speed')->label(__('admin.services.speed'))->maxLength(50)->placeholder(__('admin.services.speed_placeholder')),
            TextInput::make('start_time')->label(__('admin.services.start_time'))->maxLength(50)->placeholder(__('admin.services.start_time_placeholder')),
            Textarea::make('description')->label(__('admin.services.description'))->maxLength(2000)->columnSpanFull(),
            TextInput::make('sort_order')->label(__('admin.common.sort_order'))->integer()->default(0),
            Toggle::make('is_active')->label(__('admin.services.is_active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('category'))
            ->defaultSort('id')
            ->columns([
                TextColumn::make('id')->label('ID')->fontFamily('mono')->sortable(),
                TextColumn::make('name')->label(__('admin.services.service'))->searchable()->weight('semibold')
                    ->description(fn (Service $record) => $record->category?->name),
                TextColumn::make('min_quantity')->label(__('admin.services.limits'))->fontFamily('mono')
                    ->formatStateUsing(fn (Service $record) => number_format($record->min_quantity, 0, ',', '.').'–'.number_format($record->max_quantity, 0, ',', '.')),
                TextColumn::make('speed')->label(__('admin.services.speed'))->placeholder('—'),
                TextColumn::make('unit')->label(__('admin.services.unit_short'))->formatStateUsing(fn (ServiceUnit $state) => $state->priceSuffix()),
                TextInputColumn::make('price')->label(__('admin.services.price_vnd'))->type('number')
                    ->rules(['required', 'integer', 'min:0', 'max:100000000'])->alignEnd(),
                ToggleColumn::make('is_active')->label(__('admin.services.on_sale')),
            ])
            ->filters([
                SelectFilter::make('category_id')->label(__('admin.common.platform'))->relationship('category', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                // Dịch vụ đã có đơn → không xoá (FK restrict), chỉ tắt.
                DeleteAction::make()->visible(fn (Service $record) => ! $record->orders()->exists()),
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        return ! $record->orders()->exists();
    }

    public static function getPages(): array
    {
        return ['index' => ManageServices::route('/')];
    }
}
