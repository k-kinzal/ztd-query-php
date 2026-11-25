<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Lowering\Catalog\Index;

use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexNames;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexNamespace;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Index\IndexSchemas;
use SqlSemantics\Platform\PostgreSql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\PostgreSql\Model\Facade\Index\IndexCreationVisibility;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\CreateIndex;

/**
 * Resolves the name of an ordinary anonymous index in an ordered declaration snapshot.
 *
 * @visibility SqlSemantics
 */
final class AnonymousIndexName
{
    /**
     * Returns the allocated name when all earlier occupants and physical labels are known.
     */
    public function resolve(BoundStatement $binding, CreateIndex $index, Name $schema, Name $table, IndexNamespace $namespace): ?Name
    {
        $context = $binding->context;
        $owner = $binding->facts->relation($index)->table;
        if (!$context->complete || $namespace->hasUnknownNames($schema) || $namespace->partitioned($schema, $table)
            || IndexSchemas::system($schema->value) || !$owner instanceof DeclaredTable || !$owner->table->complete
            || $owner->table->kind !== RelationKind::BaseTable
            || ($index->method !== null && $index->method->value !== 'btree')) {
            return null;
        }
        $fields = AnonymousIndexFields::resolve($binding, $index, $owner->table);
        if ($fields === null) {
            return null;
        }
        $local = [];
        foreach ($binding->facts->declarations as $relation) {
            if ($relation->name->schema?->value === $schema->value) {
                $local[$relation->name->name->value] = true;
            }
        }
        $visibility = new IndexCreationVisibility();
        $attributes = implode('_', IndexNames::attributes($fields));
        $pass = 0;
        do {
            $suffix = $pass === 0 ? 'idx' : 'idx' . $pass;
            $candidate = new Name(IndexNames::candidate($table->value, $attributes, $suffix));
            ++$pass;
        } while (isset($local[$candidate->value]) || $visibility->occupied($context, $namespace->indexes, $candidate, $schema));

        return $candidate;
    }
}
