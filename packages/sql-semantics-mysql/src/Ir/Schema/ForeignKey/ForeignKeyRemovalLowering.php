<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexDependencyLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexPolicyLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Contract\AnalysisContext;
use SqlSemantics\Platform\MySql\Model\Contract\LexicalSettings;
use SqlSemantics\Platform\MySql\Model\Contract\ParameterStyle;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\RelationKind;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Comparison;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Rules\SessionDatabase;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;

/**
 * Binds foreign constraints and physical removals over one native ALTER owner.
 * @visibility SqlSemantics
 */
final class ForeignKeyRemovalLowering
{
    /**
     * Recognizes a group containing a foreign-key removal and optional index removals.
     * @return non-empty-list<DropElement>|null
     */
    public function changes(AlterTable $statement): ?array
    {
        $actions = [];
        $foreign = false;
        foreach ($statement->commands as $command) {
            if ($command instanceof AlgorithmOption || $command instanceof LockOption) {
                continue;
            }
            if (!$command instanceof DropElement || !in_array($command->kind, [ElementKind::ForeignKey, ElementKind::Index, ElementKind::PrimaryKey], true)) {
                return null;
            }
            $actions[] = $command;
            $foreign = $foreign || $command->kind === ElementKind::ForeignKey;
        }

        return $foreign && $actions !== [] ? $actions : null;
    }

    /**
     * Publishes checked native constraints and optional dependent indexes together.
     */
    public function lower(BoundStatement $operation, AlterTable $statement): DropForeignKeys
    {
        $selected = $this->changes($statement);
        Check::input($selected !== null, 'ALTER TABLE requires a native foreign-key removal group.');
        $owner = $this->owner($operation, $statement);
        $indexes = new DropIndexLowering();
        $actions = $physical = $removed = [];
        foreach ($selected as $action) {
            if ($action->kind === ElementKind::ForeignKey) {
                $constraint = $this->constraint($operation->context, $owner, $action);
                $actions[] = $constraint;
                if ($constraint->name !== null) {
                    $removed[] = $constraint->name;
                }
            } else {
                $target = $indexes->identity($operation, $statement, $action->name->column ?? new Name('PRIMARY'), $action->name?->table);
                $actions[] = $target;
                $physical[] = $target;
            }
        }
        $requirements = $physical === [] ? [] : (new IndexDependencyLowering())->resolve($operation, $statement, $physical, $removed);

        return new DropForeignKeys(
            $owner,
            $actions,
            $requirements,
            $operation->context->profile->grammar,
            (new IndexPolicyLowering())->alteration($statement),
            $statement->ignore,
        );
    }

    /**
     * Binds the absolute physical owning relation under the fixed native table profile.
     */
    public function owner(BoundStatement $operation, AlterTable $statement): TableIdentity
    {
        $context = $operation->context;
        Check::input(
            $context->profile->parameters === ParameterStyle::Native && $context->profile->lexical->equals(new LexicalSettings())
                && $context->relationNames === Comparison::Sensitive,
            'Native foreign-key removal requires the default InnoDB profile.'
        );
        $bound = $operation->facts->relation($statement)->table;
        Check::input(
            $bound instanceof DeclaredTable && $bound->table->kind === RelationKind::BaseTable && $bound->table->name->catalog === null
                && $bound->table->storageEngine !== null && strcasecmp($bound->table->storageEngine, 'InnoDB') === 0,
            'Native foreign-key removal requires a resolved ordinary InnoDB table.'
        );
        $table = $bound->table;
        $schema = ($table->name->schema ?? $context->declarationSchema)->value;
        Check::input($schema !== SessionDatabase::UNNAMED, 'Native foreign-key removal needs an absolute owning database.');

        return new TableIdentity(
            new TableName(new Identifier($schema), new Identifier($table->name->name->value)),
            $table->temporary ? ExternalRelationKind::TemporaryTable : ExternalRelationKind::BaseTable,
        );
    }

    /**
     * Resolves an original constraint or the explicit legacy temporary action.
     */
    public function constraint(AnalysisContext $context, TableIdentity $owner, DropElement $action): ForeignKeyRemoval
    {
        $written = $action->name;
        Check::input($action->kind === ElementKind::ForeignKey && $written !== null, 'A foreign-key removal requires a named constraint.');
        $legacy = (new ForeignKeyRemovalSet())->legacy($context->profile->grammar);
        $this->qualifier($owner, $written->table, $legacy);
        if ($owner->kind === ExternalRelationKind::TemporaryTable) {
            Check::input($legacy, 'This MySQL release has no foreign-key removal target on a temporary table.');

            Check::input(preg_match('/\A[^\x00\x{10000}-\x{10FFFF}]*\z/u', $written->column->value) === 1, 'The ignored native label must be a decoded BMP identifier.');

            return new ForeignKeyRemoval(null, ForeignKeyRemovalKind::IgnoredOnTemporary);
        }
        $names = new NativeIdentifierCase();
        $required = $names->fold($written->column->value);
        $matches = [];
        $scope = new IndexDependencyLowering();
        foreach ($context->foreignKeys as $edge) {
            if ($names->fold($edge->constraint->value) === $required && $scope->matches($edge->child, $owner)) {
                $matches[] = $edge->constraint;
            }
        }
        Check::input(count($matches) === 1, 'Native foreign-key removal requires one resolved constraint on its owning table.');

        return new ForeignKeyRemoval(new Identifier($matches[0]->value), ForeignKeyRemovalKind::Constraint);
    }

    /**
     * Resolves the legacy optional qualifier against the already bound owner.
     */
    public function qualifier(TableIdentity $owner, ?QualifiedName $name, bool $legacy): void
    {
        if ($name === null) {
            return;
        }
        Check::input(
            $legacy && $name->name->value === $owner->name->table->value
                && ($name->schema === null || $name->schema->value === $owner->name->schema->value),
            'A qualified foreign-key removal must name its selected owning table.'
        );
    }
}
