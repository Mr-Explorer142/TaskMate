<?php

namespace App\Enums;

enum LinkPlatform: string
{
    case Github = 'github';
    case Linkedin = 'linkedin';
    case Portfolio = 'portfolio';
    case Behance = 'behance';
    case Dribbble = 'dribbble';
    case Twitter = 'twitter';
    case Youtube = 'youtube';
    case Website = 'website';
    case Other = 'other';

    /**
     * Detect the platform from a URL's host.
     */
    public static function detect(string $url): self
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        if ($host === '') {
            return self::Website;
        }

        return match (true) {
            self::hostMatches($host, 'github.com') => self::Github,
            self::hostMatches($host, 'linkedin.com') => self::Linkedin,
            self::hostMatches($host, 'behance.net') => self::Behance,
            self::hostMatches($host, 'dribbble.com') => self::Dribbble,
            self::hostMatches($host, 'x.com'),
            self::hostMatches($host, 'twitter.com') => self::Twitter,
            self::hostMatches($host, 'youtube.com'),
            self::hostMatches($host, 'youtu.be') => self::Youtube,
            default => self::Website,
        };
    }

    private static function hostMatches(string $host, string $domain): bool
    {
        return $host === $domain || str_ends_with($host, '.'.$domain);
    }
}
