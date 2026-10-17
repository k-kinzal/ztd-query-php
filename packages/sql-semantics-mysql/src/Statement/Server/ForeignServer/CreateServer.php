<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\ForeignServer;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

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
 * @visibility public
 * @example Refusing a definition with an empty server name
 *     new \SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\CreateServer(new \SqlSemantics\Statement\Identifier\Name(''), new \SqlSemantics\Statement\Identifier\Name('mysql'), [new \SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOption(\SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOptionKind::Host, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('localhost'))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Defining a server
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("create server s foreign data wrapper mysql options (host 'db', port 3307)");
 *     [$create->toString(), $create->statement->options[1]->kind] // => ["CREATE SERVER s FOREIGN DATA WRAPPER mysql OPTIONS (HOST 'db', PORT 3307)", \SqlSemantics\Platform\MySql\Statement\Server\ForeignServer\ServerOptionKind::Port]
 */
final class CreateServer implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<ServerOption> The options in written order; at least one
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
        Check::input($name->value !== '', 'A created foreign server has a nonempty name.');
        $this->options = Check::listOf($options, ServerOption::class, 'CREATE SERVER takes at least one option.', 1);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the character conversion of system-string operands.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new \SqlSemantics\Platform\MySql\Rules\Server\ForeignStrings())->derive($this, $derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'SERVER')->name($this->name, NameUse::Label)->keyword('FOREIGN', 'DATA', 'WRAPPER')->name($this->wrapper, NameUse::Label);
        $out->keyword('OPTIONS')->symbol('(')->list($this->options)->symbol(')');
    }
}
