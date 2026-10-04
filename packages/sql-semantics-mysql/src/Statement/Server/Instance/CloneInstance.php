<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Instance;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `CLONE INSTANCE FROM user@host:port IDENTIFIED BY 'password' [DATA DIRECTORY [=] 'directory'] [REQUIRE [NO] SSL]`: a request to clone a remote server (MySQL 8.0.17 and later).
 *
 * Mirrors Sql_cmd_clone for a remote clone. Rule: MYSQL-CLONE-INSTANCE-001.
 * The account, the colon and the port are written without spaces: the
 * server rejects a space around the colon as a syntax error. Without a
 * directory the clone replaces the data of the local server; without an
 * SSL choice the server's clone_ssl settings decide. The equals sign is
 * optional and not written. The statement names no relation and has no
 * facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/clone.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Cloning a remote server into a directory
 *     $clone = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("clone instance from 'u'@'h':3306 identified by 'p' data directory '/d' require no ssl");
 *     [$clone->toString(), $clone->statement->port->text, $clone->statement->requireSsl] // => ["CLONE INSTANCE FROM u@h:3306 IDENTIFIED BY 'p' DATA DIRECTORY '/d' REQUIRE NO SSL", '3306', false]
 */
final class CloneInstance implements Statement
{
    use Snapshot;

    /**
     * @param Account $donor The account the clone connects as, with the donor's host
     * @param Numeral $port The donor's port
     * @param Text $password The account's password
     * @param Text|null $directory The directory the data is cloned into, when written
     * @param bool|null $requireSsl Whether REQUIRE SSL (true) or REQUIRE NO SSL (false) is written; null when absent
     */
    public function __construct(
        public readonly Account $donor,
        public readonly Numeral $port,
        public readonly Text $password,
        public readonly ?Text $directory = null,
        public readonly ?bool $requireSsl = null,
    ) {
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
        $out->keyword('CLONE', 'INSTANCE', 'FROM')->node($this->donor)->glue()->symbol(':')->glue()->node($this->port);
        $out->keyword('IDENTIFIED', 'BY')->node($this->password);
        if ($this->directory !== null) {
            $out->keyword('DATA', 'DIRECTORY')->node($this->directory);
        }
        if ($this->requireSsl !== null) {
            $out->keyword(...($this->requireSsl ? ['REQUIRE', 'SSL'] : ['REQUIRE', 'NO', 'SSL']));
        }
    }
}
