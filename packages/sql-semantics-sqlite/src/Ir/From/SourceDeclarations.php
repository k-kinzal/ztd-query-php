<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\From;

use SqlSemantics\Platform\Sqlite\Ir\Rows\LocalSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Column;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\CommonTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Relation;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\MutationTarget;
use SqlSemantics\Platform\Sqlite\Statement\Query\With\CommonTable as Definition;
use SqlSemantics\Platform\Sqlite\Statement\Relation\JoinChain;
use SqlSemantics\Platform\Sqlite\Statement\Relation\TableInput;

/**
 * Collects the declarations corresponding to the supported local input occurrences.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class SourceDeclarations
{
    /**
     * Resolves the inputs in the order in which their slots will be numbered.
     *
     * @param array{Definition, LocalSource}|null $localDefinition The checked optional WITH definition
     * @return list<array{TableInput|MutationTarget, Table|LocalSource}>
     */
    public function bind(BoundStatement $operation, ?Relation $source, ?array $localDefinition = null): array
    {
        if ($source === null) {
            Check::input($localDefinition === null, 'A WITH SELECT must read its checked local definition.');

            return [];
        }
        $comparison = Comparison::AsciiInsensitive;
        $path = $operation->context->searchPath;
        Check::input(
            $operation->context->relationNames === $comparison
                && $operation->context->columnNames === $comparison,
            'The declaration context must use SQLite name comparison.',
        );
        Check::input(
            isset($path[1]) && $comparison->equal($path[0]->value, 'temp') && $comparison->equal($path[1]->value, 'main'),
            'The declaration context must search temp and main before attached schemas.',
        );
        $terms = [$source];
        if ($source instanceof JoinChain) {
            Check::input($source->constraint === null, 'The first table cannot have a join constraint.');
            $terms = [$source->first];
            foreach ($source->steps as $step) {
                $terms[] = $step->relation;
            }
        }
        $tables = [];
        $usesLocal = false;
        foreach ($terms as $term) {
            Check::input(($term instanceof TableInput || $term instanceof MutationTarget) && $term->index === null, 'The projection source must be an ordinary table input.');
            $resolution = $operation->facts->relation($term)->table;
            if ($resolution instanceof CommonTable && $localDefinition !== null && $resolution->definition === $localDefinition[0]) {
                $tables[] = [$term, $localDefinition[1]];
                $usesLocal = true;
                continue;
            }
            $kinds = $term instanceof TableInput ? [RelationKind::BaseTable, RelationKind::View] : [RelationKind::BaseTable];
            Check::input(
                $resolution instanceof DeclaredTable && $resolution->table->complete
                    && in_array($resolution->table->kind, $kinds, true) && $resolution->table->name->catalog === null,
                'Reading requires a resolved, complete table or view declaration. A mutation target requires a base table.',
            );
            $tables[] = [$term, $resolution->table];
        }

        Check::input($localDefinition === null || $usesLocal, 'A WITH SELECT must read its checked local definition.');

        return $tables;
    }

    /**
     * Maps the unique declared names of an ordinary external source in heading order.
     * @return array<array-key, Column>
     */
    public function mergeHeading(BoundStatement $operation, Relation $source): array
    {
        $resolution = $operation->facts->relation($source)->table;
        Check::input($resolution instanceof DeclaredTable && $resolution->table->complete && $resolution->table->kind === RelationKind::BaseTable, 'A merged join requires complete base-table headings.');
        $columns = [];
        foreach ($resolution->table->columns as $column) {
            $key = Comparison::AsciiInsensitive->fold($column->name->value);
            Check::input(!isset($columns[$key]), 'A merged join requires distinct names in each heading.');
            $columns[$key] = $column;
        }

        return $columns;
    }

    /**
     * Collects the first declared member for each name in the left join prefix.
     * @return array<array-key, array{int, Column}>
     */
    public function prefixHeading(BoundStatement $operation, JoinChain $chain, int $position): array
    {
        $terms = [$chain->first];
        foreach (array_slice($chain->steps, 0, $position) as $step) {
            $terms[] = $step->relation;
        }
        $fields = [];
        foreach ($terms as $occurrence => $term) {
            foreach ($this->mergeHeading($operation, $term) as $name => $column) {
                $fields[$name] ??= [$occurrence, $column];
            }
        }

        return $fields;
    }
}
