<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * A catalog type constrained by type modifiers, such as `numeric(10,2)` or `character varying(5)`.
 *
 * Source: https://www.postgresql.org/docs/17/datatype.html.
 *
 * @visibility public
 * @example Reading the type of a typed constant with modifiers
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("SELECT numeric(10, 2) '1.5'");
 *     $query->field(0)->type->descriptor->name() // => 'numeric(10,2)'
 */
final class Parameterized implements TypeDescriptor
{
    use Snapshot;

    /**
     * @var non-empty-list<int> The modifier values in the order the type takes them
     */
    public readonly array $modifiers;

    /**
     * @param Builtin $base The catalog type
     * @param int ...$modifiers The modifier values in the order the type takes them; at least one
     */
    public function __construct(public readonly Builtin $base, int ...$modifiers)
    {
        $values = array_values($modifiers);
        Check::input($values !== [], 'A parameterized type has at least one modifier.');
        $this->modifiers = $values;
    }

    /**
     * Names the type as the server displays it, modifiers included.
     */
    public function name(): string
    {
        $modifiers = '(' . implode(',', $this->modifiers) . ')';

        return match ($this->base) {
            Builtin::Time, Builtin::Timetz, Builtin::Timestamp, Builtin::Timestamptz => preg_replace('/\A(\w+)/', '$1' . $modifiers, $this->base->name()) ?? $this->base->name(),
            default => $this->base->name() . $modifiers,
        };
    }
}
