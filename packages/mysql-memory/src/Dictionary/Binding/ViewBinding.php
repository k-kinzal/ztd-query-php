<?php

declare(strict_types=1);

namespace MySqlMemory\Dictionary\Binding;

use MySqlMemory\Dictionary\View;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Statement\Declaration\Table;

/**
 * A view resolved against one persistent catalog, separate from its stored definition.
 * This value has no access to session state or the binding machinery.
 *
 * @visibility MySqlMemory
 */
final class ViewBinding
{
    /**
     * @param View $view The immutable stored definition
     * @param MySqlStatement $operation The retained query bound against the captured declarations
     * @param Table $declaration The view's public names and current output types
     * @param list<int> $positions The query output position of each public column
     * @param bool $valid Whether this view and its dependencies still resolve
     */
    public function __construct(public readonly View $view, public readonly MySqlStatement $operation, public readonly Table $declaration, public readonly array $positions, public readonly bool $valid)
    {
    }

    /**
     * Returns the query owned by this binding, including its current local references.
     */
    public function query(): \SqlSemantics\Statement\Query
    {
        \SqlSemantics\Diagnostic\Check::invariant($this->operation instanceof \SqlSemantics\Platform\MySql\Statement\View\CreateView, 'A stored view binding belongs to its CREATE VIEW definition.');
        return $this->operation->definition->query;
    }
}
