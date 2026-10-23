<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Explain;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Utility\ExplainFacts;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

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
 * Connection identifiers are integer tokens: decimal digits within the unsigned 64-bit
 * range or a hexadecimal literal. Fractional and exponent forms have no connection operand.
 * ANALYZE and INTO have no connection-explanation form; those options belong to Explain.
 *
 * @visibility public
 * @example Refusing a format outside the public format domain
 *     new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1'), new \SqlSemantics\Statement\Identifier\Name('XML')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a fractional connection identifier
 *     new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1.5')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a decimal connection identifier beyond the token range
 *     new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('18446744073709551616')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Reading the connection
 *     $explain = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->analyze('EXPLAIN FORMAT = JSON FOR CONNECTION 42');
 *     [$explain->statement->connection->text, $explain->field(0)->name?->value, $explain->toString()] // => ['42', 'EXPLAIN', 'EXPLAIN FORMAT = `JSON` FOR CONNECTION 42']
 */
final class ExplainConnection implements Statement
{
    use Snapshot;

    /**
     * The resolved unsigned 32-bit thread identity, independent of its numeric spelling.
     */
    public readonly int $connectionId;

    /**
     * @param Numeral $connection The connection identifier
     * @param Name|null $format The format name after FORMAT =
     * @param ExplainModifier|null $modifier The EXTENDED or PARTITIONS keyword of MySQL 5.7
     */
    public function __construct(
        public readonly Numeral $connection,
        public readonly ?Name $format = null,
        public readonly ?ExplainModifier $modifier = null,
    ) {
        $this->connectionId = (new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes())->connection($connection);
        Check::input((new ExplainFacts())->known($format), 'An EXPLAIN format is TRADITIONAL, JSON or TREE.');
        Check::input($modifier === null || $format === null, 'EXTENDED and PARTITIONS are written alone.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Records the rows of the report.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ExplainFacts())->derive($derivation, $this->format, false, $this->modifier, null);
    }

    /**
     * Writes EXPLAIN, the options and the connection.
     */
    public function render(Output $out): void
    {
        $out->keyword('EXPLAIN');
        if ($this->modifier !== null) {
            $out->keyword($this->modifier->value);
        }
        if ($this->format !== null) {
            $out->keyword('FORMAT')->symbol('=')->name($this->format, NameUse::Label);
        }
        $out->keyword('FOR', 'CONNECTION')->node($this->connection);
    }
}
