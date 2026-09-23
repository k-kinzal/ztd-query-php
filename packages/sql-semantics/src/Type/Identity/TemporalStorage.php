<?php

declare(strict_types=1);

namespace SqlSemantics\Type\Identity;

use Override;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A time or timestamp declaration with explicit precision and time zone behavior.
 * @visibility public
 * @example Retaining temporal precision without evaluating it
 *     $type = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(x timetz("3"))')->tables[0]->columns[0]->type;
 *     $type->identity->precision->name // => '3'
 */
final class TemporalStorage implements TypeIdentity
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly BuiltinIdentity $base,
        public readonly Numeric\NumericParameter|\SqlSemantics\Type\Modifier\TextParameter|\SqlSemantics\Type\Modifier\IdentifierParameter|\SqlSemantics\Type\Modifier\NegatedParameter|null $precision = null,
        public readonly TimeZoneMode $timeZone = TimeZoneMode::Unspecified,
    ) {
        if ($precision !== null && (!$precision instanceof Numeric\NumericParameter || !ctype_digit(str_replace('_', '', $precision->spelling))) && ($timeZone !== TimeZoneMode::With || $base === BuiltinIdentity::Datetime)) {
            throw new InvalidStructure('Nonnumeric temporal precision requires a PostgreSQL timetz or timestamptz type reference.');
        }
        if (!in_array($base, [BuiltinIdentity::Time, BuiltinIdentity::Timestamp, BuiltinIdentity::Datetime], true)) {
            throw new InvalidStructure('Temporal precision requires a time or timestamp type.');
        }
    }

    /**
     * Returns the canonical database type name represented by this identity.
     */
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
