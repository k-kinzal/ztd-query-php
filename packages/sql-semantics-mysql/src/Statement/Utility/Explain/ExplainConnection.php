<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Explain;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Utility\ExplainFacts;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;

/**
 * EXPLAIN FOR CONNECTION: the plan of the statement another connection is running (MySQL 5.7 and later).
 *
 * Rule: MYSQL-EXPLAIN-CONNECTION-001. The connection is a thread number
 * as SHOW PROCESSLIST reports it; the statement it runs is not part of the
 * request. The options and the rows of the report follow
 * MYSQL-EXPLAIN-ROWS-001. Terminates: no nested statement.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/explain-for-connection.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ExplainConnection implements Statement
{
    use Snapshot;

    /**
     * @param Numeral $connection The connection identifier
     * @param Name|null $format The format name after FORMAT =
     * @param bool $analyze Whether ANALYZE is written
     * @param ExplainModifier|null $modifier The EXTENDED or PARTITIONS keyword of MySQL 5.7
     * @param UserVariable|null $into The variable after INTO
     */
    public function __construct(
        public readonly Numeral $connection,
        public readonly ?Name $format = null,
        public readonly bool $analyze = false,
        public readonly ?ExplainModifier $modifier = null,
        public readonly ?UserVariable $into = null,
    ) {
        Check::input($modifier === null || ($format === null && !$analyze && $into === null), 'EXTENDED and PARTITIONS are written alone.');
    }

    /**
     * Records the rows of the report.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ExplainFacts())->derive($derivation, $this->format, $this->analyze, $this->modifier, $this->into);
    }

    /**
     * Writes EXPLAIN, the options and the connection.
     */
    public function render(Output $out): void
    {
        $out->keyword('EXPLAIN');
        if ($this->analyze) {
            $out->keyword('ANALYZE');
        }
        if ($this->modifier !== null) {
            $out->keyword($this->modifier->value);
        }
        if ($this->format !== null) {
            $out->keyword('FORMAT')->symbol('=')->name($this->format, NameUse::Label);
        }
        if ($this->into !== null) {
            $out->keyword('INTO')->node($this->into);
        }
        $out->keyword('FOR', 'CONNECTION')->node($this->connection);
    }
}
