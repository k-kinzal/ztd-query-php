<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Exchange;

use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyAdditionLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\ForeignKeyTables;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\ForeignKeyRemovalSet;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexPolicyLowering;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;

/**
 * Binds simultaneous native additions and removals in a single operation.
 * @visibility SqlSemantics
 */
final class ForeignKeyExchangeLowering
{
    /**
     * Recognizes a group limited to foreign-key additions, removals and physical policy.
     *
     * @return non-empty-list<ForeignKey|DropElement>|null
     */
    public function actions(AlterTable $statement): ?array
    {
        $actions = [];
        $adds = $drops = false;
        foreach ($statement->commands as $command) {
            if ($command instanceof AlgorithmOption || $command instanceof LockOption) {
                continue;
            }
            if ($command instanceof AddConstraint && $command->element instanceof ForeignKey) {
                $actions[] = $command->element;
                $adds = true;
            } elseif ($command instanceof DropElement && $command->kind === ElementKind::ForeignKey) {
                $actions[] = $command;
                $drops = true;
            } else {
                return null;
            }
        }

        return $adds && $drops && $actions !== [] ? $actions : null;
    }

    /**
     * Resolves the original and introduced native references under the same starting catalog.
     */
    public function lower(BoundStatement $operation, AlterTable $statement): AlterForeignKeys
    {
        $actions = $this->actions($statement);
        Check::input($actions !== null, 'ALTER TABLE requires both native foreign-key action kinds.');
        [$child, $owner] = (new ForeignKeyTables())->child($operation, $statement);
        $release = $operation->context->profile->grammar;
        $policy = (new IndexPolicyLowering())->alteration($statement);
        $remove = new ForeignKeyRemovalLowering();
        $definitions = $removals = [];
        foreach ($actions as $slot => $action) {
            if ($action instanceof ForeignKey) {
                $definitions[] = $action;
            } else {
                $removals[$slot] = $remove->constraint($operation->context, $owner, $action);
            }
        }
        Check::invariant($definitions !== [] && $removals !== [], 'The exchange has both native action roles.');
        (new ForeignKeyRemovalSet())->check($owner, array_values($removals), [], $release, $policy);
        [$additions, $keys, $constraints, $limit] = (new ForeignKeyAdditionLowering())->resolve($operation, $child, $owner, $definitions);
        $result = [];
        $position = 0;
        foreach ($actions as $slot => $action) {
            $result[] = $removals[$slot] ?? $additions[$position++];
        }

        return new AlterForeignKeys(
            $owner,
            $result,
            $keys,
            $constraints,
            $release,
            $policy,
            $statement->ignore,
            $limit,
        );
    }
}
