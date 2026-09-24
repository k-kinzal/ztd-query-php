<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Sequence;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Relation\Identity;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The option rules PostgreSQL applies to CREATE SEQUENCE and ALTER SEQUENCE.
 * @visibility SqlSemantics
 */
final class SequenceInvariant
{
    /**
     * The value range of each sequence data type.
     */
    public const RANGES = ['smallint' => [-32768, 32767], 'integer' => [-2147483648, 2147483647], 'bigint' => [PHP_INT_MIN, PHP_INT_MAX]];

    /**
     * Sequences are PostgreSQL relations.
     * @throws InvalidStructure
     */
    public static function dialect(Origin $origin): void
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Sequence definitions require PostgreSQL.');
        }
    }

    /**
     * Validates each option and returns them by the sequence parameter they set; a parameter is set at most once.
     * @param list<Identity\SequenceValueChange|Identity\SequenceFlag|Identity\SequenceStorage|Identity\SetSequenceOwner|Identity\RestartIdentity> $options
     * @return array<string, Identity\SequenceValueChange|Identity\SequenceFlag|Identity\SequenceStorage|Identity\SetSequenceOwner|Identity\RestartIdentity>
     * @throws InvalidStructure
     */
    public static function options(array $options): array
    {
        Collections::alternatives($options, [Identity\SequenceValueChange::class, Identity\SequenceFlag::class, Identity\SequenceStorage::class, Identity\SetSequenceOwner::class, Identity\RestartIdentity::class]);
        $keyed = [];
        foreach ($options as $option) {
            $key = self::key($option);
            if (array_key_exists($key, $keyed)) {
                throw new InvalidStructure('A sequence option is given at most once.');
            }
            $keyed[$key] = $option;
        }
        $storage = $keyed['type'] ?? null;
        if ($storage instanceof Identity\SequenceStorage && !array_key_exists($storage->type->name, self::RANGES)) {
            throw new InvalidStructure('A sequence type is smallint, integer, or bigint.');
        }
        $owner = $keyed['owner'] ?? null;
        if ($owner instanceof Identity\SetSequenceOwner && $owner->column !== null && count($owner->column->parts) < 2) {
            throw new InvalidStructure('OWNED BY names a table column or NONE.');
        }
        $increment = self::value($keyed, Identity\SequenceAttribute::Increment);
        $cache = self::value($keyed, Identity\SequenceAttribute::Cache);
        if ($increment === 0 || ($cache !== null && $cache < 1)) {
            throw new InvalidStructure('A sequence increment is nonzero and its cache is positive.');
        }
        return $keyed;
    }

    /**
     * Names the sequence parameter an option sets; LOGGED and UNLOGGED are not sequence options.
     * @throws InvalidStructure
     */
    public static function key(Identity\SequenceValueChange|Identity\SequenceFlag|Identity\SequenceStorage|Identity\SetSequenceOwner|Identity\RestartIdentity $option): string
    {
        return match (true) {
            $option instanceof Identity\SequenceValueChange => $option->attribute->name,
            $option instanceof Identity\SequenceStorage => 'type',
            $option instanceof Identity\SetSequenceOwner => 'owner',
            $option instanceof Identity\RestartIdentity => 'restart',
            $option instanceof Identity\SequenceFlag => match ($option) {
                Identity\SequenceFlag::Cycle, Identity\SequenceFlag::NoCycle => 'cycle',
                Identity\SequenceFlag::NoMinValue => Identity\SequenceAttribute::MinValue->name,
                Identity\SequenceFlag::NoMaxValue => Identity\SequenceAttribute::MaxValue->name,
                Identity\SequenceFlag::Logged, Identity\SequenceFlag::Unlogged => throw new InvalidStructure('LOGGED and UNLOGGED change a sequence with SET, not as sequence options.'),
            },
        };
    }

    /**
     * Reads an explicit numeric option.
     * @param array<string, Identity\SequenceValueChange|Identity\SequenceFlag|Identity\SequenceStorage|Identity\SetSequenceOwner|Identity\RestartIdentity> $keyed
     * @throws InvalidStructure
     */
    public static function value(array $keyed, Identity\SequenceAttribute $attribute): ?int
    {
        $option = $keyed[$attribute->name] ?? null;
        return $option instanceof Identity\SequenceValueChange ? self::integer($option->value) : null;
    }

    /**
     * Converts an integer literal that fits a signed 64-bit integer.
     * @throws InvalidStructure
     */
    public static function integer(Literal $value): int
    {
        if (preg_match('/^([+-]?)0*([0-9]+)$/D', $value->text, $match) !== 1) {
            throw new InvalidStructure('A sequence value is an integer literal.');
        }
        $limit = $match[1] === '-' ? '9223372036854775808' : '9223372036854775807';
        if (strlen($match[2]) > 19 || (strlen($match[2]) === 19 && strcmp($match[2], $limit) > 0)) {
            throw new InvalidStructure('A sequence value fits a signed 64-bit integer.');
        }
        return $match[1] === '-' ? (int) ('-' . $match[2]) : (int) $match[2];
    }

    /**
     * Applies the bound checks that hold whatever the current sequence state: explicit bounds are ordered, lie within an explicit type,
     * and contain explicit START and RESTART values.
     * @param array<string, Identity\SequenceValueChange|Identity\SequenceFlag|Identity\SequenceStorage|Identity\SetSequenceOwner|Identity\RestartIdentity> $keyed
     * @throws InvalidStructure
     */
    public static function bounds(array $keyed, ?int $minimum, ?int $maximum): void
    {
        $storage = $keyed['type'] ?? null;
        [$low, $high] = $storage instanceof Identity\SequenceStorage ? self::RANGES[$storage->type->name] ?? self::RANGES['bigint'] : self::RANGES['bigint'];
        foreach ([$minimum, $maximum] as $bound) {
            if ($bound !== null && ($bound < $low || $bound > $high)) {
                throw new InvalidStructure('A sequence bound lies within the range of the sequence type.');
            }
        }
        $restart = $keyed['restart'] ?? null;
        $values = [self::value($keyed, Identity\SequenceAttribute::Start), $restart instanceof Identity\RestartIdentity && $restart->value !== null ? self::integer($restart->value) : null];
        if ($minimum !== null && $maximum !== null && $minimum >= $maximum) {
            throw new InvalidStructure('MINVALUE is less than MAXVALUE.');
        }
        foreach ($values as $value) {
            if ($value !== null && (($minimum !== null && $value < $minimum) || ($maximum !== null && $value > $maximum))) {
                throw new InvalidStructure('START and RESTART lie between MINVALUE and MAXVALUE.');
            }
        }
    }

    /**
     * Checks a new sequence with the defaults the server fills in: bounds follow the direction and type, and START defaults to the bound the sequence starts from.
     * @param array<string, Identity\SequenceValueChange|Identity\SequenceFlag|Identity\SequenceStorage|Identity\SetSequenceOwner|Identity\RestartIdentity> $keyed
     * @throws InvalidStructure
     */
    public static function creation(array $keyed): void
    {
        $storage = $keyed['type'] ?? null;
        [$low, $high] = $storage instanceof Identity\SequenceStorage ? self::RANGES[$storage->type->name] ?? self::RANGES['bigint'] : self::RANGES['bigint'];
        $ascending = (self::value($keyed, Identity\SequenceAttribute::Increment) ?? 1) > 0;
        $minimum = self::value($keyed, Identity\SequenceAttribute::MinValue) ?? ($ascending ? 1 : $low);
        $maximum = self::value($keyed, Identity\SequenceAttribute::MaxValue) ?? ($ascending ? $high : -1);
        self::bounds($keyed, $minimum, $maximum);
        $start = self::value($keyed, Identity\SequenceAttribute::Start) ?? ($ascending ? $minimum : $maximum);
        if ($start < $minimum || $start > $maximum) {
            throw new InvalidStructure('START lies between MINVALUE and MAXVALUE.');
        }
    }
}
