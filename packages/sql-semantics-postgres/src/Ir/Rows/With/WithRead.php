<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows\With;

use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalResultColumn;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * An ordered projection of one local relation in the owning WITH scope.
 *
 * @visibility public
 * @example Reading a checked local field
 *     $field = new \SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalResultColumn(1, new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('selected'));
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithRead(2, [$field]))->definition // => 2
 */
final class WithRead
{
    use Snapshot;

    /**
     * @var non-empty-list<LocalResultColumn> Owned result columns in order
     */
    public readonly array $columns;

    /**
     * Establishes an exact source coordinate and checked result fields.
     * @param list<LocalResultColumn> $columns Result columns of the local read
     */
    public function __construct(public readonly int $definition, array $columns)
    {
        Check::input($definition >= 0, 'A WITH definition position must be nonnegative.');
        $checked = Check::listOf($columns, LocalResultColumn::class, 'A local WITH read needs a nonempty ordered result.', 1);
        Check::input(count($checked) <= 1024, 'A local WITH read can have at most 1024 columns.');
        $this->columns = $checked;
    }
}
