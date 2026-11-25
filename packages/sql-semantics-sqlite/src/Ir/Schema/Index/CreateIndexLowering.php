<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Index;

use SqlSemantics\Platform\Sqlite\Ir\ExpressionLowering;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\SourceBinding;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortDirection;
use SqlSemantics\Platform\Sqlite\Statement\Schema\CreateIndex as ParsedIndex;

/**
 * Compiles an SQLite index over the resolved owning table.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CreateIndexLowering
{
    /**
     * Resolves the ordered keys and predicate against the declared table.
     *
     * @throws InvalidConstruction When a name or expression lacks a supported binding
     */
    public function lower(BoundStatement $operation, ParsedIndex $statement): CreateIndex
    {
        $context = $operation->context;
        $comparison = Comparison::AsciiInsensitive;
        Check::input(
            $context->relationNames === $comparison && $context->columnNames === $comparison
                && isset($context->searchPath[1]) && $comparison->equal($context->searchPath[0]->value, 'temp')
                && $comparison->equal($context->searchPath[1]->value, 'main'),
            'CREATE INDEX requires SQLite identifier comparison and schema search order.',
        );
        $resolution = $operation->facts->relation($statement)->table;
        Check::input(
            $resolution instanceof DeclaredTable && $resolution->table->kind === RelationKind::BaseTable && $resolution->table->complete,
            'CREATE INDEX requires a resolved ordinary table with a complete heading.',
        );
        $table = $resolution->table;
        $names = [];
        foreach ($table->columns as $column) {
            $names[] = new Identifier($column->name->value);
        }
        Check::input($names !== [], 'CREATE INDEX requires a declared table heading.');
        $binding = new SourceBinding($operation, $statement, $names);
        $expressions = new ExpressionLowering(indexSyntax: true);
        $terms = [];
        foreach ($statement->terms as $term) {
            Check::input($term->nulls === null, 'SQLite index keys cannot specify NULLS FIRST or NULLS LAST.');
            $expression = $expressions->lower($term->expression, $binding);
            Check::input(!$expression instanceof Parameter, 'An index expression cannot use a live parameter.');
            $terms[] = new IndexTerm($expression, $term->direction === SortDirection::Descending);
        }
        $where = $statement->where === null ? null : $expressions->lower($statement->where, $binding);
        Check::input(!$where instanceof Parameter, 'A partial index cannot use a live parameter.');

        return new CreateIndex(
            new Identifier(($table->name->schema ?? $context->declarationSchema)->value),
            new Identifier($statement->name->name->value),
            new Identifier($table->name->name->value),
            $names,
            $terms,
            $where,
            $statement->unique,
            $statement->ifNotExists,
        );
    }
}
