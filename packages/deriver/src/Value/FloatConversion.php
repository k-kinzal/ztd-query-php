<?php

declare(strict_types=1);

namespace Deriver\Value;

use Closure;

/**
 * Converts floats to strings under the captured target precision instead of the host setting.
 * @visibility root
 */
final class FloatConversion
{
    /**
     * @param int|null $precision Target value of the precision directive; null when it was not captured
     */
    public function __construct(public readonly ?int $precision = null)
    {
    }

    /**
     * Produces the PHP 8.3 string form of a float.
     * @param float $value Float operand
     * @return string|null String form; null when the target precision is unknown
     */
    public function string(float $value): ?string
    {
        if ($this->precision === null) {
            return null;
        }
        if (is_nan($value)) {
            return substr('NAN', 0, $this->precision === -1 ? 3 : max(1, $this->precision));
        }
        return $this->within(static fn (): string => (string) $value);
    }

    /**
     * Runs a host operation that converts floats to strings under the captured target precision.
     * @template T
     * @param Closure(): T $operation Operation without application code
     * @return T Operation result
     */
    public function within(Closure $operation): mixed
    {
        if ($this->precision === null) {
            return $operation();
        }
        $host = ini_get('precision');
        ini_set('precision', (string) $this->precision);
        try {
            return $operation();
        } finally {
            ini_set('precision', $host);
        }
    }
}
