<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir;

use SqlSemantics\AnalysisContext;
use SqlSemantics\IrAnalyzer;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Operation;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression as ParsedColumn;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;

/**
 * Publishes complete MySQL SELECT values in the supported projection domain.
 *
 * @visibility public
 * @example Analyzing a complete result
 *     (new \SqlSemantics\Platform\MySql\Ir\Analyzer())->analyze('SELECT 42 AS answer')->toString() // => 'SELECT 42 AS `answer`'
 */
final class Analyzer implements IrAnalyzer
{
    /**
     * Uses the supplied MySQL language and declaration settings.
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
        Check::input($profile->lexical->equals(new LexicalSettings()), 'This projection profile requires the default lexical settings.');
        $operation = $this->semantics->analyze($sql, $context);
        $statement = $operation->statement;
        Check::input($statement instanceof ParsedSelect, 'Complete MySQL IR currently supports a single projection SELECT.');
        Check::input(
            $statement->where === null && $statement->groupBy === null && $statement->having === null
                && $statement->windows === [] && $statement->qualify === null && $statement->orderBy === []
                && $statement->limit === null && $statement->procedure === null && $statement->locking === []
                && $statement->into === null && $statement->late === null
                && ($statement->options === [] || $statement->options === [SelectOption::All]),
            'Complete MySQL IR currently supports unfiltered, unordered bag projections.',
        );
        $binding = new SourceBinding($operation, $statement->from);
        $expressions = new ExpressionLowering();
        $results = [];
        foreach ($statement->items as $index => $column) {
            Check::input($column instanceof ParsedColumn, 'A complete projection currently requires explicit result expressions.');
            $name = $operation->field($index)->name;
            Check::input($name !== null, 'The result must have a resolved name.');
            $results[] = new ResultColumn($expressions->lower($column->expression, $binding), new Identifier(ltrim($name->value, " \t\r\n\v\f")));
        }

        return new Select($results, $binding->source(), $profile->grammar);
    }
}
