<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * SHOW ENGINES (SHOW STORAGE ENGINES): the storage engines of the server.
 *
 * Rule: MYSQL-SHOW-ENGINECATALOG-001. STORAGE is an optional word (LeafNoise). The columns are those
 * of the layout of MYSQL-SHOW-ROWS-001. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-engines.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SHOW STORAGE ENGINES');
 *     [$show->field(1)->name?->value, $show->toString()] // => ['Support', 'SHOW ENGINES']
 */
final class ShowEngineCatalog extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;


    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowFacts())->rows($derivation, Report::Engines);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'ENGINES');
    }
    /**
     * Constructs this SQL form with optional completed semantics and an input range.
     */
    public function __construct(?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
    }
}
