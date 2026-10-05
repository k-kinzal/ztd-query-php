<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to remove a user mapping.
 *
 * Rule: PG-USER-MAPPING-003. Mirrors `DropUserMappingStmt`.
 * Source: https://www.postgresql.org/docs/17/sql-dropusermapping.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a user mapping
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP USER MAPPING IF EXISTS FOR CURRENT_USER SERVER s');
 *     $operation->toString() // => 'DROP USER MAPPING IF EXISTS FOR CURRENT_USER SERVER s'
 */
final class DropUserMapping implements Statement
{
    use Snapshot;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param RoleSpec|MappingUser $user The user
     * @param Name $server The foreign server
     */
    public function __construct(public readonly bool $ifExists, public readonly RoleSpec|MappingUser $user, public readonly Name $server)
    {
    }

    /**
     * Derives nothing: a user mapping is not part of a declaration context.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', 'USER', 'MAPPING');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->keyword('FOR')->node($this->user)->keyword('SERVER')->name($this->server, NameUse::Column);
    }
}
