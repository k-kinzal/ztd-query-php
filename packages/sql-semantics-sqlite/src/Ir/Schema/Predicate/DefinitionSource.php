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
     * @var non-empty-list<Identifier>
     */
    public readonly array $columns;

    /**
     * Supplies the checked declaration names in slot order.
     * @param non-empty-list<Identifier> $columns
     */
    public function __construct(array $columns)
    {
        $this->columns = Check::listOf($columns, Identifier::class, 'A definition source has a nonempty list of column names.', 1);
    }
}
