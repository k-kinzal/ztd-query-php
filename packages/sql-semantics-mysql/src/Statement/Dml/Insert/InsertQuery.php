<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Insert;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Dml\InsertFacts;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Explainable;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;

/**
 * INSERT or REPLACE of the rows of a query (SELECT, TABLE, VALUES or a set operation), with the optional ON DUPLICATE KEY UPDATE.
 *
 * Rule: MYSQL-INSERT-QUERY-001. The facts follow MYSQL-INSERT-001 with the
 * query as the source. A standalone VALUES source supplies its rows directly.
 * Its enclosing WITH, ORDER BY, LIMIT and locking clauses remain represented
 * for rendering, but do not bind names or change the written rows. This role
 * also applies to directly constructed statements, independently of parsing.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/insert-select.html. Status: Implemented.
 *
 * @example Refusing a read source with no relation for its star
 *     $head = new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto(false, null, false, new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))));
 *     $query = new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\Star()]);
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery($head, $query) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 *
 * @visibility public
 * @example Reading the source of an INSERT ... SELECT
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('INSERT INTO t (a) SELECT b FROM u');
 *     [$insert->statement->source instanceof \SqlSemantics\Platform\MySql\Statement\Query\Select, $insert->toString()] // => [true, 'INSERT INTO t (a) SELECT b FROM u']
 */
final class InsertQuery implements Explainable
{
    use Snapshot;

    /**
     * @var list<Assignment> The assignments of ON DUPLICATE KEY UPDATE in written order
     */
    public readonly array $onDuplicate;

    /**
     * @param InsertInto $into The head: verb, modifiers, table and column list
     * @param Query $source The query whose rows are written
     * @param list<Assignment> $onDuplicate The assignments of ON DUPLICATE KEY UPDATE
     * @throws InvalidConstruction When REPLACE has ON DUPLICATE KEY UPDATE
     */
    public function __construct(public readonly InsertInto $into, public readonly Query $source, array $onDuplicate = [])
    {
        $this->onDuplicate = Check::listOf($onDuplicate, Assignment::class, 'ON DUPLICATE KEY UPDATE holds assignments.');
        Check::input(!$into->replace || $this->onDuplicate === [], 'REPLACE has no ON DUPLICATE KEY UPDATE.');
        Check::input(!(new \SqlSemantics\Platform\MySql\Rules\Dml\ValueSources())->hasDestination($source), 'An INSERT source supplies rows without an INTO result destination.');
        Check::input($this->values() !== null || (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ReadColumns())->problem($source) === null, 'An INSERT read source has coherent fixed columns.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Answers the VALUES rows the source writes, also in parentheses, after WITH, under ORDER BY, LIMIT or a locking clause, or null when the source is another query.
     *
     * The server writes every row of such a source in written order: it
     * ignores ORDER BY and LIMIT there (verified on a live 8.4 server).
     */
    public function values(): ?ValuesQuery
    {
        return (new \SqlSemantics\Platform\MySql\Rules\Dml\ValueSources())->values($this->source);
    }

    /**
     * Answers the query wrappers whose clauses do not participate in a standalone VALUES write.
     *
     * The row expressions keep their ordinary meaning, including their own subqueries.
     * A set operation and SELECT keep all of their clauses. Verified against MySQL
     * 8.0, 8.4 and 9.1, including absent local variables and common-table names.
     *
     * @return list<QueryStatement|ParenthesizedQuery|QueryExpression|ValuesClauses>
     */
    public function wrappers(): array
    {
        if ($this->values() === null) {
            return [];
        }
        $wrappers = [];
        $source = $this->source;
        while ($source instanceof QueryStatement || $source instanceof ParenthesizedQuery || $source instanceof QueryExpression || $source instanceof ValuesClauses) {
            $wrappers[] = $source;
            $source = $source instanceof QueryExpression || $source instanceof ValuesClauses ? $source->body : $source->query;
        }
        return $wrappers;
    }

    /**
     * Derives the written table, the columns, the query and the assignments; the statement returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new InsertFacts())->query($this, $derivation, $derivation->environment());
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->into)->node($this->source);
        if ($this->onDuplicate !== []) {
            $out->keyword('ON', 'DUPLICATE', 'KEY', 'UPDATE')->list($this->onDuplicate);
        }
    }
}
