<?php

declare(strict_types=1);

namespace SqlCatalog\Conformance;

use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextPattern;

/**
 * Decides whether a statement a program sent is one a catalogued shape describes.
 *
 * @visibility root
 */
final class PatternMatcher
{
    /**
     * Whether the shape describes the statement.
     */
    public function matches(TextPattern $pattern, string $sql): bool
    {
        $target = $this->normalize($sql);
        $text = $pattern->text();
        if ($text !== null) {
            return $this->normalize($text) === $target;
        }

        return preg_match($this->toRegex($pattern), $target) === 1;
    }

    /**
     * The expression a shape with gaps matches statements by.
     */
    public function toRegex(TextPattern $pattern): string
    {
        $expression = '';
        foreach ($pattern->segments as $segment) {
            $expression .= $segment instanceof LiteralText
                ? $this->quote($this->normalize($segment->text))
                : '.*?';
        }

        return '/^' . $expression . '$/su';
    }

    /**
     * A resolved run quoted so that the spaces in it match any run of whitespace.
     */
    public function quote(string $text): string
    {
        $parts = array_map(
            static fn (string $part): string => preg_quote($part, '/'),
            explode(' ', $text),
        );

        return implode('\\s*', $parts);
    }

    /**
     * The statement with its whitespace collapsed, so formatting does not matter.
     */
    public function normalize(string $sql): string
    {
        return trim(preg_replace('/\s+/', ' ', $sql) ?? $sql);
    }
}
