<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Schema;

use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\Sqlite\Model\Facade\BoundStatement;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;

/**
 * Resolves the destination schema used by a table declaration.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CreateSchema
{
    /**
     * Resolves the native destination before the declaration is published.
     * @throws InvalidConstruction When the schema is absent or incompatible with TEMP
     */
    public function resolve(BoundStatement $operation, QualifiedName $name, bool $temporary): Identifier
    {
        $schemaName = $name->schema->value ?? ($temporary ? 'temp' : 'main');
        $comparison = Comparison::AsciiInsensitive;
        Check::input(!$temporary || $comparison->equal($schemaName, 'temp'), 'A temporary table must be in the temp schema.');
        foreach ($operation->context->searchPath as $schema) {
            if ($comparison->equal($schemaName, $schema->value)) {
                return new Identifier($schema->value);
            }
        }

        throw new InvalidConstruction('CREATE TABLE requires a known schema.');
    }
}
