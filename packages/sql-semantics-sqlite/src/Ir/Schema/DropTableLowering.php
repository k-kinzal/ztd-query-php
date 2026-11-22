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
 * Resolves an ordinary table removal before the complete value is built.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class DropTableLowering
{
    /**
     * Constructs the drop only when the schema and table identity are fixed.
     * @throws InvalidConstruction When the target does not resolve to an ordinary table location
     */
    public function lower(BoundStatement $operation, Drop $statement): DropTable
    {
        Check::input($statement->object === SchemaObjectKind::Table, 'The complete DROP currently requires the TABLE object kind.');
        $comparison = Comparison::AsciiInsensitive;
        $path = $operation->context->searchPath;
        Check::input(
            $operation->context->relationNames === $comparison
                && isset($path[1]) && $comparison->equal($path[0]->value, 'temp') && $comparison->equal($path[1]->value, 'main'),
            'DROP TABLE requires SQLite relation comparison and schema search order.',
        );
        $resolution = $operation->facts->relation($statement)->table;
        if ($resolution instanceof DeclaredTable && $resolution->table->kind === RelationKind::BaseTable) {
            $name = $resolution->table->name;
            $schema = $name->schema ?? $operation->context->declarationSchema;

            return new DropTable(new Identifier($schema->value), new Identifier($name->name->value), $statement->ifExists);
        }
        if ($resolution instanceof AbsentRelation && $statement->ifExists) {
            $name = $resolution->name;
            Check::input($name->schema !== null, 'An absent DROP TABLE target requires an explicit schema.');
            foreach ($path as $schema) {
                if ($comparison->equal($schema->value, $name->schema->value)) {
                    return new DropTable(new Identifier($schema->value), new Identifier($name->name->value), true);
                }
            }
        }

        throw new InvalidConstruction('DROP TABLE requires a resolved ordinary table or a known schema-qualified absence.');
    }
}
