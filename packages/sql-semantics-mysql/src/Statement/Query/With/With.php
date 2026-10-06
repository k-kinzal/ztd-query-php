<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\With;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\CommonTables;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * A WITH clause: common table expressions and the RECURSIVE marker.
 *
 * The query, UPDATE or DELETE that holds the clause binds its expressions
 * (MYSQL-WITH-001). Source: https://dev.mysql.com/doc/refman/8.4/en/with.html.
 *
 * @visibility public
 * @example Reading a recursive WITH clause
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('WITH RECURSIVE c AS (SELECT 1 AS n UNION ALL SELECT n + 1 FROM c WHERE n < 3) SELECT n FROM c');
 *     [$query->statement->with->recursive, count($query->statement->with->tables)] // => [true, 1]
 * @example Refusing a WITH clause without tables
 *     new \SqlSemantics\Platform\MySql\Statement\Query\With\With(false, []) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class With implements WithClause
{
    use Snapshot;

    /**
     * @var non-empty-list<CommonTableExpression> The common table expressions in written order
     */
    public readonly array $tables;

    /**
     * @param bool $recursive Whether RECURSIVE is written
     * @param list<CommonTableExpression> $tables The common table expressions in written order; at least one
     */
    public function __construct(public readonly bool $recursive, array $tables)
    {
        $this->tables = Check::listOf($tables, CommonTableExpression::class, 'A WITH clause defines at least one common table expression.', 1);
    }

    /**
     * Derives the common table expressions and answers the scope that sees them (MYSQL-WITH-001).
     */
    public function bind(Derivation $derivation, Environment $outer): Environment
    {
        return (new CommonTables())->bind($this, $derivation, $outer);
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('WITH');
        if ($this->recursive) {
            $out->keyword('RECURSIVE');
        }
        $out->list($this->tables);
    }
}
