<?php

declare(strict_types=1);

namespace App\Document;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Valide les URL externes de documents (https uniquement + ressource joignable).
 */
final class ExternalDocumentUrlChecker
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return string|null Message d’erreur métier, ou null si l’URL est acceptable
     */
    public function validate(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return 'URL invalide.';
        }

        if (!filter_var($url, \FILTER_VALIDATE_URL)) {
            return 'URL invalide.';
        }

        $parsed = parse_url($url);
        $scheme = isset($parsed['scheme']) ? strtolower((string) $parsed['scheme']) : '';
        if ($scheme === 'http') {
            return 'Seules les URL en https:// sont acceptées.';
        }
        if ($scheme !== 'https') {
            return 'L’URL doit commencer par https://.';
        }

        $host = $parsed['host'] ?? null;
        if (!\is_string($host) || $host === '') {
            return 'URL invalide.';
        }

        try {
            $response = $this->httpClient->request('HEAD', $url, [
                'timeout' => 5,
                'max_redirects' => 3,
                'headers' => [
                    'User-Agent' => 'Extranet17b-DocumentUrlChecker/1.0',
                ],
            ]);
            $status = $response->getStatusCode();
        } catch (TransportExceptionInterface) {
            // Certains serveurs refusent HEAD : tentative GET allégée.
            try {
                $response = $this->httpClient->request('GET', $url, [
                    'timeout' => 5,
                    'max_redirects' => 3,
                    'headers' => [
                        'User-Agent' => 'Extranet17b-DocumentUrlChecker/1.0',
                        'Range' => 'bytes=0-0',
                    ],
                ]);
                $status = $response->getStatusCode();
            } catch (TransportExceptionInterface) {
                return 'Impossible de joindre cette URL. Vérifiez l’adresse et réessayez.';
            }
        }

        // 405/501 sur HEAD : on retente en GET partiel.
        if (\in_array($status, [405, 501], true)) {
            try {
                $response = $this->httpClient->request('GET', $url, [
                    'timeout' => 5,
                    'max_redirects' => 3,
                    'headers' => [
                        'User-Agent' => 'Extranet17b-DocumentUrlChecker/1.0',
                        'Range' => 'bytes=0-0',
                    ],
                ]);
                $status = $response->getStatusCode();
            } catch (TransportExceptionInterface) {
                return 'Impossible de joindre cette URL. Vérifiez l’adresse et réessayez.';
            }
        }

        if ($status >= 200 && $status < 400) {
            return null;
        }

        if ($status === 404) {
            return 'Cette URL renvoie une erreur 404 (page introuvable).';
        }

        return sprintf('Cette URL n’est pas accessible (code HTTP %d).', $status);
    }
}
