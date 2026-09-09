<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Lexical;

use RuntimeException;

/**
 * Consumes a declared registration region completely; unknown declarations cannot disappear silently.
 */
final class RegistrationTable
{
    /**
     * Removes C comments while retaining comment-looking characters inside quoted strings.
     */
    public function withoutComments(string $source): string
    {
        return preg_replace_callback(
            '~"(?:\\\\.|[^"\\\\])*"|/\*.*?\*/|//[^\r\n]*~s',
            static fn (array $match): string => str_starts_with($match[0], '"') ? $match[0] : ' ',
            $source
        ) ?? $source;
    }

    /**
     * Extracts one named C initializer, excluding declarations outside that table.
     * @throws RuntimeException When a named initializer is missing or incomplete
     */
    public function body(string $source, string $name): string
    {
        $source = $this->withoutComments($source);
        if (preg_match('~\b' . preg_quote($name, '~') . '\s*\[\s*\]\s*=\s*\{(.*?)\}\s*;~s', $source, $match) !== 1) {
            throw new RuntimeException('Registration table was not found: ' . $name);
        }
        return $match[1];
    }

    /**
     * Returns matched rows only if every non-separator byte in the region was understood.
     * @return list<list<string>>
     * @throws RuntimeException When any declaration uses an unsupported form
     */
    public function entries(string $source, string $pattern): array
    {
        $source = $this->withoutComments($source);
        preg_match_all($pattern, $source, $matches, PREG_SET_ORDER);
        $remaining = preg_replace($pattern, '', $source);
        if ($remaining === null || trim($remaining, " \t\r\n,") !== '') {
            throw new RuntimeException('Unsupported registration declaration: ' . substr(trim($remaining ?? $source), 0, 100));
        }
        return array_map(static fn (array $match): array => array_values($match), $matches);
    }
}
