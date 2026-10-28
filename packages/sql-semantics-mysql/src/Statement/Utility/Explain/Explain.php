<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Explain;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Utility\ExplainFacts;
use SqlSemantics\Platform\MySql\Statement\Explainable;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;

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
 * @example Retaining an unknown format for execution validation
 *     $query = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'))]));
 *     (new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain($query, new \SqlSemantics\Statement\Identifier\Name('XML')))->format?->value // => 'XML'
 * @example Refusing a legacy modifier around a modern DELETE alias
 *     $target = new \SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), alias: new \SqlSemantics\Statement\Identifier\Name('x'));
 *     $delete = new \SqlSemantics\Platform\MySql\Statement\Dml\Delete(null, [], $target);
 *     new \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain($delete, modifier: \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier::Partitions) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Inspecting a query and reading the report columns
 *     $explain = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('EXPLAIN FORMAT = TREE SELECT 1');
 *     [$explain->field(0)->name?->value, $explain->toString()] // => ['EXPLAIN', 'EXPLAIN FORMAT = TREE SELECT 1']
 */
final class Explain extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Explainable $statement The explained statement
     * @param Name|null $format The format name after FORMAT =
     * @param bool $analyze Whether ANALYZE is written
     * @param ExplainModifier|null $modifier The EXTENDED or PARTITIONS keyword of MySQL 5.x
     * @param UserVariable|null $into The variable after INTO
     * @param Name|null $database The database after FOR DATABASE
     * @param bool $emptyInto Whether the ignored empty INTO spelling of MySQL 8.2–8.3 is present
     */
    public function __construct(
        public readonly Explainable $statement,
        public readonly ?Name $format = null,
        public readonly bool $analyze = false,
        public readonly ?ExplainModifier $modifier = null,
        public readonly ?UserVariable $into = null,
        public readonly ?Name $database = null,
        public readonly bool $emptyInto = false,
        ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null,
        public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null,
        public readonly ExplainVerb $verb = ExplainVerb::Explain,
    ) {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        Check::input(!$emptyInto || ($into === null && !$analyze && $modifier === null), 'An empty EXPLAIN destination has no assignment, ANALYZE or legacy modifier.');
        Check::input((new ExplainFacts())->target($into), 'An EXPLAIN INTO target has between 1 and 64 characters.');
        Check::input($database === null || mb_strlen($database->value, 'UTF-8') <= 64, 'An EXPLAIN database name has at most 64 characters.');
        Check::input(!$analyze || strcasecmp($format->value ?? '', 'TRADITIONAL') !== 0, 'EXPLAIN ANALYZE cannot use the TRADITIONAL format.');
        Check::input($modifier === null || ($format === null && !$analyze && $into === null && $database === null), 'EXTENDED and PARTITIONS are written alone.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Inspects the explained statement and records the rows of the report.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $context = $derivation->context;
        $late = $context->profile->grammar === \SqlSemantics\Contract\GrammarRelease::MySql810 && $this->into !== null && (new ExplainFacts())->known($this->format);
        if (!$late) {
            (new ExplainFacts())->derive($derivation, $this->format, $this->analyze, $this->modifier, $this->into);
        }
        $derivation->inspected($this->statement, $this->database === null ? null : new Environment(new AnalysisContext($context->profile, [$this->database], $context->tables, $context->complete, $context->relationNames, $context->columnNames, $context->declarationSchema, $context->session, $context->foldedSchemas)));
        if ($late) {
            (new ExplainFacts())->derive($derivation, $this->format, $this->analyze, $this->modifier, $this->into);
        }
    }

    /**
     * Writes EXPLAIN, the options and the explained statement.
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
        if ($this->database !== null) {
            $out->keyword('FOR', 'DATABASE')->name($this->database, NameUse::Qualifier);
        }
        $out->node($this->statement);
    }
}
