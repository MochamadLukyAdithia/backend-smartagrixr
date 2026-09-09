<?php

namespace App\Filament\Resources\LearningContents\Pages;

use App\Filament\Resources\LearningContents\LearningContentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLearningContents extends ListRecords
{
    protected static string $resource = LearningContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
