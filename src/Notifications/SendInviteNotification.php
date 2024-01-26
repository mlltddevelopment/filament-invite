<?php

namespace Concept7\FilamentInvite\Notifications;

use App\Mail\SendInviteMail as MailSendInviteMail;
use Concept7\FilamentInvite\Contracts\SendInviteMail;
use Concept7\FilamentInvite\Models\Invite;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SendInviteNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(private Invite $invite)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): SendInviteMail
    {
        return new MailSendInviteMail(user: $notifiable, url: $this->getLink());
    }

    public function getLink(): string
    {
        return route('filament.invite.accept', [
            'acceptId' => $this->invite->id,
            'hash' => $this->invite->token,
        ]);
    }
}
