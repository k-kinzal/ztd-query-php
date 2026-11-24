<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\Index;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\CreateTable;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndexLowering;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexPresence;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Statement\Catalog\Schema\CreateSchema;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex as ParsedCreateIndex;

/**
 * Resolves a schema index using the schema's earlier member bindings and name occupants.
 *
 * @visibility SqlSemantics
 */
final class SchemaIndexLowering
{
    /**
     * Preserves the relative order of the native index phase.
     *
     * @param array<array-key, CreateTable|TableName|true> $occupied Checked preceding relation occupants
     * @return list<CreateIndex>
     */
    public function lower(BoundStatement $operation, CreateSchema $schema, ?Identifier $resolved, array $occupied): array
    {
        $indexes = [];
        $lowering = new CreateIndexLowering();
        foreach ($schema->elements as $element) {
            if (!$element instanceof ParsedCreateIndex) {
                continue;
            }
            Check::input($resolved !== null && !$schema->ifNotExists, 'Schema indexes require an unguarded, statically named namespace.');
            Check::input(!$element->concurrently, 'A schema index cannot be built concurrently.');
            $coordinate = $element->name->value ?? '';
            $presence = isset($occupied[$coordinate]) ? IndexPresence::Occupied : IndexPresence::Vacant;
            $indexes[] = $lowering->lower($operation, $element, $resolved, $presence);
            $occupied[$coordinate] ??= true;
        }

        return $indexes;
    }
}
