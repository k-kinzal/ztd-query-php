<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable\Catalog;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Snapshot;

/**
 * The system variables of one release, read from the catalog generated from a server of the release.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html.
 *
 * @visibility public
 * @example Finding a variable of 5.7
 *     \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables::of(\SqlSemantics\Contract\GrammarRelease::MySql5744)->find('sql_mode')?->reach // => \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Reach::Both
 */
final class SystemVariables
{
    use Snapshot;

    private const REACHES = ['Global' => Reach::Global, 'Session' => Reach::Session, 'Both' => Reach::Both];

    private const SHAPES = ['Boolean' => ValueShape::Boolean, 'Integer' => ValueShape::Integer, 'Unsigned' => ValueShape::Unsigned, 'Double' => ValueShape::Double, 'Text' => ValueShape::Text];

    /**
     * @var array<string, self>
     */
    private static array $releases = [];

    /**
     * @param array<string, Definition> $definitions The variables by lower-case name
     */
    public function __construct(public readonly array $definitions)
    {
    }

    /**
     * Answers the variables of a release; a release without a catalog has those of 8.4.
     */
    public static function of(GrammarRelease $release): self
    {
        if (!isset(self::$releases[$release->value])) {
            $directory = dirname(__DIR__, 4) . '/resources/variables/';
            $file = is_file($directory . $release->value . '.php') ? $directory . $release->value . '.php' : $directory . GrammarRelease::MySql847->value . '.php';
            /** @var list<array{string, string, string, string|int|null, string, int|null, int|string|null, int, int, int, bool, string}> $entries */
            $entries = require $file;
            $definitions = [];
            foreach ($entries as [$name, $reach, $shape, $default, $writability, $minimum, $maximum, $field, $length, $decimals, $unsigned, $collation]) {
                $unsignedShape = $shape === 'Unsigned';
                $definitions[$name] = new Definition($name, self::REACHES[$reach] ?? Reach::Both, self::SHAPES[$shape] ?? ValueShape::Text, $unsignedShape && $default !== null ? self::bits($default) : $default, self::writability($writability), $minimum, $maximum === null ? null : (int) self::bits($maximum), self::domain($field, $length, $decimals, $unsigned, $collation));
            }
            self::$releases[$release->value] = new self($definitions);
        }

        return self::$releases[$release->value];
    }

    /**
     * Answers the 64 bits of an unsigned value a catalog entry records: a value beyond the largest signed integer is written as its decimal digits, and read as the negative integer of the same bits.
     *
     * @example The largest unsigned value
     *     \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables::bits('18446744073709551615') // => -1
     */
    public static function bits(int|string $value): int|string
    {
        if (is_int($value) || preg_match('/\A[0-9]{19,20}\z/', $value) !== 1 || strcmp(str_pad($value, 20, '0', STR_PAD_LEFT), '09223372036854775807') <= 0) {
            return $value;
        }

        $tens = (int) substr($value, 0, -1) - 1844674407370955160;

        return $tens * 10 + (int) substr($value, -1) - 16;
    }

    /**
     * Reads the writability a catalog entry records: No, Session or Yes for the read-only scopes.
     */
    public static function writability(string $readOnly): Writability
    {
        return match ($readOnly) {
            'Yes' => Writability::ReadOnly,
            'Session' => Writability::GlobalOnly,
            default => Writability::Writable,
        };
    }

    /**
     * Builds the type of a read from the metadata a catalog entry records.
     */
    public static function domain(int $code, int $length, int $decimals, bool $unsigned, string $collation): Domain
    {
        $field = Field::tryFrom($code) ?? Field::VarString;

        return match (true) {
            $field->integral() => Domain::integer($field, $length, $unsigned),
            $field === Field::Double || $field === Field::Float => Domain::double($length, $decimals),
            $field === Field::NewDecimal => new Domain(Kind::Decimal, $field, $length, $decimals, $unsigned, null, [], Coercibility::Numeric),
            default => Domain::string($length, Collation::named($collation) ?? Collation::binary(), $field, Coercibility::SystemConstant),
        };
    }

    /**
     * Finds a variable by name, case-insensitively.
     */
    public function find(string $name): ?Definition
    {
        return $this->definitions[strtolower($name)] ?? null;
    }
}
