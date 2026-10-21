<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\TableDefinition\Index;

use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Deferred\ExpressionPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\KeyPart;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;

/**
 * Separates ordinary index definitions from retained name and operand conditions.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Roles
{
    /**
     * @param list<KeyPart> $parts Complete ordered index operands
     */
    public function request(\SqlSemantics\Statement\Identifier\Name $name, array $parts, \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind $kind = \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind::Index): bool
    {
        return (new Names())->problem($name) !== null || $this->parts($parts) || (new Shapes())->problem($kind, $parts) !== null;
    }

    /**
     * @param list<KeyPart> $parts Complete ordered index operands
     */
    public function parts(array $parts): bool
    {
        return array_filter($parts, static fn (KeyPart $part): bool => $part instanceof ExpressionPart || ($part instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\ArrayPart && (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Key\Operands())->contains($part->expression))) !== [];
    }

    /**
     * @param list<TableElement> $elements Complete table declarations
     */
    public function elements(array $elements): bool
    {
        return array_filter($elements, fn (TableElement $element): bool => $element instanceof IndexDefinition && ((new Names())->definition($element) !== null || $this->parts($element->parts) || (new Shapes())->problem($element->kind, $element->parts) !== null)) !== [];
    }

    /**
     * @param list<AlterCommand> $commands Complete alteration actions
     */
    public function commands(array $commands): bool
    {
        return array_filter($commands, fn (AlterCommand $command): bool => $command instanceof AddConstraint && $this->elements([$command->element])) !== [];
    }
}
