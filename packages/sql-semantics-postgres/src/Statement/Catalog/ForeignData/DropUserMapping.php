<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec;

/**
 * A request to remove a user mapping.
 *
 * Rule: PG-USER-MAPPING-003. Mirrors `DropUserMappingStmt`.
 * Source: https://www.postgresql.org/docs/17/sql-dropusermapping.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
