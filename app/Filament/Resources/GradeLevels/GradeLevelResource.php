<?php

namespace App\Filament\Resources\GradeLevels;

use App\Filament\Resources\GradeLevels\Pages\CreateGradeLevel;
use App\Filament\Resources\GradeLevels\Pages\EditGradeLevel;
use App\Filament\Resources\GradeLevels\Pages\ListGradeLevels;
use App\Filament\Resources\GradeLevels\Schemas\GradeLevelForm;
use App\Filament\Resources\GradeLevels\Tables\GradeLevelsTable;
use App\Models\GradeLevel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Str;

class GradeLevelResource extends Resource
{
    protected static ?string $model = GradeLevel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
 
            TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (string $state, callable $set) {
                    // Slug otomatis dari nama, huruf kecil semua
                    // (Str::slug() sudah lowercase secara default)
                    $set('slug', Str::slug($state));
                }),
 
            TextInput::make('slug')
                ->label('Slug')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(255)
                ->readOnly() // tetap ikut ter-submit, tapi tidak bisa diketik manual
                ->helperText('Otomatis dibuat dari Nama.'),
 
            Select::make('level_type')
                ->label('Tipe Jenjang')
                ->options([
                    'prasekolah' => 'Prasekolah',
                    'tk'         => 'TK',
                    'sd'         => 'SD',
                    'smp'        => 'SMP',
                    'sma'        => 'SMA',
                    'kuliah'     => 'Kuliah',
                    'umum'       => 'Umum',
                ])
                ->required()
                ->native(false),
 
            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true),
 
            TextInput::make('sort_order')
                ->label('Urutan')
                ->numeric()
                ->required()
                ->default(fn () => (int) (GradeLevel::max('sort_order') ?? 0) + 1)
                ->helperText('Otomatis diisi urutan berikutnya. Bisa diubah manual jika perlu.'),
 
        ]);
    }

    public static function table(Table $table): Table
    {
        return GradeLevelsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGradeLevels::route('/'),
            'create' => CreateGradeLevel::route('/create'),
            'edit' => EditGradeLevel::route('/{record}/edit'),
        ];
    }
}
