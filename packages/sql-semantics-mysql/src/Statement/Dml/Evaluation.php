<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Query\From\JoinedInput;
use SqlSemantics\Platform\MySql\Rules\Query\Projection;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Star;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * DO: evaluates expressions and returns no rows.
 *
 * Rule: MYSQL-DO-001. The expressions are derived as a select list without
 * input relations (MYSQL-PROJECTION-001), so a star is reported as a star
 * without tables; MySQL 5.7 and later accept select items with aliases,
 * MySQL 5.6 an expression list. The statement returns no rows. Terminates:
 * one pass over the items. Source: https://dev.mysql.com/doc/refman/8.4/en/do.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the expressions of DO
 *     $do = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('DO SLEEP(1), @a := 2');
 *     [count($do->statement->items), $do->facts->output, $do->toString()] // => [2, null, 'DO SLEEP(1), @a := 2']
 */
final class Evaluation implements Statement
{
    use Snapshot;

    /**
     * @var list<SelectExpression|Star|TableWildcard> The items in written order
     */
    public readonly array $items;

    /**
     * @param list<SelectExpression|Star|TableWildcard> $items The items; at least one
     * @throws InvalidConstruction When there is no item, or an item is of no select item class
     */
    public function __construct(array $items)
    {
        $list = [];
        foreach (Check::listOf($items, Node::class, 'DO evaluates at least one expression.', 1) as $item) {
            Check::input($item instanceof SelectExpression || $item instanceof Star || $item instanceof TableWildcard, 'An item of DO is an expression or a star.');
            $list[] = $item;
        }
        $this->items = $list;
    }

    /**
     * Derives the items where no relation is visible; the statement returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new Projection())->items($this->items, $derivation, $derivation->environment(), new JoinedInput(new RelationFact(new RowShape([])), [], []));
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('DO')->list($this->items);
    }
}
