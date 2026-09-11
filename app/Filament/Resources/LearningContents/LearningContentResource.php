<?php

namespace App\Filament\Resources\LearningContents;

use App\Filament\Resources\LearningContents\Pages\CreateLearningContent;
use App\Filament\Resources\LearningContents\Pages\EditLearningContent;
use App\Filament\Resources\LearningContents\Pages\ListLearningContents;
use App\Filament\Resources\LearningContents\Schemas\LearningContentForm;
use App\Filament\Resources\LearningContents\Tables\LearningContentsTable;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use App\Models\LearningContent;
use App\Models\Subject;
use App\Models\GradeLevel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LearningContentResource extends Resource
{
    protected static ?string $model = LearningContent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    
    protected static function cleanCanvaEmbed(?string $html): ?string
    {
        if (blank($html)) {
            return $html;
        }

        $html = str_replace('""', '"', $html);

        if (preg_match('/<div\b[^>]*>.*?<\/iframe>\s*<\/div>/is', $html, $matches)) {
            return trim($matches[0]);
        }

        return $html;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

            Select::make('subject_id')
                ->label('Mata Pelajaran')
                ->options(
                    Subject::where('is_active', true)
                        ->orderBy('sort_order')
                        ->pluck('name', 'id')
                )
                ->searchable()
                ->required(),

            Select::make('grade_level_id')
                ->label('Tingkat Kelas')
                ->options(
                    GradeLevel::where('is_active', true)
                        ->orderBy('sort_order')
                        ->pluck('name', 'id')
                )
                ->searchable()
                ->required(),

            TextInput::make('title')
                ->label('Judul')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Textarea::make('description')
                ->label('Deskripsi')
                ->rows(3)
                ->columnSpanFull(),

            FileUpload::make('thumbnail_path')
                ->label('Thumbnail')
                ->image()
                ->imageEditor()
                ->directory('thumbnails')
                ->disk('r2')
                ->visibility('private')
                ->maxSize(2048)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->helperText('Kosongkan kalau ingin thumbnail diambil otomatis dari embed Canva.')
                ->columnSpanFull(),

            Textarea::make('embed_url')
                ->label('Embed URL (Canva)')
                ->placeholder('Gunakan Kode penyematan HTML pada opsi embed Canva')
                ->required()
                ->rows(2)
                ->columnSpanFull()
                ->dehydrateStateUsing(fn (?string $state) => self::cleanCanvaEmbed($state)),

            Toggle::make('is_published')
                ->label('Publikasikan')
                ->default(true),

        ]);
    }

    public static function table(Table $table): Table
    {
        return LearningContentsTable::configure($table);
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
            'index' => ListLearningContents::route('/'),
            'create' => CreateLearningContent::route('/create'),
            'edit' => EditLearningContent::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
