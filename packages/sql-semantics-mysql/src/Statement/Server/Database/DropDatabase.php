<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Database;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

/**
 * `DROP {DATABASE | SCHEMA} [IF EXISTS] name`: a request to remove a database and every table in it.
 *
 * Mirrors SQLCOM_DROP_DB. Rule: MYSQL-DROP-DATABASE-001. The statement
 * removes no declaration from the context it is analyzed against.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-database.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Removing a database
 *     $drop = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('drop schema if exists shop');
 *     [$drop->toString(), $drop->ifExists] // => ['DROP DATABASE IF EXISTS shop', true]
 */
final class DropDatabase extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param bool $ifExists Whether IF EXISTS is written
     * @param Name $name The database name
     */
    public function __construct(public readonly bool $ifExists, public readonly Name $name, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Has nothing to derive: a database is no relation.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', 'DATABASE');
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        $out->name($this->name, NameUse::Qualifier);
    }
}
