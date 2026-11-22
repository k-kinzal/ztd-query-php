<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\Sqlite\Statement\Schema\AbsentRelation;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Drop;
use SqlSemantics\Platform\Sqlite\Statement\Schema\SchemaObjectKind;

/**
 * Resolves a table or view removal before the complete value is built.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DropRelationLowering
{
    /**
     * Constructs a removal once its schema and object kind are fixed.
     * @throws InvalidConstruction When the target does not resolve to the required kind
     */
    public function lower(BoundStatement $operation, Drop $statement): DropTable|DropView
    {
        $kind = match ($statement->object) {
            SchemaObjectKind::Table => RelationKind::BaseTable,
            SchemaObjectKind::View => RelationKind::View,
            SchemaObjectKind::Index, SchemaObjectKind::Trigger => throw new InvalidConstruction(
                'The complete DROP requires the TABLE object kind or the VIEW object kind.',
            ),
        };
        $comparison = Comparison::AsciiInsensitive;
        $path = $operation->context->searchPath;
        $verb = 'DROP ' . $statement->object->value;
        Check::input(
            $operation->context->relationNames === $comparison
                && isset($path[1]) && $comparison->equal($path[0]->value, 'temp') && $comparison->equal($path[1]->value, 'main'),
            $verb . ' requires SQLite relation comparison and schema search order.',
        );
        $resolution = $operation->facts->relation($statement)->table;
        $schema = null;
        $name = $statement->name->name;
        if ($resolution instanceof DeclaredTable && $resolution->table->kind === $kind) {
            $target = $resolution->table->name;
            $schema = $target->schema ?? $operation->context->declarationSchema;
            $name = $target->name;
        } elseif ($resolution instanceof AbsentRelation && $statement->ifExists) {
            $target = $resolution->name;
            Check::input($target->schema !== null, 'An absent ' . $verb . ' target requires an explicit schema.');
            foreach ($path as $known) {
                if ($comparison->equal($known->value, $target->schema->value)) {
                    $schema = $known;
                    break;
                }
            }
        }
        $expected = $kind === RelationKind::View ? 'view' : 'ordinary table';
        Check::input($schema !== null, $verb . ' requires a resolved ' . $expected . ' or a known schema-qualified absence.');

        $location = new Identifier($schema->value);
        $object = new Identifier($name->value);

        return $kind === RelationKind::View
            ? new DropView($location, $object, $statement->ifExists)
            : new DropTable($location, $object, $statement->ifExists);
    }
}
