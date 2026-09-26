<?php

declare(strict_types=1);

namespace Requirements\Config\Markdown;

use Requirements\Input\InvalidInputException;

/**
 * Checks that a static shields.io badge image shows the value and role its alt text and
 * title declare, so the rendered card cannot mislead.
 */
final class StaticBadge
{
    /**
     * Checks a badge image; images from other hosts or paths are not checked.
     *
     * @param string $url The image URL
     * @param string $field The field the badge sets
     * @param string $value The value in its alt text
     *
     * @throws InvalidInputException When the static image shows another role or value
     */
    public static function validate(string $url, string $field, string $value): void
    {
        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        if (parse_url($url, PHP_URL_HOST) !== 'img.shields.io' || preg_match('~^/badge/(kind|status|origin|category|label)-(.*)$~', $path, $match) !== 1) {
            return;
        }
        $parts = explode('-', str_replace('--', "\0", $match[2]));
        $message = str_replace(["\0", '_'], ['-', ' '], str_replace('__', "\1", $parts[0]));
        $message = str_replace("\1", '_', $message);
        if (count($parts) !== 2 || $match[1] !== $field || $message !== $value) {
            throw new InvalidInputException('Static badge image text and role must agree with its alt text and title.');
        }
    }
}
