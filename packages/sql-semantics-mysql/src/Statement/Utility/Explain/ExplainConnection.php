<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Explain;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Utility\ExplainFacts;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

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
 * Connection operands retain decimal, exponent and hexadecimal forms. Only integer
 * tokens within the unsigned 64-bit domain resolve to a connection identity.
 * ANALYZE and INTO remain part of the request even when the server refuses them.
 *
 * @visibility public
 * @example Refusing INTO with FOR CONNECTION while reading
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('EXPLAIN FORMAT=JSON INTO @v FOR CONNECTION 1') // throws \SqlSemantics\Construction\RejectedInput
 * @example Retaining a fractional connection operand
 *     (new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('1.5')))->connection->text // => '1.5'
 * @example Retaining a connection operand beyond the token range
 *     (new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection(new \SqlSemantics\Platform\MySql\Statement\Literal\Numeral('18446744073709551616')))->connection->text // => '18446744073709551616'
 * @example Reading the connection
 *     $explain = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-5.7.44'))->analyze('EXPLAIN FORMAT = JSON FOR CONNECTION 42');
 *     [$explain->connection->text, $explain->field(0)->name?->value, $explain->toString()] // => ['42', 'EXPLAIN', 'EXPLAIN FORMAT = JSON FOR CONNECTION 42']
 */
final class ExplainConnection extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * The resolved unsigned 32-bit identity, or null for a refused integer operand.
     */
    public readonly ?int $connectionId;

    /**
     * @param Numeral $connection The connection identifier
     * @param Name|null $format The format name after FORMAT =
     * @param ExplainModifier|null $modifier The EXTENDED or PARTITIONS keyword of MySQL 5.7
     * @param bool $analyze Whether ANALYZE is written
     * @param UserVariable|null $into The output variable, including refused names
     * @param bool $emptyInto Whether MySQL 8.2–8.3's ignored empty destination is written
     */
    public function __construct(
        public readonly Numeral $connection,
        public readonly ?Name $format = null,
        public readonly ?ExplainModifier $modifier = null,
        ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null,
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
        public readonly ExplainVerb $verb = ExplainVerb::Explain,
        public readonly bool $analyze = false,
        public readonly ?UserVariable $into = null,
        public readonly bool $emptyInto = false,
    ) {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        $magnitudes = new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes();
        $this->connectionId = $magnitudes->integral($connection) ? $magnitudes->connection($connection) : null;
        Check::input(!$emptyInto || $into === null, 'An ignored empty destination has no assignment.');
        Check::input(!$analyze && $into === null, 'EXPLAIN FOR CONNECTION takes neither ANALYZE nor INTO.');
        Check::input(in_array(strtoupper($format->value ?? 'TRADITIONAL'), ['TRADITIONAL', 'JSON', 'TREE'], true), 'An EXPLAIN format is TRADITIONAL, JSON or TREE.');
        Check::input($modifier === null || ($format === null && !$emptyInto), 'EXTENDED and PARTITIONS are written alone.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Records the rows of the report.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new \SqlSemantics\Platform\MySql\Rules\Server\Magnitudes())->integer($derivation, $this->connection);
        (new ExplainFacts())->derive($derivation, $this->format, $this->analyze, $this->modifier, $this->into, true);
    }

    /**
     * Writes EXPLAIN, the options and the connection.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->verb->value);
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
        if ($this->emptyInto) {
            $out->keyword('INTO')->symbol('@')->glue()->spelled("''");
        }
        $out->keyword('FOR', 'CONNECTION')->node($this->connection);
    }
}
