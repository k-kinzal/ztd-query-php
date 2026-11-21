<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\AnalysisContext;
use SqlSemantics\IrAnalyzer;
use SqlSemantics\Platform\Sqlite\Ir\From\QueryClauses;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\Sqlite\Model\Statement\Operation;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn as ParsedColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\Sqlite\Statement\Query\SetQuantifier;

/**
 * Publishes complete SQLite SELECT values in the supported projection domain.
 *
 * @visibility public
 * @example Analyzing a complete result
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Analyzer())->analyze('SELECT 42 AS answer')->toString() // => 'SELECT 42 AS "answer"'
 */
final class Analyzer implements IrAnalyzer
{
    /**
     * Uses the supplied SQLite language and declaration settings.
     */
    public function __construct(private readonly Semantics $semantics = new Semantics())
    {
    }

    /**
     * Resolves the source and result names before constructing a complete value.
     *
     * @param list<Table|Operation>|AnalysisContext|null $context
     * @throws InvalidConstruction When the analysis cannot produce a complete SELECT in this domain
     */
    public function analyze(string $sql, array|AnalysisContext|null $context = null): Select
    {
        $operation = $this->semantics->analyze($sql, $context);
        $statement = $operation->statement;
        Check::input($statement instanceof ParsedSelect, 'Complete SQLite IR currently supports a single projection SELECT.');
        Check::input(
            $statement->groupBy === [] && $statement->having === null
                && $statement->windows === [] && $statement->limit === null
                && $statement->quantifier !== SetQuantifier::Distinct,
            'Complete SQLite IR currently supports bag projections without grouping, windows or limits.',
        );
        $binding = new SourceBinding($operation, $statement->from);
        $expressions = new ExpressionLowering();
        $results = [];
        foreach ($statement->columns as $index => $column) {
            Check::input($column instanceof ParsedColumn, 'A complete projection currently requires explicit result expressions.');
            $name = $operation->field($index)->name;
            Check::input($name !== null, 'The result must have a resolved name.');
            $results[] = new ResultColumn($expressions->lower($column->expression, $binding), new Identifier($name->value));
        }

        $clauses = new QueryClauses();
        $where = $clauses->predicate($statement->where, $binding);
        $joins = $clauses->joins($statement->from, $binding);

        return new Select($results, $binding->source(), $joins, $where, $clauses->ordering($statement->orderBy));
    }
}
