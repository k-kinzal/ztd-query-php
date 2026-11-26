<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Index\DropIndexLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\Binding\DropTriggerLowering;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\DropTrigger;
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
 * Resolves the native table, view, index and trigger removal roles.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DropLowering
{
    /**
     * Constructs a removal once its schema and object kind are fixed.
     * @throws InvalidConstruction When the target does not resolve to the required kind
     */
    public function lower(BoundStatement $operation, Drop $statement): DropTable|DropView|DropIndex|DropTrigger
    {
        if ($statement->object === SchemaObjectKind::Index) {
            return (new DropIndexLowering())->lower($operation, $statement);
        }
        if ($statement->object === SchemaObjectKind::Trigger) {
            return (new DropTriggerLowering())->lower($operation, $statement);
        }
        $kind = match ($statement->object) {
            SchemaObjectKind::Table => RelationKind::BaseTable,
            SchemaObjectKind::View => RelationKind::View,
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
