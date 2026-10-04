<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\ForeignServer;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `DROP SERVER [IF EXISTS] name`: a request to remove a FEDERATED server definition.
 *
 * Mirrors SQLCOM_DROP_SERVER. Rule: MYSQL-DROP-SERVER-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-server.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Removing a server
 *     $drop = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("drop server if exists 's'");
 *     [$drop->toString(), $drop->statement->name->value] // => ['DROP SERVER IF EXISTS s', 's']
 */
final class DropServer implements Statement
{
    use Snapshot;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param Name $name The server name
     */
    public function __construct(public readonly bool $ifExists, public readonly Name $name)
    {
    }

    /**
     * Has nothing to derive: a server is no relation.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', 'SERVER');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->name($this->name, NameUse::Identifier);
    }
}
