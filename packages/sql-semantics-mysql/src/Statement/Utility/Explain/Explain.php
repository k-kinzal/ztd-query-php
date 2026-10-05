<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Explain;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Utility\ExplainFacts;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * EXPLAIN (DESCRIBE, DESC) with a statement: the execution plan of a SELECT, TABLE, VALUES, INSERT, REPLACE, UPDATE or DELETE.
 *
 * Rule: MYSQL-EXPLAIN-001. The explained statement is inspected, not run:
 * every part of it is derived as usual, so its name resolutions and
 * diagnostics are facts of the operation, but the rows it would return are
 * discarded. FOR DATABASE (8.2 and later) explains it as if that database
 * were the current one: it is derived with that database searched for
 * unqualified names. ANALYZE (8.0.18 and later) runs the statement and
 * reports the measured plan. The rows of the report follow
 * MYSQL-EXPLAIN-ROWS-001. Terminates: the explained statement is a strict
 * subtree.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/explain.html. Status: Implemented.
 *
 * @visibility public
 * @example Inspecting a query and reading the report columns
 *     $explain = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('EXPLAIN FORMAT = TREE SELECT 1');
 *     [$explain->field(0)->name?->value, $explain->toString()] // => ['EXPLAIN', 'EXPLAIN FORMAT = TREE SELECT 1']
 */
final class Explain implements Statement
{
    use Snapshot;

    /**
     * @param Statement $statement The explained statement
     * @param Name|null $format The format name after FORMAT =
     * @param bool $analyze Whether ANALYZE is written
     * @param ExplainModifier|null $modifier The EXTENDED or PARTITIONS keyword of MySQL 5.x
     * @param UserVariable|null $into The variable after INTO
     * @param Name|null $database The database after FOR DATABASE
     */
    public function __construct(
        public readonly Statement $statement,
        public readonly ?Name $format = null,
        public readonly bool $analyze = false,
        public readonly ?ExplainModifier $modifier = null,
        public readonly ?UserVariable $into = null,
        public readonly ?Name $database = null,
    ) {
        Check::input($modifier === null || ($format === null && !$analyze && $into === null && $database === null), 'EXTENDED and PARTITIONS are written alone.');
    }

    /**
     * Inspects the explained statement and records the rows of the report.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $context = $derivation->context;
        $derivation->inspected($this->statement, $this->database === null ? null : new Environment(new AnalysisContext($context->profile, [$this->database], $context->tables, $context->complete, $context->relationNames, $context->columnNames, $context->declarationSchema)));
        (new ExplainFacts())->derive($derivation, $this->format, $this->analyze, $this->modifier, $this->into);
    }

    /**
     * Writes EXPLAIN, the options and the explained statement.
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
        if ($this->database !== null) {
            $out->keyword('FOR', 'DATABASE')->name($this->database, NameUse::Qualifier);
        }
        $out->node($this->statement);
    }
}
