<?php

namespace Concept7\FilamentInvite\Models\Traits;

use App\Models\User;
use Concept7\FilamentInvite\Jobs\SendInvite;
use Concept7\FilamentInvite\Models\Invite;
use Concept7\FilamentInvite\Notifications\SendInviteNotification;

trait Invitable
{
    public abstract function afterInviteAcceptanceRedirectTo();
    
    public static function bootInvitable()
    {
        static::created(function (User $user) {
            SendInvite::dispatch($user);
        });
    }

    public function sendInviteNotification(Invite $invite)
    {
        return $this->notify(new SendInviteNotification($invite));
    }
}
