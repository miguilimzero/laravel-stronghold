<?php

namespace Miguilim\LaravelStronghold\Notifications;

use Illuminate\Bus\Queueable;
// use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SignInDetected extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(protected Request $request)
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     *
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param mixed $notifiable
     *
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $ipAddress = $this->request->ip();
        $userAgent = new \WhichBrowser\Parser($this->request->userAgent());
        $currentDate = '**' . now() . '**';

        $mail = (new MailMessage())
            ->subject(__('Sign In To Your Account Detected'))
            ->line(__('We just detected a new successful sign in to your account. We\'re sending you this e-mail to make sure it was you.'))
            ->line(' ');

        $mail = $mail->line(__('Request made at :date.', ['date' => $currentDate]));

        return $mail->line(__('User Agent: :agent.', ['agent' => "**{$userAgent->toString()}**"]))
            ->line(__('IP Address: :ip.', ['ip' => "**{$ipAddress}**"]))
            ->action(__('Secure Your Account'), route('profile.show'))
            ->line(__('If you did not initiate this request, please secure your account immediately.'));
    }
}
