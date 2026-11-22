<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Mutation;

use SqlSemantics\Platform\Sqlite\Ir\From\SourceDeclarations;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison as NameComparison;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\InsertInto;

/**
 * Resolves the write target and its supplied column layout.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class InsertTargetLowering
{
    /**
     * Resolves explicitly named or implicit writable positions in the same declaration.
     */
    public function lower(BoundStatement $operation, InsertInto $into, bool $defaults): TableSource
    {
        $targets = (new SourceDeclarations())->bind($operation, $into->target);
        Check::invariant(isset($targets[0]), 'A resolved INSERT has a target.');
        [, $table] = $targets[0];
        $columns = [];
        if ($defaults) {
            Check::input($into->columns === [], 'DEFAULT VALUES does not admit a written column list.');
        } elseif ($into->columns === []) {
            foreach ($table->columns as $column) {
                if (!$column->generated) {
                    $columns[] = new Identifier($column->name->value);
                }
            }
        } else {
            foreach ($into->columns as $name) {
                $matching = $table->matchingColumns($name->value, NameComparison::AsciiInsensitive);
                Check::input(
                    count($matching) === 1 && in_array($matching[0], $table->columns, true) && !$matching[0]->generated,
                    'An INSERT column must resolve to a writable declared column.',
                );
                $columns[] = new Identifier($matching[0]->name->value);
            }
        }

        return new TableSource(
            new Identifier(($table->name->schema ?? $operation->context->declarationSchema)->value),
            new Identifier($table->name->name->value),
            $columns,
        );
    }
}
