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

/**
 * SHOW CHARACTER SET (SHOW CHARSET): the character sets of the server.
 *
 * Rule: MYSQL-SHOW-CHARACTERSET-001. CHARACTER SET, CHAR SET and CHARSET are the same keyword
 * (LeafNoise); the writer emits CHARSET. The rows and the WHERE condition
 * are derived by MYSQL-SHOW-FACTS-001 from the layout of
 * MYSQL-SHOW-ROWS-001. Terminates: a fixed layout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-character-set.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ShowCharacterSet implements Statement, Relation
{
    use Snapshot;

    /**
     * @param ShowLike|ShowWhere|null $filter The LIKE or WHERE clause
     */
    public function __construct(public readonly ShowLike|ShowWhere|null $filter = null)
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
        return (new ShowFacts())->fact($derivation, Report::Charsets);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('SHOW', 'CHARSET')->node($this->filter);
    }
}
