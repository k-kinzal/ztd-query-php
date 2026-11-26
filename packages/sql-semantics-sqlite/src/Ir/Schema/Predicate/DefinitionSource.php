<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Predicate;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * The render-time column names of an owning table definition.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DefinitionSource
{
    use Snapshot;

    /**
     * @var array<int, Identifier>
     */
    public readonly array $columns;

    /**
     * Supplies the checked declaration names at their bound slots.
     * @param array<array-key, object|array<array-key, object|scalar|null>|scalar|null> $columns
     */
    public function __construct(array $columns)
    {
        $bindings = [];
        foreach ($columns as $slot => $name) {
            Check::input(is_int($slot) && $slot >= 0 && $name instanceof Identifier, 'A definition source needs identifiers at valid column slots.');
            $bindings[$slot] = $name;
        }
        $this->columns = $bindings;
    }
}
