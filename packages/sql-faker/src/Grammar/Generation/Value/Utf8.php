<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Value;

/**
 * Reads UTF-8 scalar encodings, including the ASCII and three-byte subsets.
 */
final class Utf8
{
    /**
     * Rejects truncation, isolated continuation bytes, overlong forms, surrogates and out-of-range scalars.
     */
    public function valid(string $bytes, int $maximumWidth = 4): bool
    {
        for ($offset = 0; $offset < strlen($bytes);) {
            $first = ord($bytes[$offset++]);
            if ($first < 128) {
                continue;
            }
            $width = $this->width($first);
            if ($width === 0 || $width > $maximumWidth || $offset + $width - 1 > strlen($bytes)) {
                return false;
            }
            $second = ord($bytes[$offset]);
            $minimum = match ($first) {
                224 => 160, 240 => 144, default => 128
            };
            $maximum = match ($first) {
                237 => 159, 244 => 143, default => 191
            };
            if ($second < $minimum || $second > $maximum) {
                return false;
            }
            for ($index = 1; $index < $width; ++$index) {
                $continuation = ord($bytes[$offset++]);
                if ($continuation < 128 || $continuation > 191) {
                    return false;
                }
            }
        }
        return true;
    }
    /**
     * Classifies a UTF-8 leading byte; zero denotes an invalid lead or a continuation byte.
     * @param int<0, 255> $lead
     */
    public function width(int $lead): int
    {
        return match (true) {
            $lead < 128 => 1,
            $lead < 194 => 0,
            $lead < 224 => 2,
            $lead < 240 => 3,
            $lead <= 244 => 4,
            default => 0,
        };
    }
}
