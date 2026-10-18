<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Rules\Utility\ExplainFacts;
use SqlSemantics\Platform\MySql\Statement\Explainable;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A complete stored explanation whose operands require invocation-time preparation.
 *
 * Reaching this instruction prepares the explained statement before its output
 * target. MySQL 8.1–8.3 also defers invalid target names until invocation.
 * This is a program instruction, never a standalone Statement.
 *
 * @visibility public
 * @example Retaining a complete explanation in a stored body
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql, 'mysql-8.1.0'))->analyze("CREATE PROCEDURE p() EXPLAIN FORMAT=JSON INTO @'' SELECT 1");
 *     $operation->statement->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Server\DeferredExplain // => true
 */
final class DeferredExplain implements ProgramStatement
{
    use Snapshot;

    /**
     * @param Explainable|DeferredSelection|DeferredQuery $statement The complete explained statement, including deferred query operands
     * @param Name|null $format The explicit report format, if present
     * @param UserVariable|null $into The retained output target
     * @param Name|null $database The optional database used to bind the explained statement
     * @param bool $analyze Whether the explanation measures execution
     * @param \SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier|null $modifier The optional legacy report modifier
     * @param bool $emptyInto Whether the ignored empty INTO spelling of 8.2–8.3 is present
     */
    public function __construct(public readonly Explainable|DeferredSelection|DeferredQuery $statement, public readonly ?Name $format = null, public readonly ?UserVariable $into = null, public readonly ?Name $database = null, public readonly bool $analyze = false, public readonly ?\SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainModifier $modifier = null, public readonly bool $emptyInto = false)
    {
        Check::input((new ExplainFacts())->known($format), 'An EXPLAIN format is TRADITIONAL, JSON or TREE.');
        Check::input($into === null || strcasecmp($format->value ?? '', 'JSON') === 0, 'EXPLAIN INTO has an explicit JSON format.');
        Check::input(!$emptyInto || ($into === null && !$analyze && $modifier === null), 'An empty EXPLAIN destination has no assignment, ANALYZE or legacy modifier.');
        Check::input($modifier === null || ($format === null && !$analyze && $into === null && $database === null && !$emptyInto), 'EXTENDED and PARTITIONS are written alone.');
        Check::input(!$statement instanceof Explainable || !(new ExplainFacts())->target($into) || ($analyze && strcasecmp($format->value ?? '', 'TRADITIONAL') === 0), 'A deferred explanation retains an invocation-time operand constraint.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds the explained dependencies without validating an unreached target.
     */
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        if ($this->into !== null) {
            $derivation->scalar($this->into, $scope->environment);
        }
        if ($this->database !== null) {
            $environment = $scope->environment;
            $context = $environment->context;
            $context = new \SqlSemantics\Contract\AnalysisContext($context->profile, [$this->database], $context->tables, $context->complete, $context->relationNames, $context->columnNames, $context->declarationSchema, $context->session, $context->foldedSchemas);
            $environment = new \SqlSemantics\Resolution\Environment($context, $environment->outer, $environment->relations, $environment->commonTables, $environment->aliases, $environment->written, $environment->aggregation, $environment->aggregatesAllowed, $environment->aggregateArgument, $environment->projection);
            $scope = $scope->declared($environment, $scope->conditions, $scope->cursors);
        }
        if ($this->statement instanceof ProgramStatement) {
            $this->statement->deriveProgram($derivation, $scope);
        } else {
            $derivation->inspected($this->statement, $scope->environment);
        }
    }

    /**
     * Writes the complete instruction and its output target.
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
        if ($this->emptyInto) {
            $out->keyword('INTO')->symbol('@')->glue()->spelled("''");
        }
        if ($this->database !== null) {
            $out->keyword('FOR', 'DATABASE')->name($this->database, NameUse::Qualifier);
        }
        $out->node($this->statement);
    }
}
