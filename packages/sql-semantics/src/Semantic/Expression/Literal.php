<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Expression;

use SqlSemantics\Core\Dialect;
use SqlSemantics\Core\Type\Nullability;
use SqlSemantics\Core\Type\TypeDescriptor;
use SqlSemantics\Semantic\Type\Undetermined;
use SqlSemantics\Semantic\Type\UnknownReason;

/**
 * A scalar constant; SQL quoting is derived from its value.
 * @example Reading semantic relationships
 *     $literal = new \SqlSemantics\Semantic\Expression\Literal(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite, "it's text");
 *     $literal->toString() // => "'it''s text'"
 *
 * @visibility public
 */
final class Literal
{
    /**
     * The result type or the explicit reason no type can be established.
     */
    public readonly TypeDescriptor|Undetermined $type;
    /**
     * The conservative NULL fact at this evaluation stage.
     */
    public readonly Nullability $nullability;

    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly Dialect $dialect, public readonly int|string|bool|null $value)
    {
        assert(!is_string($value) || (!str_contains($value, "\0") && !str_contains($value, '\\')), 'NUL and backslash string literals require an explicit dialect literal form.');
        $this->type = match (true) {
            $value === null => new Undetermined(UnknownReason::NullLiteral),
            is_int($value) => new TypeDescriptor($dialect, $dialect->platform()->types()->integer(ltrim((string) $value, '-'))),
            is_bool($value) => $dialect->platform()->types()->boolean(),
            is_string($value) => new TypeDescriptor($dialect, 'text'),
        };
        $this->nullability = $value === null ? Nullability::AlwaysNull : Nullability::NotNull;
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        return match (true) {
            $this->value === null => 'NULL',
            is_bool($this->value) => $this->value ? 'TRUE' : 'FALSE',
            is_int($this->value) => (string) $this->value,
            is_string($this->value) => "'" . str_replace("'", "''", $this->value) . "'",
        };
    }
}
