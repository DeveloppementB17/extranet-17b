<?php

namespace App\Service\Monitor;

/**
 * Normalisation d'URL pour rapprocher extranet ↔ 17b-monitor.
 */
final class SiteUrlMatcher
{
    /**
     * @return list<string> Hôtes en minuscules (avec et sans www.)
     */
    public function hostVariants(string $url): array
    {
        $normalized = $this->normalize($url);
        if ($normalized === null) {
            return [];
        }

        $host = parse_url($normalized, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return [];
        }

        $host = strtolower(rtrim($host, '.'));
        $variants = [$host];
        if (str_starts_with($host, 'www.')) {
            $variants[] = substr($host, 4);
        } else {
            $variants[] = 'www.'.$host;
        }

        return array_values(array_unique($variants));
    }

    public function normalize(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $trimmed = trim($url);
        if ($trimmed === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $trimmed)) {
            $trimmed = 'https://'.$trimmed;
        }

        return rtrim($trimmed, '/');
    }

    public function hostsMatch(?string $left, ?string $right): bool
    {
        $leftVariants = $this->hostVariants($left ?? '');
        $rightVariants = $this->hostVariants($right ?? '');

        if ($leftVariants === [] || $rightVariants === []) {
            return false;
        }

        return count(array_intersect($leftVariants, $rightVariants)) > 0;
    }

    public function textContainsHost(?string $text, string $siteUrl): bool
    {
        if ($text === null || trim($text) === '') {
            return false;
        }

        $haystack = strtolower($text);
        foreach ($this->hostVariants($siteUrl) as $host) {
            if (str_contains($haystack, $host)) {
                return true;
            }
        }

        return false;
    }
}
