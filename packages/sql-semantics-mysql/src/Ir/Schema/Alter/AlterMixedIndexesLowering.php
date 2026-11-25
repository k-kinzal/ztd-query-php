<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Alter;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Change\AlterIndexes;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Change\IndexAlteration;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\DropIndexLowering;
use SqlSemantics\Platform\MySql\Ir\Schema\Index\Rename\IndexRename;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Facade\BoundStatement;
use SqlSemantics\Platform\MySql\Model\Statement\Declaration\IndexDeclaration;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlgorithmOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\LockOption;

/**
 * Binds mixed removal and rename inputs to the same original physical namespace.
 * @visibility SqlSemantics
 */
final class AlterMixedIndexesLowering
{
    /**
     * Identifies statements whose action list contains removals and renames.
     * @return non-empty-list<DropElement|RenameElement>|null
     */
    public function changes(AlterTable $statement): ?array
    {
        $actions = [];
        $drops = $moves = false;
        foreach ($statement->commands as $command) {
            if ($command instanceof AlgorithmOption || $command instanceof LockOption) {
                continue;
            }
            if ($command instanceof DropElement && in_array($command->kind, [ElementKind::PrimaryKey, ElementKind::Index], true)) {
                $drops = true;
            } elseif ($command instanceof RenameElement && $command->kind === ElementKind::Index) {
                $moves = true;
            } else {
                return null;
            }
            $actions[] = $command;
        }

        return $drops && $moves && $actions !== [] ? $actions : null;
    }

    /**
     * Publishes one mixed index operation from the complete initial namespace.
     */
    public function lower(BoundStatement $operation, AlterTable $statement): AlterIndexes
    {
        $written = $this->changes($statement);
        Check::input($written !== null, 'ALTER TABLE requires both ordinary native index removals and renames.');
        $resolved = $operation->facts->relation($statement)->table;
        Check::input($resolved instanceof DeclaredTable && $resolved->table->indexes !== null, 'Mixed index alterations require a complete physical key namespace.');
        $selector = new DropIndexLowering();
        $renames = new AlterIndexRenameLowering();
        $case = new NativeIdentifierCase();
        $owner = null;
        $actions = $modified = [];
        foreach ($written as $action) {
            if ($action instanceof DropElement) {
                $source = $selector->identity($operation, $statement, $action->name->column ?? new Name('PRIMARY'), $action->name?->table);
                $selected = $source;
            } else {
                $source = $selector->identity($operation, $statement, $action->from->column, $action->from->table);
                $renames->qualifier($source->owner, $action->to->table);
                $selected = new IndexRename($source->name, new Identifier($action->to->column->value));
            }
            $owner ??= $source->owner;
            $modified[$case->fold($source->name->value)] = true;
            $actions[] = new IndexAlteration($selected);
        }

        return new AlterIndexes(
            $owner,
            $actions,
            $this->unmoved($resolved->table->indexes, $modified),
            $operation->context->profile->grammar,
            $renames->policy($statement),
        );
    }

    /**
     * Captures exactly the names whose physical indexes keep their original identity and spelling.
     * @param list<IndexDeclaration> $keys Original native namespace
     * @param array<array-key, true> $modified Originally selected physical names
     * @return list<Identifier>
     */
    public function unmoved(array $keys, array $modified): array
    {
        $case = new NativeIdentifierCase();
        $unmoved = [];
        foreach ($keys as $key) {
            if (!isset($modified[$case->fold($key->name->value)])) {
                $unmoved[] = new Identifier($key->name->value);
            }
        }

        return $unmoved;
    }
}
