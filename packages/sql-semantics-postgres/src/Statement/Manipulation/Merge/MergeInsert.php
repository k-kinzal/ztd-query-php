<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Assignment\ColumnTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Overriding;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * The action INSERT ... VALUES of a MERGE WHEN clause: one row is inserted from the source row.
 *
 * Mirrors a `MergeWhenClause` with `CMD_INSERT`, its column list, override
 * and values. A value may be DEFAULT.
 * Source: https://www.postgresql.org/docs/17/sql-merge.html.
 *
 * @visibility public
 * @example Reading an insert action
 *     $merge = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('MERGE INTO t USING u ON t.a = u.a WHEN NOT MATCHED THEN INSERT (a) OVERRIDING USER VALUE VALUES (u.a)');
 *     [count($merge->statement->clauses[0]->action->values), $merge->toString()] // => [1, 'MERGE INTO t USING u ON t.a = u.a WHEN NOT MATCHED THEN INSERT (a) OVERRIDING USER VALUE VALUES (u.a)']
 * @example Refusing an insert without value
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Merge\MergeInsert([], null, []) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class MergeInsert implements Node
{
    use Snapshot;

    /**
     * @var list<ColumnTarget> The column list in written order
     */
    public readonly array $columns;

    /**
     * @var non-empty-list<Scalar> The values in written order
     */
    public readonly array $values;

    /**
     * @param list<ColumnTarget> $columns The column list in written order
     * @param Overriding|null $overriding Whose values identity columns receive
     * @param list<Scalar> $values The values in written order; at least one
     *
     * @throws InvalidConstruction When there is no value, or a list holds a foreign item
     */
    public function __construct(array $columns, public readonly ?Overriding $overriding, array $values)
    {
        $this->columns = Check::listOf($columns, ColumnTarget::class, 'The column list of an INSERT holds column targets.');
        $this->values = Check::listOf($values, Scalar::class, 'An INSERT action holds at least one value.', 1);
    }

    /**
     * Writes INSERT, the columns, the override and VALUES.
     */
    public function render(Output $out): void
    {
        $out->keyword('INSERT');
        if ($this->columns !== []) {
            $out->symbol('(')->list($this->columns)->symbol(')');
        }
        if ($this->overriding !== null) {
            $out->keyword('OVERRIDING', $this->overriding->value, 'VALUE');
        }
        $out->keyword('VALUES')->symbol('(')->list($this->values)->symbol(')');
    }
}
