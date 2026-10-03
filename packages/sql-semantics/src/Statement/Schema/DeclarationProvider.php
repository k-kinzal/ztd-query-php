<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema;

use SqlSemantics\Statement\Operation;

/**
 * An operation that describes new table declarations without executing a schema change.
 * @visibility public
 * @example Retrieving the declaration represented by a CREATE operation
 *     $column = new \SqlSemantics\Statement\Schema\Definition\SqliteColumnDefinition(new \SqlSemantics\Statement\Identifier\Name('id'), new \SqlSemantics\Statement\Type\SqliteDeclaration('INTEGER'));
 *     $create = new \SqlSemantics\Statement\Schema\Definition\SqliteCreateTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('users')), columns: $column);
 *     $create->declaredTables()[0] === $create->table // => true
 */
interface DeclarationProvider extends Operation
{
    /**
     * Returns this operation's own declarations, with their original identities.
     * @return list<Table>
     */
    public function declaredTables(): array;
}
