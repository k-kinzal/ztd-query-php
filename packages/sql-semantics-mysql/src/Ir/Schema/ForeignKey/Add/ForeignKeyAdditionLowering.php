<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\ForeignKey\Add\Catalog\ForeignKeyCatalogLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexPolicyLowering;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;

/**
 * Publishes table-level native foreign-key additions with both endpoints resolved.
 * @visibility SqlSemantics
 */
final class ForeignKeyAdditionLowering
{
    /**
     * Selects groups consisting entirely of native foreign keys and physical policy.
     * @return non-empty-list<ForeignKey>|null
     */
    public function additions(AlterTable $statement): ?array
    {
        $additions = [];
        foreach ($statement->commands as $action) {
            if ($action instanceof AlgorithmOption || $action instanceof LockOption) {
                continue;
            }
            if (!$action instanceof AddConstraint || !$action->element instanceof ForeignKey) {
                return null;
            }
            $additions[] = $action->element;
        }

        return $additions === [] ? null : $additions;
    }

    /**
     * Resolves the ordered native actions and verifies them through the public root.
     */
    public function lower(BoundStatement $operation, AlterTable $statement): AddForeignKeys
    {
        $definitions = $this->additions($statement);
        Check::input($definitions !== null, 'ALTER TABLE requires a group of native foreign-key additions.');
        $tables = new ForeignKeyTables();
        [$child, $owner] = $tables->child($operation, $statement);
        $release = $operation->context->profile->grammar;
        $native = new ForeignKeyCatalogLowering();
        $keys = [$owner->quoted() => $native->keys($child, $owner)];
        $constraints = $native->constraints($operation->context, $owner);
        $pairsLowering = new ForeignKeyPairLowering();
        $effectsLowering = new ForeignKeyEffectsLowering();
        $frames = $pairs = $requested = $labels = [];
        foreach ($definitions as $definition) {
            [$parent, $target] = $tables->parent($operation, $definition->references);
            $keys[$target->quoted()] ??= $native->keys($parent, $target);
            $pairs[] = $pairsLowering->pairs($definition, $child, $parent, $release);
            $symbol = $this->identifier(($definition->constraint->name ?? null)?->column);
            $index = $this->identifier($definition->name?->column);
            $requested[] = $symbol;
            $labels[] = $symbol ?? $index;
            $frames[] = [$target, $effectsLowering->lower($definition->references, $release), $index];
        }
        $original = array_merge(...array_values($keys));
        $symbols = (new ForeignKeyConstraintNamespace())->allocate($owner, $constraints, $requested);
        $created = (new ForeignKeyChildIndexes())->allocate($owner, $original, $pairs, $labels, $release);
        $additions = [];
        foreach ($frames as $position => [$target, $effects, $written]) {
            $generated = $requested[$position] === null;
            $index = $created[$position];
            $additions[] = new ForeignKeyAddition(
                $target,
                $symbols[$position],
                $pairs[$position],
                $effects,
                $generated,
                $generated && $index !== null ? $written : null,
                $index
            );
        }
        return new AddForeignKeys(
            $owner,
            $additions,
            $original,
            $constraints,
            $release,
            (new IndexPolicyLowering())->alteration($statement),
            $statement->ignore
        );
    }

    /**
     * Decodes a written constraint or key label into a checked native identifier.
     */
    public function identifier(?Name $name): ?Identifier
    {
        return $name === null ? null : new Identifier($name->value);
    }
}
