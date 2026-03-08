<?php

declare(strict_types=1);

namespace Fuzz\Support;

/**
 * Centralizes process-wide adjustments required by the php-fuzzer entry scripts.
 */
final class FuzzerRuntime
{
    /**
     * Masks E_WARNING so php-fuzzer's integer mutator does not abort the target
     * process under newer PHP runtimes.
     */
    public static function suppressPhpFuzzerWarnings(): void
    {
        error_reporting(E_ALL & ~E_WARNING);
    }

    /**
     * Clears php-fuzzer's alarm before shutdown so container teardown can finish.
     */
    public static function registerPcntlAlarmReset(): void
    {
        register_shutdown_function(static function (): void {
            if (function_exists('pcntl_alarm')) {
                pcntl_alarm(0);
            }
        });
    }

    /**
     * Reads an integer environment variable or falls back to the provided default.
     */
    public static function intEnv(string $name, int $default): int
    {
        $value = getenv($name);

        if (!is_string($value)) {
            return $default;
        }

        return (int) $value;
    }
}
