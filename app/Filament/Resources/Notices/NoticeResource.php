<?php

namespace App\Filament\Resources\Notices;

use App\Filament\Resources\Notices\Pages\ManageNotices;
use App\Models\Notice;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use UnitEnum;

class NoticeResource extends Resource
{
    protected static ?string $model = Notice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return __('admin.notices.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.notices.plural');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return __('admin.nav.settings');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('title')->label(__('admin.notices.title'))->required()->maxLength(150),
            Textarea::make('body')->label(__('admin.notices.body'))->required()->rows(4)->maxLength(5000),
            DateTimePicker::make('published_at')->label(__('admin.notices.published_at'))->default(now())->required()->seconds(false),
            Toggle::make('is_published')->label(__('admin.notices.is_published'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('admin.notices.title'))->searchable()->weight('semibold')->limit(60),
                TextColumn::make('published_at')->label(__('admin.notices.published_at'))->dateTime('d/m/Y H:i')->sortable(),
                ToggleColumn::make('is_published')->label(__('admin.notices.is_published')),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageNotices::route('/')];
    }
}
