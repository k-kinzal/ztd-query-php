<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One projected expression of a select list with its optional alias.
 *
 * Slice of the query family: completed or replaced by that family.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 *
 * @visibility public
 * @example Reading a projected expression and its alias
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a AS total FROM t');
 *     [$query->statement->items[0]->expression->name->value, $query->statement->items[0]->alias?->value] // => ['a', 'total']
 */
final class SelectExpression implements SelectItem
{
    use Snapshot;

    /**
     * @param Scalar $expression The projected expression
     * @param Name|null $alias The output name given with AS
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
