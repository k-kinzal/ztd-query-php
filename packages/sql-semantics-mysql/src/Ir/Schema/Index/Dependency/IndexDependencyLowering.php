<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\IndexIdentity;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\DropIndex;

/**
 * Resolves the affected foreign-key field sequences and original physical keys.
 * @visibility SqlSemantics
 */
final class IndexDependencyLowering
{
    /**
     * Produces only the physical alternatives applicable to the checked removal.
     * @param non-empty-list<IndexIdentity> $targets Ordered removals of one owner
     * @return list<IndexForeignKeyRequirement>
     */
    public function resolve(BoundStatement $operation, AlterTable|DropIndex $statement, array $targets): array
    {
        $owner = $targets[0]->owner;
        if ($owner->kind === ExternalRelationKind::TemporaryTable) {
            return [];
        }
        Check::input($operation->context->complete, 'Persistent index removal needs a complete native foreign-key catalog.');
        $table = $operation->facts->relation($statement)->table;
        Check::input($table instanceof DeclaredTable && $table->table->indexes !== null, 'Index removal needs a settled physical key namespace.');
        $coverage = new IndexDependencyKeys();
        $remaining = $this->remaining($targets, $table->table->indexes);
        $requirements = [];
        $release = $operation->context->profile->grammar;
        foreach ($operation->context->foreignKeys as $edge) {
            if ($this->matches($edge->child, $owner)) {
                $keys = $coverage->support($edge->childFields, false, $release, $table->table->indexes, $remaining);
                $requirements[] = new IndexForeignKeyRequirement($owner, $keys);
            }
            if ($this->matches($edge->parent, $owner)) {
                Check::input(
                    $edge->parentFields !== null && count($edge->parentFields) === count($edge->childFields),
                    'Index removal needs settled ordered parent fields.'
                );
                $keys = $coverage->support($edge->parentFields, true, $release, $table->table->indexes, $remaining);
                $requirements[] = new IndexForeignKeyRequirement($owner, $keys);
            }
        }

        return $requirements;
    }

    /**
     * Resolves an edge endpoint by its absolute native coordinate.
     */
    public function matches(QualifiedName $endpoint, TableIdentity $owner): bool
    {
        $schema = $endpoint->schema?->value;
        if ($endpoint->name->value !== $owner->name->table->value) {
            return false;
        }
        Check::input($schema !== null && $schema !== SessionDatabase::UNNAMED, 'The foreign-key index endpoint needs an absolute owning namespace.');

        return $schema === $owner->name->schema->value;
    }

    /**
     * Removes the selected physical keys from the original index layout.
     * @param non-empty-list<IndexIdentity> $targets Original identities to remove
     * @param list<IndexDeclaration> $indexes Complete initial namespace
     * @return list<IndexDeclaration>
     */
    public function remaining(array $targets, array $indexes): array
    {
        $case = new NativeIdentifierCase();
        $removed = [];
        foreach ($targets as $target) {
            $removed[$case->fold($target->name->value)] = true;
        }

        return array_values(array_filter(
            $indexes,
            static fn (IndexDeclaration $key): bool => !isset($removed[$case->fold($key->name->value)]),
        ));
    }
}
