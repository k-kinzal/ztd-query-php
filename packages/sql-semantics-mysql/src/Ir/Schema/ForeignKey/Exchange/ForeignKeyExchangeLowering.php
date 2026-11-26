<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange;

use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyConstraintIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAdditionLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyTables;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemoval;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalSet;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Dependency\IndexDependencyLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexPolicyLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\TableName;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\ForeignKeyIndexSide;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexRemovalTarget;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\Table;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;

/**
 * Binds simultaneous native additions and removals in a single operation.
 * @visibility SqlSemantics
 * @phpstan-import-type IndexFrame from IndexAdditionLowering
 * @phpstan-type ParsedKeyAction ForeignKey|DropElement|IndexDefinition
 */
final class ForeignKeyExchangeLowering
{
    /**
     * Recognizes foreign-key additions combined with other native key changes.
     *
     * @return non-empty-list<ParsedKeyAction>|null
     */
    public function actions(AlterTable $statement): ?array
    {
        $actions = [];
        $adds = $mixed = false;
        foreach ($statement->commands as $command) {
            if ($command instanceof AlgorithmOption || $command instanceof LockOption) {
                continue;
            }
            if ($command instanceof AddConstraint && $command->element instanceof ForeignKey) {
                $actions[] = $command->element;
                $adds = true;
            } elseif ($command instanceof AddConstraint && $command->element instanceof IndexDefinition) {
                $actions[] = $command->element;
                $mixed = true;
            } elseif ($command instanceof DropElement && in_array($command->kind, [ElementKind::ForeignKey, ElementKind::Index, ElementKind::PrimaryKey], true)) {
                $actions[] = $command;
                $mixed = true;
            } else {
                return null;
            }
        }

        return $adds && $mixed && $actions !== [] ? $actions : null;
    }

    /**
     * Resolves the original and introduced native references under the same starting catalog.
     */
    public function lower(BoundStatement $operation, AlterTable $statement): AlterForeignKeys
    {
        $actions = $this->actions($statement);
        Check::input($actions !== null, 'ALTER TABLE requires foreign-key additions and a distinct key edit.');
        [$child, $owner] = (new ForeignKeyTables())->child($operation, $statement);
        $release = $operation->context->profile->grammar;
        $policy = (new IndexPolicyLowering())->alteration($statement);
        $removalRule = new ForeignKeyRemovalSet();
        [$definitions, $positions, $removals, $indexes, $physical] = $this->bind(
            $operation,
            $statement,
            $child,
            $owner,
            $actions,
            $removalRule->legacy($release)
        );
        if ($removals !== []) {
            $removalRule->check($owner, array_values($removals), [], $release, $policy);
        }
        $requests = [];
        foreach ($indexes as $slot => [$name, $columns]) {
            $requests[$slot] = [$name, $columns];
        }
        [$additions, $keys, $constraints, $limit, $names] = (new ForeignKeyAdditionLowering())->resolve(
            $operation,
            $child,
            $owner,
            $definitions,
            $requests,
            $positions,
            array_values($physical),
            $policy
        );
        $result = [];
        $position = 0;
        $indexLowering = new IndexAdditionLowering();
        foreach ($actions as $slot => $action) {
            $result[] = match (true) {
                $action instanceof ForeignKey => $additions[$position++],
                $action instanceof IndexDefinition => $indexLowering->complete($owner, $names[$slot], $indexes[$slot]),
                $action instanceof DropElement => $removals[$slot] ?? $physical[$slot],
            };
        }

        $sides = $physical === [] ? null : $this->sides($operation, $owner, $removals);

        return new AlterForeignKeys($owner, $result, $keys, $constraints, $release, $policy, $statement->ignore, $limit, $sides);
    }

    /**
     * Collects the individual typed roles while retaining their original action positions.
     * @param non-empty-list<ParsedKeyAction> $actions Checked parsed actions in written order
     * @return array{non-empty-list<ForeignKey>, list<int>, array<int, ForeignKeyRemoval>, array<int, IndexFrame>, array<int, IndexRemovalTarget>}
     */
    public function bind(BoundStatement $operation, AlterTable $statement, Table $child, TableIdentity $owner, array $actions, bool $legacy): array
    {
        $remove = new ForeignKeyRemovalLowering();
        $declare = new IndexAdditionLowering();
        $dropIndex = new DropIndexLowering();
        $definitions = $positions = $removals = $indexes = $physical = [];
        foreach ($actions as $slot => $action) {
            if ($action instanceof ForeignKey) {
                $definitions[] = $action;
                $positions[] = $slot;
            } elseif ($action instanceof IndexDefinition) {
                $indexes[$slot] = $declare->request($child, $owner, $action, $legacy);
            } elseif ($action->kind === ElementKind::ForeignKey) {
                $removals[$slot] = $remove->constraint($operation->context, $owner, $action);
            } else {
                $physical[$slot] = $dropIndex->removal($operation, $statement, $action->name->column ?? new Name('PRIMARY'), $action->name?->table);
            }
        }
        Check::invariant($definitions !== [] && ($removals !== [] || $indexes !== [] || $physical !== []), 'The alteration has both native action roles.');

        return [$definitions, $positions, $removals, $indexes, $physical];
    }

    /**
     * Projects only the original sides left in force on the altered owner.
     * @param array<int, ForeignKeyRemoval> $removals Original foreign constraints explicitly removed
     * @return list<ForeignKeyIndexSide>
     */
    public function sides(BoundStatement $operation, TableIdentity $owner, array $removals): array
    {
        $scope = new IndexDependencyLowering();
        $case = new NativeIdentifierCase();
        $removed = [];
        foreach ($removals as $removal) {
            Check::invariant($removal->name !== null, 'A persistent foreign-key removal has an original symbol.');
            $removed[$case->fold($removal->name->value)] = true;
        }
        $fields = static fn (Name $name): Identifier => new Identifier($name->value);
        $sides = [];
        foreach ($operation->context->foreignKeys as $edge) {
            $child = $scope->matches($edge->child, $owner);
            if ($child && isset($removed[$case->fold($edge->constraint->value)])) {
                continue;
            }
            $parent = $scope->matches($edge->parent, $owner);
            if (!$child && !$parent) {
                continue;
            }
            Check::input($edge->child->schema !== null, 'A retained native constraint needs an absolute child identity.');
            $source = new TableName(new Identifier($edge->child->schema->value), new Identifier($edge->child->name->value));
            $constraint = new ForeignKeyConstraintIdentity(
                new TableIdentity($source, ExternalRelationKind::BaseTable),
                new Identifier($edge->constraint->value)
            );
            if ($child) {
                $sides[] = new ForeignKeyIndexSide($constraint, array_map($fields, $edge->childFields), false);
            }
            if ($parent) {
                Check::input(
                    $edge->parentFields !== null && count($edge->parentFields) === count($edge->childFields),
                    'A mixed physical removal needs resolved ordered parent fields.'
                );
                $sides[] = new ForeignKeyIndexSide($constraint, array_map($fields, $edge->parentFields), true);
            }
        }

        return $sides;
    }
}
