<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\With;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\CommonTableFacts;
use SqlSemantics\Platform\PostgreSql\Statement\Query\CommonTables;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * WITH [RECURSIVE] and its common table expressions.
 *
 * Mirrors PostgreSQL's `WithClause`. The facts are derived by
 * PG-COMMON-TABLE-001.
 * Source: https://www.postgresql.org/docs/17/queries-with.html.
 *
 * @visibility public
 * @example Reading a recursive WITH clause
 *     $one = new \SqlSemantics\Platform\PostgreSql\Statement\Query\Select([new \SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')))]);
 *     $with = new \SqlSemantics\Platform\PostgreSql\Statement\Query\With\WithClause([new \SqlSemantics\Platform\PostgreSql\Statement\Query\With\CommonTableExpression(new \SqlSemantics\Statement\Identifier\Name('x'), $one)], true);
 *     [$with->recursive, count($with->tables)] // => [true, 1]
 */
final class WithClause implements CommonTables
{
    use Snapshot;

    /**
     * @var non-empty-list<CommonTableExpression> The common table expressions in written order
     */
    public readonly array $tables;

    /**
     * @param list<CommonTableExpression> $tables The common table expressions in written order; at least one
     * @param bool $recursive Whether RECURSIVE is written
     */
    public function __construct(array $tables, public readonly bool $recursive = false)
    {
        $this->tables = Check::listOf($tables, CommonTableExpression::class, 'A WITH clause holds at least one common table expression.', 1);
    }

    /**
     * Derives the common table expressions and answers the environment in which they are visible.
     */
    public function deriveCommonTables(Derivation $derivation, Environment $outer): Environment
    {
        return (new CommonTableFacts())->bind($this, $derivation, $outer);
    }

    /**
     * Writes WITH, RECURSIVE and the common table expressions.
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
