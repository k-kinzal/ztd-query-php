<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\From;

use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Operation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Relation;
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
     * @return list<array{TableInput, Table}>
     */
    public function bind(Operation $operation, ?Relation $source): array
    {
        if ($source === null) {
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
        foreach ($terms as $term) {
            Check::input($term instanceof TableInput && $term->index === null, 'The projection source must be an ordinary table input.');
            $resolution = $operation->facts->relation($term)->table;
            Check::input(
                $resolution instanceof DeclaredTable && $resolution->table->complete
                    && $resolution->table->kind === RelationKind::BaseTable && $resolution->table->name->catalog === null,
                'The projection requires a resolved, complete base table declaration.',
            );
            $tables[] = [$term, $resolution->table];
        }

        return $tables;
    }
}
