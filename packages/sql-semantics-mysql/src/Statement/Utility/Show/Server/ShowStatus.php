<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Show\Server;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\MySql\Model\Statement\Relation;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Utility\Report;
use SqlSemantics\Platform\MySql\Rules\Utility\ShowFacts;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;

/**
 * SHOW STATUS: the status variables of the server or the session.
 *
 * Rule: MYSQL-SHOW-STATUS-001. GLOBAL reports the global values, SESSION (or LOCAL) and no
 * scope the values of the session. LIKE matches the variable name. The
 * rows and the WHERE condition are derived by MYSQL-SHOW-FACTS-001 from
 * the layout of MYSQL-SHOW-ROWS-001. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-status.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ShowStatus implements Statement, Relation
{
    use Snapshot;

    /**
     * @param ?VariableScope $scope The scope keyword
     * @param ShowLike|ShowWhere|null $filter The LIKE or WHERE clause
     */
    public function __construct(public readonly ?VariableScope $scope = null, public readonly ShowLike|ShowWhere|null $filter = null)
    {
    }

    /**
     * Derives the rows and the filter.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ShowFacts())->derive($derivation, $this, $this->filter);
    }

    /**
     * Derives the result columns.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return (new ShowFacts())->fact($derivation, Report::Variables);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW');
        if ($this->scope !== null) {
            $out->keyword($this->scope->value);
        }
        $out->keyword('STATUS')->node($this->filter);
    }
}
