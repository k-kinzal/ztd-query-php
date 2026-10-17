<?php

declare(strict_types=1);

namespace Deriver\Source\Discovery;

use Deriver\Exception\InvalidInputException;

/**
 * Matches complete root-relative paths with *, ? and recursive ** wildcards.
 * @visibility root
 */
final class PathPattern
{
    private readonly string $expression;

    /**
     * @throws InvalidInputException If the pattern is empty or not root-relative
     */
    public function __construct(string $pattern)
    {
        $pattern = str_replace('\\', '/', $pattern);
        while (str_starts_with($pattern, './')) {
            $pattern = substr($pattern, 2);
        }
        if ($pattern === '' || str_starts_with($pattern, '/') || str_contains($pattern, ':') || in_array('..', explode('/', $pattern), true)) {
            throw new InvalidInputException('Finder patterns must be nonempty root-relative paths: ' . $pattern);
        }
        $this->expression = '~^' . strtr(preg_quote($pattern, '~'), [
            '\*\*/' => '(?:[^/]+/)*',
            '\*\*' => '.*',
            '\*' => '[^/]*',
            '\?' => '[^/]',
        ]) . '$~D';
    }

    /**
     * Matches a relative path; a trailing slash represents a directory.
     */
    public function matches(string $path): bool
    {
        return preg_match($this->expression, $path) === 1;
    }
    /**
     * Tests whether any pattern selects a path.
     * @param list<self> $patterns Accepted patterns
     */
    public static function matchesAny(array $patterns, string $path): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern->matches($path)) {
                return true;
            }
        }
        return false;
    }
}
