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
        #[Autowire('%env(bool:MAILER_PHP_MAIL)%')]
        private readonly bool $usePhpMail = false,
    ) {
    }

    public function sendText(string $to, string $subject, string $body): void
    {
        try {
            if ($this->usePhpMail) {
                $this->sendViaPhpMail($to, $subject, $body);

                return;
            }

            $message = (new Email())
                ->from($this->fromAddress)
                ->to($to)
                ->subject($subject)
                ->text($body);

            $this->mailer->send($message);
        } catch (\Throwable $exception) {
            $this->logger->error('Envoi email impossible.', [
                'to' => $to,
                'subject' => $subject,
                'via' => $this->usePhpMail ? 'php_mail' : 'symfony_mailer',
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    /**
     * Envoi via mail() de l’hébergement (mutualisé OVH).
     * L’expéditeur doit idéalement être une adresse créée sur l’hébergement.
     */
    private function sendViaPhpMail(string $to, string $subject, string $body): void
    {
        $headers = implode("\r\n", [
            'From: '.$this->fromAddress,
            'Reply-To: '.$this->fromAddress,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: extranet-17b',
        ]);

        $encodedSubject = '=?UTF-8?B?'.base64_encode($subject).'?=';

        $ok = @mail($to, $encodedSubject, $body, $headers);
        if (!$ok) {
            throw new \RuntimeException(sprintf(
                'Échec de mail() vers %s (vérifier l’autorisation d’envoi OVH et que %s existe sur l’hébergement).',
                $to,
                $this->fromAddress,
            ));
        }
    }
}
