<?php

declare(strict_types=1);

namespace Deriver\Evaluation\Control;

use Deriver\Query\ResourceLimits;

/**
 * Checks runtime interruptions while reserving memory for an explicit residual result.
 * @visibility root
 */
final class Resources
{
    /**
     * Baseline used memory, captured before reserving termination storage.
     */
    private readonly int $baseline;
    /**
     * Monotonic query start in nanoseconds.
     */
    private readonly int|float $started;
    /**
     * Storage released before constructing a resource interruption report.
     */
    private string $reserve;
    /**
     * First interruption reason, permanent for this query.
     */
    private ?string $stopped = null;
    /**
     * Host PHP allocator limit, when finite.
     */
    private readonly ?int $hostLimit;
    /**
     * Conservative host frame limit, including debugger termination headroom.
     */
    private readonly int $frameLimit;

    /**
     * @param ResourceLimits $limits Explicit runtime policy
     */
    public function __construct(public readonly ResourceLimits $limits)
    {
        $debugMode = getenv('XDEBUG_MODE');
        $this->frameLimit = self::stackLimit($limits->stackFrames, ini_get('xdebug.max_nesting_level'), $debugMode === false ? ini_get('xdebug.mode') : $debugMode);
        $this->baseline = memory_get_usage();
        $this->started = hrtime(true);
        $this->hostLimit = self::memoryLimit(ini_get('memory_limit'));
        $capacity = $this->hostLimit === null ? 262144 : min(262144, intdiv(max(0, $this->hostLimit - memory_get_usage(true)), 2));
        $this->reserve = str_repeat(' ', $capacity);
    }

    /**
     * Returns the first resource interruption and releases termination capacity.
     * @param int $additionalBytes Anticipated allocation before the next operation
     * @param bool $call Whether to check host stack capacity before entering another callable
     * @return string|null CANCELLED, MEMORY_LIMIT, TIME_LIMIT, STACK_LIMIT, or no interruption
     * @phpstan-impure
     */
    public function reason(int $additionalBytes = 0, bool $call = false): ?string
    {
        if ($this->stopped !== null) {
            return $this->stopped;
        }
        if ($this->limits->cancellation?->isRequested() === true) {
            $this->stopped = 'CANCELLED';
        } elseif ($additionalBytes >= $this->limits->memoryBytes - strlen($this->reserve) - (memory_get_usage() - $this->baseline) || ($this->hostLimit !== null && $additionalBytes >= $this->hostLimit - memory_get_usage(true) - strlen($this->reserve))) {
            $this->stopped = 'MEMORY_LIMIT';
        } elseif ($this->limits->seconds > 0.0 && (hrtime(true) - $this->started) / 1e9 >= $this->limits->seconds) {
            $this->stopped = 'TIME_LIMIT';
        } elseif ($call && count(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->frameLimit)) >= $this->frameLimit) {
            $this->stopped = 'STACK_LIMIT';
        }
        if ($this->stopped !== null) {
            $this->reserve = '';
        }
        return $this->stopped;
    }
    /**
     * Leaves space for residual construction when an active debugger limits nesting.
     * @param int $configured Caller-selected frame limit
     * @param string|false $debugLimit Captured debugger nesting setting
     * @param string|false $debugMode Captured effective debugger mode
     * @return int Effective host stack limit
     */
    public static function stackLimit(int $configured, string|false $debugLimit, string|false $debugMode): int
    {
        if ($debugMode === false || $debugMode === '' || $debugMode === 'off' || $debugLimit === false || !ctype_digit($debugLimit) || (int) $debugLimit < 1) {
            return $configured;
        }
        return min($configured, max(1, (int) $debugLimit - 64));
    }

    /**
     * Interprets PHP's allocator limit using its K, M, and G suffixes without overflow.
     * @param string $setting Captured memory_limit setting
     * @return int|null Absolute byte limit, or no finite host limit
     */
    public static function memoryLimit(string $setting): ?int
    {
        if (preg_match('/\A([0-9]+)\s*([KMG]?)\z/i', trim($setting), $parts) !== 1) {
            return null;
        }
        $factor = match (strtoupper($parts[2])) {
            'K' => 1024, 'M' => 1048576, 'G' => 1073741824, default => 1,
        };
        $amount = (int) $parts[1];
        return $amount > intdiv(PHP_INT_MAX, $factor) ? PHP_INT_MAX : $amount * $factor;
    }
}
