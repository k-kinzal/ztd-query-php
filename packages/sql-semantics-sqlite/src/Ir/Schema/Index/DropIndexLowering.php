<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema\Index;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Drop;
use SqlSemantics\Platform\Sqlite\Statement\Schema\SchemaObjectKind;

/**
 * Resolves an index removal in SQLite's index namespace.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DropIndexLowering
{
    /**
     * Resolves the target by schema precedence or establishes a guarded absence.
     *
     * @throws InvalidConstruction When the supported index identity or absence cannot be established
     */
    public function lower(BoundStatement $operation, Drop $drop): DropIndex
    {
        Check::input($drop->object === SchemaObjectKind::Index, 'An index removal requires the DROP INDEX operation.');
        $context = $operation->context;
        $comparison = Comparison::AsciiInsensitive;
        $path = $context->searchPath;
        Check::input(
            $context->relationNames === $comparison && isset($path[1])
                && $comparison->equal($path[0]->value, 'temp') && $comparison->equal($path[1]->value, 'main'),
            'DROP INDEX requires SQLite comparison and schema search order.',
        );

        $name = new Identifier($drop->name->name->value);
        $reserved = str_starts_with($name->key(), 'sqlite_');
        $schema = $drop->name->schema;

        foreach ($schema === null ? $path : [$schema] as $location) {
            $matches = $context->indexesAt($drop->name->name, $location);
            Check::input(count($matches) <= 1, 'Conflicting declared indexes share the selected coordinate.');
            if ($matches !== []) {
                $index = $matches[0];
                Check::input($index->removable, 'SQLite cannot drop a UNIQUE or PRIMARY KEY constraint index.');
                $target = new IndexReference(
                    new Identifier($index->schema->value),
                    new Identifier($index->name->value),
                    new Identifier($index->table->value),
                );

                return new DropIndex($target, $drop->ifExists);
            }
            Check::input($schema !== null || $context->complete, 'Unqualified DROP INDEX cannot skip an open schema.');
        }

        Check::input($context->complete && $drop->ifExists, 'DROP INDEX requires a resolved index or a known guarded absence.');
        Check::input(!$reserved || $context->knowsConstraintIndexes($schema), 'A reserved index absence needs a complete implicit index namespace.');

        return new DropIndex(new MissingIndex($name, $schema === null ? null : new Identifier($schema->value)), true);
    }
}
