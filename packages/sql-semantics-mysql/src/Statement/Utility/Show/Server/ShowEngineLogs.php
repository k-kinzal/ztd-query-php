<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

/**
 * SHOW ENGINE … LOGS: the log files of a storage engine.
 *
 * Rule: MYSQL-SHOW-ENGINELOGS-001. The engine is a name the server resolves, or ALL for every
 * engine. The three SHOW ENGINE reports are distinct commands with the
 * same columns: those of the layout of MYSQL-SHOW-ROWS-001. Terminates: a
 * fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-engine.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SHOW ENGINE InnoDB LOGS');
 *     [$show->engine?->value, $show->toString()] // => ['InnoDB', 'SHOW ENGINE InnoDB LOGS']
 */
final class ShowEngineLogs extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param ?Name $engine The storage engine, or null for ALL
     */
    public function __construct(public readonly ?Name $engine = null, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowFacts())->rows($derivation, Report::Engine);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'ENGINE');
        if ($this->engine === null) {
            $out->keyword('ALL');
        } else {
            $out->name($this->engine, NameUse::Label);
        }
        $out->keyword('LOGS');
    }
}
