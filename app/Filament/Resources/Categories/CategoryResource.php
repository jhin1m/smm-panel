<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\Category;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?int $navigationSort = 20;

    public static function getModelLabel(): string
    {
        return __('admin.categories.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.categories.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.services');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('admin.common.name'))->required()->maxLength(100)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (?string $state, Set $set, ?Category $record) => $record ? null : $set('slug', Str::slug((string) $state))),
            TextInput::make('slug')->label(__('admin.categories.slug'))->required()->maxLength(120)->unique(ignoreRecord: true)->alphaDash(),
            TextInput::make('badge')->label(__('admin.categories.badge'))->required()->maxLength(3)->helperText(__('admin.categories.badge_hint')),
            ColorPicker::make('color')->label(__('admin.categories.color'))->required()->regex('/^#[0-9a-fA-F]{6}$/'),
            TextInput::make('sort_order')->label(__('admin.common.sort_order'))->integer()->default(0),
            Toggle::make('is_active')->label(__('admin.categories.is_active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                ColorColumn::make('color')->label(''),
                TextColumn::make('badge')->label(__('admin.categories.icon'))->fontFamily('mono'),
                TextColumn::make('name')->label(__('admin.common.name'))->searchable()->weight('semibold'),
                TextColumn::make('services_count')->label(__('admin.categories.services'))->counts('services'),
                ToggleColumn::make('is_active')->label(__('admin.categories.enabled')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (Category $record) => ! $record->services()->exists()),
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        return ! $record->services()->exists();
    }

    public static function getPages(): array
    {
        return ['index' => ManageCategories::route('/')];
    }
}
