<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Alter;

use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexes;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexPolicyLowering;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;

/**
 * Resolves the ordered index-removal actions of an ALTER TABLE.
 * @visibility SqlSemantics
 */
final class AlterIndexRemovalLowering
{
    /**
     * Finds every ordered key removal when the other commands are physical options.
     * @return non-empty-list<DropElement>|null
     */
    public function removals(AlterTable $statement): ?array
    {
        $actions = [];
        foreach ($statement->commands as $command) {
            if ($command instanceof AlgorithmOption || $command instanceof LockOption) {
                continue;
            }
            if (!$command instanceof DropElement || !in_array($command->kind, [ElementKind::PrimaryKey, ElementKind::Index], true)) {
                return null;
            }
            $actions[] = $command;
        }

        return $actions !== [] ? $actions : null;
    }

    /**
     * Applies the final checked options and resolves the key on the bound owner.
     */
    public function lower(BoundStatement $operation, AlterTable $statement): DropIndex|DropIndexes
    {
        $actions = $this->removals($statement);
        Check::input($actions !== null, 'ALTER TABLE index removal requires an ordered list of key actions.');
        $last = [];
        foreach ($statement->commands as $command) {
            if ($command instanceof AlgorithmOption) {
                $last['algorithm'] = $command;
            } elseif ($command instanceof LockOption) {
                $last['lock'] = $command;
            }
        }

        $release = $operation->context->profile->grammar;
        $policy = (new IndexPolicyLowering())->lower(array_values($last));
        $identities = new DropIndexLowering();
        $targets = [];
        foreach ($actions as $action) {
            $targets[] = $identities->identity($operation, $statement, $action->name->column ?? new Name('PRIMARY'), $action->name?->table);
        }

        return count($targets) === 1
            ? new DropIndex($targets[0], $release, $policy, $statement->ignore)
            : new DropIndexes($targets, $release, $policy, $statement->ignore);
    }
}
