<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Alter;

use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndex;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexPolicyLowering;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;

/**
 * Resolves the single index-removal action of an ALTER TABLE.
 * @visibility SqlSemantics
 */
final class AlterIndexRemovalLowering
{
    /**
     * Finds the sole native key removal when every other action is a physical option.
     */
    public function removal(AlterTable $statement): ?DropElement
    {
        $actions = array_values(array_filter(
            $statement->commands,
            static fn (AlterCommand $command): bool => !$command instanceof AlgorithmOption && !$command instanceof LockOption,
        ));

        return count($actions) === 1 && $actions[0] instanceof DropElement
            && in_array($actions[0]->kind, [ElementKind::PrimaryKey, ElementKind::Index], true)
            ? $actions[0]
            : null;
    }

    /**
     * Applies the final checked options and resolves the key on the bound owner.
     */
    public function lower(BoundStatement $operation, AlterTable $statement): DropIndex
    {
        $action = $this->removal($statement);
        Check::input($action instanceof DropElement, 'ALTER TABLE index removal requires exactly one key action.');
        $last = [];
        foreach ($statement->commands as $command) {
            if ($command instanceof AlgorithmOption) {
                $last['algorithm'] = $command;
            } elseif ($command instanceof LockOption) {
                $last['lock'] = $command;
            }
        }

        return (new DropIndexLowering())->resolve(
            $operation,
            $statement,
            $action->name->column ?? new Name('PRIMARY'),
            (new IndexPolicyLowering())->lower(array_values($last)),
            $action->name?->table,
            $statement->ignore,
        );
    }
}
