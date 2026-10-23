<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Replication\Group;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Replication\GroupCredentials;
use SqlSemantics\Platform\MySql\Rules\Replication\Releases;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * `START GROUP_REPLICATION [USER = …, PASSWORD = …, DEFAULT_AUTH = …]`: starts group replication (MySQL 5.7 and later).
 *
 * Mirrors SQLCOM_START_GROUP_REPLICATION with LEX::replica_connection; the
 * options are kept in written order, an option possibly written twice. The
 * options need MySQL 8.0 or later. Rule: MYSQL-GROUP-REPLICATION-001. Explicit
 * credentials must name a nonempty user when a password is supplied, and
 * passwords must fit the 32-byte limit.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/start-group-replication.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Starting with credentials
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("start group_replication user = 'u', password = 'p'")->toString() // => "START GROUP_REPLICATION USER = 'u', PASSWORD = 'p'"
 */
final class StartGroupReplication implements Statement
{
    use Snapshot;

    /**
     * @var list<CredentialOption> The options in written order
     */
    public readonly array $options;

    /**
     * @param list<CredentialOption> $options The options in written order
     */
    public function __construct(array $options = [])
    {
        $this->options = Check::listOf($options, CredentialOption::class, 'START GROUP_REPLICATION sets a list of options.');
        Check::input((new GroupCredentials())->problem($this->options) === null, 'Group recovery credentials require a nonempty user with a password of at most 32 bytes.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Checks the release and reports refused values.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $release = $derivation->context->profile->grammar;
        Check::input($release !== GrammarRelease::MySql5651, 'Group replication needs MySQL 5.7 or later.');
        Check::input($this->options === [] || !(new Releases())->legacy($release), 'The options of START GROUP_REPLICATION need MySQL 8.0 or later.');

    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('START', 'GROUP_REPLICATION')->list($this->options);
    }
}
