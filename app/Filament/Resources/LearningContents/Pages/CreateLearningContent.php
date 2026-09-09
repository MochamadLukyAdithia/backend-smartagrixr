<?php

namespace App\Filament\Resources\LearningContents\Pages;

use App\Filament\Resources\LearningContents\LearningContentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLearningContent extends CreateRecord
{
    protected static string $resource = LearningContentResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        return $data;
    }
}
