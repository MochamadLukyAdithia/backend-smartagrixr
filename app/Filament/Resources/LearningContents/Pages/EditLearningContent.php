<?php

namespace App\Filament\Resources\LearningContents\Pages;

use App\Filament\Resources\LearningContents\LearningContentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditLearningContent extends EditRecord
{
    protected static string $resource = LearningContentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
