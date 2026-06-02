<?php

declare(strict_types=1);

namespace App\Mailer;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class SystemMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%app.mailer_from%')]
        private readonly string $fromAddress,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendText(string $to, string $subject, string $body): void
    {
        $message = (new Email())
            ->from($this->fromAddress)
            ->to($to)
            ->subject($subject)
            ->text($body);

        try {
            $this->mailer->send($message);
        } catch (\Throwable $exception) {
            $this->logger->error('Envoi email impossible.', [
                'to' => $to,
                'subject' => $subject,
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }
}
