<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

use Override;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A time or timestamp declaration with explicit precision and time zone behavior.
 * @visibility public
 */
final class TemporalStorage implements TypeIdentity
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly BuiltinIdentity $base,
        public readonly ?Numeric\NumericParameter $precision = null,
        public readonly TimeZoneMode $timeZone = TimeZoneMode::Unspecified,
    ) {
        if (!in_array($base, [BuiltinIdentity::Time, BuiltinIdentity::Timestamp, BuiltinIdentity::Datetime], true)) {
            throw new InvalidStructure('Temporal precision requires a time or timestamp type.');
        }
    }

    #[Override]
    public function name(): string
    {
        return match ($this->base) {
            BuiltinIdentity::Time => $this->timeZone === TimeZoneMode::With ? 'timetz' : 'time',
            BuiltinIdentity::Timestamp => $this->timeZone === TimeZoneMode::With ? 'timestamptz' : 'timestamp',
            default => $this->base->value,
        };
    }
}
