<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Choice;

use InvalidArgumentException;

/**
 * Consumes input choices while constructing an immutable generation plan.
 *
 * @visibility root
 */
final class ByteChoices
{
    private int $position = 0;

    /**
     * Captures one stream for this plan construction only.
     */
    public function __construct(private readonly string $bytes)
    {
    }

    /**
     * Returns a choice, or null when a complete choice is unavailable.
     *
     * @throws InvalidArgumentException When the candidate count is not positive
     */
    public function index(int $count): ?int
    {
        $width = self::width($count);
        if (strlen($this->bytes) - $this->position < $width) {
            $this->position = strlen($this->bytes);
            return null;
        }
        $value = 0;
        for ($offset = $width - 1; $offset >= 0; --$offset) {
            $byte = ord($this->bytes[$this->position + $offset]);
            for ($bit = 7; $bit >= 0; --$bit) {
                $value = $value >= $count - $value ? $value - ($count - $value) : $value + $value;
                if (($byte & (1 << $bit)) !== 0) {
                    $value = $value === $count - 1 ? 0 : $value + 1;
                }
            }
        }
        $this->position += $width;
        return $value;
    }

    /**
     * Counts the little-endian bytes used by both encoder and decoder.
     *
     * @throws InvalidArgumentException When the candidate count is not positive
     */
    public static function width(int $count): int
    {
        if ($count < 1) {
            throw new InvalidArgumentException('A choice requires at least one candidate.');
        }
        $width = 1;
        $remaining = $count - 1;
        while ($remaining > 255) {
            ++$width;
            $remaining = intdiv($remaining, 256);
        }
        return $width;
    }
}
