<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One projected expression of a selection or of a RETURNING clause with its optional alias.
 *
 * @visibility public
 * @example Reading a projected expression and its alias
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 AS one');
 *     $query->statement->columns[0]->alias?->value // => 'one'
 */
final class ResultColumn implements Node
{
    use Snapshot;

    /**
     * @param Scalar $expression The projected expression
     * @param Name|null $alias The output name
     */
    public function __construct(public readonly Scalar $expression, public readonly ?Name $alias = null)
    {
    }

    /**
     * Writes the expression and the alias.
     */
    public function render(Output $out): void
    {
        $out->node($this->expression);
        if ($this->alias !== null) {
            $out->keyword('AS')->name($this->alias, NameUse::Alias);
        }
    }
}
