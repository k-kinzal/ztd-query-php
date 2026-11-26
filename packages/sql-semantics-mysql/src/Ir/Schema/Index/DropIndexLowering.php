<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexDependencyLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexPolicyLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexRemovalTarget;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\PhysicalIndexKind;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable as ParsedAlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\DropIndex as ParsedDropIndex;

/**
 * Resolves the physical index within the table already selected by native table lookup.
 *
 * @visibility SqlSemantics
 */
final class DropIndexLowering
{
    /**
     * Publishes a resolved ordinary key with a checked native removal policy.
     */
    public function lower(BoundStatement $operation, ParsedDropIndex $statement): DropIndex
    {
        $target = $this->removal($operation, $statement, $statement->index);

        return new DropIndex(
            $target,
            (new IndexDependencyLowering())->resolve($operation, $statement, [$target->index]),
            $operation->context->profile->grammar,
            (new IndexPolicyLowering())->lower($statement->options),
        );
    }

    /**
     * Resolves a physical key through the selected statement's bound owner.
     */
    public function identity(
        BoundStatement $operation,
        ParsedDropIndex|ParsedAlterTable $statement,
        Name $key,
        ?QualifiedName $qualifier = null,
    ): IndexIdentity {
        $context = $operation->context;
        $profile = $context->profile;
        Check::input(
            $profile->parameters === ParameterStyle::Native && $profile->lexical->equals(new LexicalSettings())
                && $context->relationNames === Comparison::Sensitive,
            'DROP INDEX requires the default native profile.'
        );
        $resolution = $operation->facts->relation($statement)->table;
        Check::input(
            $resolution instanceof DeclaredTable && $resolution->table->kind === RelationKind::BaseTable
                && $resolution->table->name->catalog === null
                && $resolution->table->storageEngine !== null && strcasecmp($resolution->table->storageEngine, 'InnoDB') === 0,
            'DROP INDEX requires a resolved ordinary InnoDB declaration.'
        );
        $table = $resolution->table;
        $schema = ($table->name->schema ?? $context->declarationSchema)->value;
        Check::input($schema !== SessionDatabase::UNNAMED, 'DROP INDEX requires an absolute owning table.');
        if ($qualifier !== null) {
            Check::input($qualifier->schema === null || $qualifier->schema->value === $schema, 'DROP INDEX names a different owning database.');
            Check::input($qualifier->name->value === $table->name->name->value, 'DROP INDEX names a different owning table.');
        }
        $index = $this->index($table, $key);
        $name = new TableName(new Identifier($schema), new Identifier($table->name->name->value));
        $owner = new TableIdentity($name, $table->temporary ? ExternalRelationKind::TemporaryTable : ExternalRelationKind::BaseTable);
        $role = match ($index->kind) {
            PhysicalIndexKind::Primary => IndexRole::Primary,
            PhysicalIndexKind::Unique => $index->clusteredUnique ? IndexRole::ClusteredUnique : IndexRole::Unique,
            PhysicalIndexKind::Index => IndexRole::Ordinary,
            PhysicalIndexKind::FullText, PhysicalIndexKind::Spatial => null,
        };
        Check::input($role !== null, 'DROP INDEX requires a primary or ordinary B-tree index.');

        return new IndexIdentity($owner, new Identifier($index->name->value), $role);
    }

    /**
     * Binds the selected index and its original generation-support facts for a removal.
     */
    public function removal(
        BoundStatement $operation,
        ParsedDropIndex|ParsedAlterTable $statement,
        Name $name,
        ?QualifiedName $qualifier = null,
    ): IndexRemovalTarget {
        $index = $this->identity($operation, $statement, $name, $qualifier);
        $relation = $operation->facts->relation($statement)->table;
        Check::invariant($relation instanceof DeclaredTable, 'A checked native index has a declared owning table.');
        $definition = $this->index($relation->table, $name);
        $increment = (new IndexAutoIncrementLowering())->resolve($definition, $relation->table->indexes ?? []);

        return new IndexRemovalTarget($index, $increment);
    }

    /**
     * Selects one physical key after applying native case comparison.
     */
    public function index(Table $table, Name $name): IndexDeclaration
    {
        $case = new NativeIdentifierCase();
        $key = $case->fold($name->value);
        $indexes = array_values(array_filter($table->indexes ?? [], static fn (IndexDeclaration $index): bool => $case->fold($index->name->value) === $key));
        Check::input(count($indexes) === 1 && $indexes[0]->wholeFields, 'DROP INDEX requires one resolved ordinary index on its owning table.');

        return $indexes[0];
    }
}
