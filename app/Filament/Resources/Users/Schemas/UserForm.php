<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('username'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->password(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('status')
                    ->required()
                    ->default('unverified'),
                TextInput::make('unej_role')
                    ->required()
                    ->default('umum'),
                Toggle::make('is_unej_verified')
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                DateTimePicker::make('email_verification_sent_at'),
                TextInput::make('provider'),
                TextInput::make('provider_id'),
                TextInput::make('avatar'),
                TextInput::make('failed_login_attempts')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('locked_until'),
            ]);
    }
}
