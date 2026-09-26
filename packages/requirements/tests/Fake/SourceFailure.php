<?php

declare(strict_types=1);

namespace Tests\Fake;

use RuntimeException;

/**
 * Runs a source read that is expected to fail, so a test can look at what the failure left behind.
 */
final class SourceFailure
{
    /**
     * Runs a read and returns why it failed.
     *
     * @param callable(): mixed $read The read
     *
     * @return string The failure message, or an empty string when the read succeeded
     */
    public static function message(callable $read): string
    {
        try {
            $read();
        } catch (RuntimeException $error) {
            return $error->getMessage();
        }
        return '';
    }
}
