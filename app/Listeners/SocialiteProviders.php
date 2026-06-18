<?php

namespace App\Listeners;

use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Apple\Provider as AppleProvider;

class SocialiteProviders
{
    public function handle(SocialiteWasCalled $event): void
    {
        $event->extendSocialite('apple', AppleProvider::class);
    }
}