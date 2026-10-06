<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\ForeignServer;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `ALTER SERVER name OPTIONS (option, …)`: a request to change the options of a FEDERATED server.
 *
 * Mirrors SQLCOM_ALTER_SERVER with Server_options. Rule:
 * MYSQL-ALTER-SERVER-001. Only the options written change.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-server.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Changing the user of a server
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("alter server s options (user 'u')")->toString() // => "ALTER SERVER s OPTIONS (USER 'u')"
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
