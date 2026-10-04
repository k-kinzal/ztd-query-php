<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Instance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `CLONE LOCAL DATA DIRECTORY [=] 'directory'`: a request to clone the local server's data into a directory (MySQL 8.0.17 and later).
 *
 * Mirrors Sql_cmd_clone for a local clone. Rule: MYSQL-CLONE-LOCAL-001. The
 * equals sign is optional and not written. The statement names no relation
 * and has no facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/clone.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Cloning into a directory
 *     $clone = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("clone local data directory = '/srv/copy'");
 *     [$clone->toString(), $clone->statement->directory->value] // => ["CLONE LOCAL DATA DIRECTORY '/srv/copy'", '/srv/copy']
 */
final class CloneLocal implements Statement
{
    use Snapshot;

    /**
     * @param Text $directory The directory the data is cloned into
     */
    public function __construct(public readonly Text $directory)
    {
    }

    /**
     * Has nothing to derive: the request names no relation and no value.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CLONE', 'LOCAL', 'DATA', 'DIRECTORY')->node($this->directory);
    }
}
