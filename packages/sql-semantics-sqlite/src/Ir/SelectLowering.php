<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Ir\From\QueryClauses;
use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSourceLowering;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select as ParsedSelect;
use SqlSemantics\Platform\Sqlite\Statement\Query\SetQuantifier;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\WithQuery;

/**
 * Resolves the supported SELECT projection and constructs its complete value.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class SelectLowering
{
    /**
     * Converts the checked operation into a complete value.
     * @throws InvalidConstruction When the operation cannot be represented in this domain
     */
    public function lower(BoundStatement $operation, ParsedSelect|WithQuery|null $arm = null): Select
    {
        $statement = $arm ?? $operation->statement;
        $local = null;
        if ($statement instanceof WithQuery) {
            $definition = (new LocalSourceLowering())->lower($operation, $statement);
            $local = [$statement->with->tables[0], $definition];
            $statement = $statement->body;
        }
        Check::input($statement instanceof ParsedSelect, 'Complete SQLite IR currently supports a single projection SELECT.');
        Check::input(
            $statement->groupBy === [] && $statement->having === null && $statement->windows === [],
            'Complete SQLite IR currently supports projections without grouping or windows.',
        );
        $binding = new SourceBinding($operation, $statement->from, localDefinition: $local);
        $expressions = new ExpressionLowering();
        $fields = $arm === null ? $operation->facts->output?->fields() : $operation->facts->query($arm)->fields();
        Check::input($fields !== null, 'A complete projection requires a resolved output shape.');
        $results = [];
        foreach ($fields as $field) {
            $name = $field->name;
            Check::input($name !== null, 'The result must have a resolved name.');
            $label = new Identifier($name->value);
            $expression = $field->expression;
            if ($expression === null) {
                $resolution = $field->resolution;
                Check::input($resolution instanceof ResolvedColumn, 'A wildcard field must resolve to a visible input value.');
                $results[] = new ResultColumn($binding->value($resolution), $label);
                continue;
            }
            $results[] = new ResultColumn($expressions->lower($expression, $binding), $label);
        }

        Check::input($results !== [], 'A complete projection requires at least one resolved output field.');

        $clauses = new QueryClauses();
        $where = $clauses->predicate($statement->where, $binding);
        $joins = $clauses->joins($statement->from, $binding, $operation);

        return new Select(
            $results,
            $binding->input(),
            $joins,
            $where,
            $clauses->ordering($statement->orderBy),
            $statement->quantifier === SetQuantifier::Distinct,
            $clauses->limit($statement->limit, $binding),
        );
    }
}
