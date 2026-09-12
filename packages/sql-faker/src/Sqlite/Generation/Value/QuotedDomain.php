<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Value;

use Closure;
use Override;
use SqlFaker\Generation\Value\ValueDomain;

/**

 * One quoted scanner state: delimiter, optional prefix, doubling and optional backslash escapes.

 */
final class QuotedDomain implements ValueDomain
{
    /**
     * @param non-empty-list<string> $prefixes
     * @param non-empty-list<non-empty-string> $alphabet Unencoded characters sampled as complete atoms
     */
    public function __construct(
        private readonly string $quote,
        private readonly array $prefixes = [''],
        private readonly bool $backslash = false,
        private readonly int $minimum = 0,
        private readonly int $maximum = 255,
        private readonly array $alphabet = ['a', 'Z', '0', ' ', "'", '"', '`', '\\', 'é', '猫'],
        private readonly ?string $closing = null,
        private readonly bool $trailingSpace = true,
        private readonly bool $unicodeEscapes = false,
    ) {
    }

    /**

     * @param Closure(positive-int): int $choose

     */
    #[Override]
    public function choose(Closure $choose): string
    {
        $prefix = $this->prefixes[$choose(count($this->prefixes))];
        $length = $this->minimum + $choose(max(1, $this->maximum - $this->minimum + 1));
        $text = $prefix . $this->quote;
        $last = array_values(array_filter($this->alphabet, static fn (string $atom): bool => $atom !== ' '));
        for ($index = 0; $index < $length; ++$index) {
            $alphabet = !$this->trailingSpace && $index === $length - 1 && $last !== [] ? $last : $this->alphabet;
            $atom = $alphabet[$choose(count($alphabet))];
            $text .= $this->encode($atom);
        }
        return $text . ($this->closing ?? $this->quote);
    }

    /**

     * Encodes one complete character while constructing the value.

     */
    public function encode(string $atom): string
    {
        if ($atom === $this->quote && $this->closing === null) {
            return $atom . $atom;
        }
        return ($this->backslash || $this->unicodeEscapes) && $atom === '\\' ? '\\\\' : $atom;
    }

    /**

     * @return list<int>

     */
    #[Override]
    public function match(string $value, int $offset = 0): array
    {
        $ends = [];
        foreach ($this->prefixes as $prefix) {
            $open = $prefix . $this->quote;
            if (substr($value, $offset, strlen($open)) !== $open) {
                continue;
            }
            $end = $this->end($value, $offset + strlen($open));
            if ($end !== null) {
                $ends[] = $end;
            }
        }
        return array_values(array_unique($ends));
    }

    /**

     * Reads one scanner state until its first unescaped closing delimiter.

     */
    public function end(string $value, int $offset): ?int
    {
        $close = $this->closing ?? $this->quote;
        $count = 0;
        while (isset($value[$offset])) {
            $character = $value[$offset++];
            if ($character === "\0") {
                return null;
            }
            if ($character === $close) {
                if ($this->closing === null && ($value[$offset] ?? null) === $close) {
                    ++$offset;
                } else {
                    return $count >= $this->minimum ? $offset : null;
                }
            } elseif ($this->backslash && $character === '\\') {
                if (!isset($value[$offset]) || $value[$offset] === "\0") {
                    return null;
                }
                ++$offset;
            }
            ++$count;
        }
        return null;
    }
}
