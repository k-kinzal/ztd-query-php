<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\ForeignServer;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

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
 *     [$drop->toString(), $drop->name->value] // => ['DROP SERVER IF EXISTS s', 's']
 */
final class DropServer extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param Name $name The server name
     */
    public function __construct(public readonly bool $ifExists, public readonly Name $name, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
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
        $out->keyword('DROP', 'SERVER');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->name($this->name, NameUse::Label);
    }
}
