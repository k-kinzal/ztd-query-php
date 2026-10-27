<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Prepared;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

/**
 * DEALLOCATE PREPARE (or its synonym DROP PREPARE): releases a prepared statement.
 *
 * Rule: MYSQL-DEALLOCATE-001. The prepared statement is a session object;
 * the statement derives nothing and returns no rows. Terminates: no child.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/deallocate-prepare.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a DEALLOCATE PREPARE
 *     $deallocate = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('DROP PREPARE stmt');
 *     [$deallocate->name->value, $deallocate->toString()] // => ['stmt', 'DEALLOCATE PREPARE stmt']
 */
final class Deallocate extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Name $name The name of the prepared statement
     */
    public function __construct(public readonly Name $name, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives nothing: the statement holds no expression and returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('DEALLOCATE', 'PREPARE')->name($this->name, NameUse::Label);
    }
}
