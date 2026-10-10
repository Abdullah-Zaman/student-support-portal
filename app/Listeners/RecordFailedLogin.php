<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Database\Eloquent\Model;
use Laravel\Fortify\Fortify;

class RecordFailedLogin
{
    public function handle(Failed $event): void
    {
        activity()
            ->causedBy($event->user instanceof Model ? $event->user : null)
            ->withProperties(['email' => $event->credentials[Fortify::username()] ?? null])
            ->log('login_failed');
    }
}
