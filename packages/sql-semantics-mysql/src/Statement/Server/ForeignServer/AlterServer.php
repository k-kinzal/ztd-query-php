<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\ForeignServer;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;

/**
 * `ALTER SERVER name OPTIONS (option, …)`: a request to change the options of a FEDERATED server.
 *
 * Mirrors SQLCOM_ALTER_SERVER with Server_options. Rule:
 * MYSQL-ALTER-SERVER-001. Only the options written change.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-server.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterServer implements Statement
{
    use Snapshot;

    /**
     * @var list<ServerOption> The options in written order; at least one
     */
    public readonly array $options;

    /**
     * @param Name $name The server name
     * @param list<ServerOption> $options The options in written order; at least one
     * @throws InvalidConstruction When there is no option
     */
    public function __construct(public readonly Name $name, array $options)
    {
        $this->options = Check::listOf($options, ServerOption::class, 'ALTER SERVER takes at least one option.', 1);
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
        $out->keyword('ALTER', 'SERVER')->name($this->name, NameUse::Identifier)->keyword('OPTIONS')->symbol('(')->list($this->options)->symbol(')');
    }
}
