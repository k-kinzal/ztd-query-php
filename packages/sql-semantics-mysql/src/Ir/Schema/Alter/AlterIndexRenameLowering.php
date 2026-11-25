<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Alter;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexChangePolicy;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Policy\IndexPolicyLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\IndexRename;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\RenameIndexes;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;

/**
 * Resolves every moved index in the initial native namespace.
 * @visibility SqlSemantics
 */
final class AlterIndexRenameLowering
{
    /**
     * Accepts native index renames with physical options.
     * @return non-empty-list<RenameElement>|null
     */
    public function moves(AlterTable $statement): ?array
    {
        $moves = [];
        foreach ($statement->commands as $command) {
            if ($command instanceof AlgorithmOption || $command instanceof LockOption) {
                continue;
            }
            if (!$command instanceof RenameElement || $command->kind !== ElementKind::Index) {
                return null;
            }
            $moves[] = $command;
        }

        return $moves !== [] ? $moves : null;
    }

    /**
     * Binds the owner once in the context and all moves to its original namespace.
     */
    public function lower(BoundStatement $operation, AlterTable $statement): RenameIndexes
    {
        $written = $this->moves($statement);
        Check::input($written !== null, 'ALTER TABLE requires a native index rename.');
        $resolution = $operation->facts->relation($statement)->table;
        Check::input($resolution instanceof DeclaredTable && $resolution->table->indexes !== null, 'Index renaming requires a complete physical key namespace.');
        $selection = new DropIndexLowering();
        $source = $selection->identity($operation, $statement, $written[0]->from->column);
        $moves = [];
        $owner = $source->owner;
        foreach ($written as $action) {
            $this->qualifier($owner, $action->from->table);
            $this->qualifier($owner, $action->to->table);
            $source = $selection->identity($operation, $statement, $action->from->column);
            $moves[] = new IndexRename($source->name, new Identifier($action->to->column->value));
        }

        return new RenameIndexes(
            $owner,
            $moves,
            $this->unmoved($resolution->table->indexes, $moves),
            $operation->context->profile->grammar,
            $this->policy($statement),
        );
    }

    /**
     * Rejects a written legacy qualifier aimed at a different owner.
     */
    public function qualifier(TableIdentity $owner, ?QualifiedName $written): void
    {
        if ($written === null) {
            return;
        }
        Check::input($written->schema === null || $written->schema->value === $owner->name->schema->value, 'Index renaming names a different owning database.');
        Check::input($written->name->value === $owner->name->table->value, 'Index renaming names a different owning table.');
    }

    /**
     * Finds the physical names that remain occupied after removing source labels.
     * @param list<IndexDeclaration> $keys Complete initial physical namespace
     * @param non-empty-list<IndexRename> $moves Checked relative source identities
     * @return list<Identifier>
     */
    public function unmoved(array $keys, array $moves): array
    {
        $case = new NativeIdentifierCase();
        $selected = [];
        foreach ($moves as $move) {
            $selected[$case->fold($move->source->value)] = true;
        }
        $remaining = [];
        foreach ($keys as $key) {
            if (!isset($selected[$case->fold($key->name->value)])) {
                $remaining[] = new Identifier($key->name->value);
            }
        }

        return $remaining;
    }

    /**
     * Resolves the last physical option of each kind.
     */
    public function policy(AlterTable $statement): IndexChangePolicy
    {
        $last = [];
        foreach ($statement->commands as $command) {
            if ($command instanceof AlgorithmOption) {
                $last['algorithm'] = $command;
            } elseif ($command instanceof LockOption) {
                $last['lock'] = $command;
            }
        }

        return (new IndexPolicyLowering())->lower(array_values($last));
    }
}
