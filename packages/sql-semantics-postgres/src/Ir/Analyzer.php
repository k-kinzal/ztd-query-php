<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir;

use SqlSemantics\AnalysisContext;
use SqlSemantics\IrAnalyzer;
use SqlSemantics\Platform\PostgreSql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Operation;
use SqlSemantics\Platform\PostgreSql\Semantics;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget as ParsedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select as ParsedSelect;

/**
 * Publishes complete PostgreSQL SELECT values in the supported projection domain.
 *
 * @visibility public
 * @example Analyzing a complete result
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Analyzer())->analyze('SELECT 42 AS answer')->toString() // => 'SELECT CAST(42 AS pg_catalog.int4) AS "answer"'
 */
final class Analyzer implements IrAnalyzer
{
    /**
     * Uses the supplied PostgreSQL language and declaration settings.
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
        $profile = $this->semantics->profile();
        Check::input($profile->parameters === ParameterStyle::Native, 'The projection requires the native language profile.');
        $operation = $this->semantics->analyze($sql, $context);
        $statement = $operation->statement;
        Check::input($statement instanceof ParsedSelect, 'Complete PostgreSQL IR currently supports a single projection SELECT.');
        Check::input(
            $statement->where === null && $statement->groupBy === [] && $statement->having === null
                && $statement->windows === [] && $statement->distinct === null && $statement->into === null && $statement->options === null,
            'Complete PostgreSQL IR currently supports unfiltered, unordered bag projections.',
        );
        Check::input($statement->targets !== [], 'A complete SELECT needs a nonempty projection.');
        $binding = new SourceBinding($operation, $statement->from);
        $expressions = new ExpressionLowering();
        $searchPath = $operation->context->searchPath;
        $operators = $searchPath[0]->value === 'pg_temp' ? ($searchPath[1]->value ?? null) : $searchPath[0]->value;
        $results = [];
        foreach ($statement->targets as $index => $column) {
            Check::input($column instanceof ParsedColumn, 'A complete projection currently requires explicit result expressions.');
            $name = $operation->field($index)->name;
            Check::input($name !== null, 'The result must have a resolved name.');
            $results[] = new ResultColumn($expressions->lower($column->expression, $binding, $operators === 'pg_catalog'), new Identifier($name->value));
        }

        return new Select($results, $binding->source(), $profile->grammar);
    }
}
