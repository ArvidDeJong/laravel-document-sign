<?php

namespace Darvis\Signer\Notifications;

use Darvis\Signer\Models\Signer;
use Darvis\Signer\Support\SignerConfig;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class SignatureRequested extends Notification
{
    public function __construct(
        protected Signer $signer,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $document = $this->signer->document;

        return (new MailMessage)
            ->subject(__('Signature requested: :title', ['title' => $document->title]))
            ->greeting(__('Hello :name,', ['name' => $this->signer->name]))
            ->line(__('You are invited to sign the document ":title".', ['title' => $document->title]))
            ->action(__('Review and sign'), $this->signingUrl())
            ->line(__('This link expires after :hours hours.', [
                'hours' => $this->config()->linkExpiresAfterHours(),
            ]));
    }

    /**
     * Resolved on use, not in the constructor, so a queued notification
     * serialises only the signer.
     */
    protected function config(): SignerConfig
    {
        return app(SignerConfig::class);
    }

    protected function signingUrl(): string
    {
        return URL::temporarySignedRoute(
            'signer.show',
            now()->addHours($this->config()->linkExpiresAfterHours()),
            ['signer' => $this->signer->uuid],
        );
    }
}
