<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition;

use SqlSemantics\Model\Module\ConstructorArgument;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\CreateVirtualTableStatement;

/**
 * Writes a module call while preserving the text values passed to its constructor.
 * @visibility SqlSemantics
 */
final class VirtualTables
{
    /**
     * The module argument boundary was checked when each argument was constructed.
     */
    public static function write(CreateVirtualTableStatement $statement): Tree
    {
        $dialect = $statement->origin->dialect;
        $arguments = array_map(static fn (ConstructorArgument $argument): Tree => new Tree('module-argument', [new Atom('module-argument', $argument->text)]), $statement->constructor->arguments);
        return new Tree('virtual-table', [Build::keyword('CREATE VIRTUAL TABLE'), ...($statement->ifNotExists ? [Build::keyword('IF NOT EXISTS')] : []), Build::identifier($statement->name->parts, $dialect), Build::keyword('USING'), Build::identifier([$statement->constructor->module], $dialect), ...($arguments === [] ? [] : [Build::parentheses(Build::separated($arguments))])]);
    }
}
