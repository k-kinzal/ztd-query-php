<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Session;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * SHOW PROFILES: the statements the session profiled.
 *
 * Rule: MYSQL-SHOW-PROFILES-001. The columns are those of the layout of MYSQL-SHOW-ROWS-001.
 * Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-profiles.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the statement
 *     $show = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SHOW PROFILES');
 *     [$show->field(0)->name?->value, $show->toString()] // => ['Query_ID', 'SHOW PROFILES']
 */
final class ShowProfiles extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;


    /**
     * Derives the rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        \SqlSemantics\Platform\MySql\Statement\Notice\Deprecation::raise(\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::ShowProfiles, $derivation, $this);
        (new ShowFacts())->rows($derivation, Report::Profiles);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'PROFILES');
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
