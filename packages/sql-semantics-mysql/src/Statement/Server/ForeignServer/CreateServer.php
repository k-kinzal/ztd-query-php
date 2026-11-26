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
 * `CREATE SERVER name FOREIGN DATA WRAPPER wrapper OPTIONS (option, …)`: a request to define a server for the FEDERATED engine.
 *
 * Mirrors SQLCOM_CREATE_SERVER with Server_options. Rule:
 * MYSQL-CREATE-SERVER-001. The name and the wrapper are written as
 * identifiers or strings; the server compares server names
 * case-insensitively. The options are kept in written order. The statement
 * declares no relation.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-server.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CreateServer implements Statement
{
    use Snapshot;

    /**
     * @var list<ServerOption> The options in written order; at least one
     */
    public readonly array $options;

    /**
     * @param Name $name The server name
     * @param Name $wrapper The wrapper name
     * @param list<ServerOption> $options The options in written order; at least one
     * @throws InvalidConstruction When there is no option
     */
    public function __construct(public readonly Name $name, public readonly Name $wrapper, array $options)
    {
        $this->options = Check::listOf($options, ServerOption::class, 'CREATE SERVER takes at least one option.', 1);
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
        $out->keyword('CREATE', 'SERVER')->name($this->name, NameUse::Identifier)->keyword('FOREIGN', 'DATA', 'WRAPPER')->name($this->wrapper, NameUse::Identifier);
        $out->keyword('OPTIONS')->symbol('(')->list($this->options)->symbol(')');
    }
}
