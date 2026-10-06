<?php

declare(strict_types=1);

namespace SqlSemantics\Construction;

use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\TableLookup;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Reference\Table\TableResolution;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Statement;

/**
 * The one-time recorder of the facts of a statement under construction.
 *
 * Each node derives its own facts and asks the derivation to derive its
 * children, which records exactly one fact per node. The recorder lives for
 * one construction and is unreachable from the published operation.
 *
 * @visibility SqlSemantics
 */
final class Derivation
{
    /**
     * @var list<array{Scalar, ScalarFact}>
     */
    private array $scalars = [];

    /**
     * @var list<array{Node, RelationFact}>
     */
    private array $relations = [];

    /**
     * @var list<array{Query, QueryFact}>
     */
    private array $queries = [];

    /**
     * @var list<Table>
     */
    private array $declarations = [];

    /**
     * @var list<Diagnostic>
     */
    private array $diagnostics = [];

    private ?QueryFact $output = null;

    private ?Environment $base = null;

    /**
     * @param AnalysisContext $context The fixed declaration context every part is derived against
     */
    public function __construct(public AnalysisContext $context)
    {
    }

    /**
     * Derives a nested statement that the database reads with other name-search settings.
     *
     * The statement sees the declarations of the same profile under the
     * search settings of the given context, as the elements of a schema
     * definition are read with that schema searched first. The context may add
     * only declarations this statement has already provided, as the elements
     * of one schema definition see each other.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the context holds other declarations or another profile
     */
    public function within(AnalysisContext $context, Statement $node): void
    {
        $this->admit($context);
        [$outer, $base] = [$this->context, $this->base];
        [$this->context, $this->base] = [$context, null];
        $node->deriveStatement($this);
        [$this->context, $this->base] = [$outer, $base];
    }

    /**
     * Refuses a nested context that differs from this one other than in search settings and own declarations.
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the context holds other declarations or another profile
     */
    public function admit(AnalysisContext $context): void
    {
        Check::input($context->profile->compatibleWith($this->context->profile) && $context->complete === $this->context->complete, 'A nested statement is derived under the same profile and completeness.');
        foreach ($this->context->tables as $table) {
            Check::input(in_array($table, $context->tables, true), 'A nested statement sees every declaration of the enclosing context.');
        }
        foreach ($context->tables as $table) {
            Check::input(in_array($table, $this->context->tables, true) || in_array($table, $this->declarations, true), 'A nested statement sees only the declarations of the enclosing context and of the statement itself.');
        }
    }

    /**
     * Answers the environment of a statement position that sees no relation.
     */
    public function environment(): Environment
    {
        return $this->base ?? new Environment($this->context);
    }

    /**
     * Derives a statement nested in or equal to the root.
     */
    public function statement(Statement $node): void
    {
        $node->deriveStatement($this);
    }

    /**
     * Derives one member of a script.
     *
     * The member's declarations and diagnostics are kept; the rows it would
     * return are not: a script of several statements returns no single row
     * set of its own.
     */
    public function member(Statement $node): void
    {
        $output = $this->output;
        $this->output = null;
        $node->deriveStatement($this);
        $this->output = $output;
    }

    /**
     * Derives a statement whose request is only inspected or stored, such as the operand of EXPLAIN or a routine body.
     *
     * Every part of the statement receives its facts, and its diagnostics are
     * kept, but the rows it would return and the declarations it would
     * provide are discarded: inspecting or storing a statement neither
     * executes it nor declares anything. An environment, when given, is the
     * position the statement is read at instead of a statement root, such as
     * the variables of a routine body or the search settings of EXPLAIN FOR
     * DATABASE; its context follows the rule of within().
     *
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the environment holds other declarations or another profile
     */
    public function inspected(Statement $node, ?Environment $environment = null): void
    {
        [$output, $declarations, $context, $base] = [$this->output, $this->declarations, $this->context, $this->base];
        if ($environment !== null) {
            $this->admit($environment->context);
            [$this->context, $this->base] = [$environment->context, $environment];
        }
        $this->output = null;
        $node->deriveStatement($this);
        [$this->output, $this->declarations, $this->context, $this->base] = [$output, $declarations, $context, $base];
    }

    /**
     * Derives and records the facts of a scalar expression at its position.
     */
    public function scalar(Scalar $node, Environment $environment): ScalarFact
    {
        $fact = $node->deriveScalar($this, $environment);
        $this->scalars[] = [$node, $fact];
        if ($fact->resolution instanceof Diagnostic) {
            $this->diagnostics[] = $fact->resolution;
        }

        return $fact;
    }

    /**
     * Derives and records the facts of a relation occurrence.
     */
    public function relation(Relation $node, Environment $environment): RelationFact
    {
        return $this->target($node, $node->deriveRelation($this, $environment));
    }

    /**
     * Records the facts of a table use that is not an input relation, such as a write target.
     */
    public function target(Node $node, RelationFact $fact): RelationFact
    {
        $this->relations[] = [$node, $fact];
        if ($fact->table instanceof Diagnostic) {
            $this->diagnostics[] = $fact->table;
        }

        return $fact;
    }

    /**
     * Derives and records the output of a query used at a position.
     */
    public function query(Query $node, Environment $outer): QueryFact
    {
        $fact = $node->deriveQuery($this, $outer);
        $this->queries[] = [$node, $fact];

        return $fact;
    }

    /**
     * Resolves a relation name: the nearest common table of the environment, then the context.
     */
    public function table(QualifiedName $name, Environment $environment): TableResolution
    {
        if ($name->schema === null) {
            $common = $environment->commonTable($name->name);
            if ($common !== null) {
                return new CommonTable($common->definition);
            }
        }

        return (new TableLookup())->find($this->context, $name);
    }

    /**
     * Records a relation declaration the statement provides to a context.
     */
    public function declare(Table $table): void
    {
        $this->declarations[] = $table;
    }

    /**
     * Records the rows the root statement returns.
     */
    public function output(QueryFact $fact): void
    {
        Check::invariant($this->output === null, 'A statement has one output.');
        $this->output = $fact;
    }

    /**
     * Records a semantic problem that is not a resolution outcome.
     */
    public function report(Diagnostic $diagnostic): void
    {
        $this->diagnostics[] = $diagnostic;
    }

    /**
     * Freezes the recorded facts.
     */
    public function facts(): Facts
    {
        return new Facts($this->scalars, $this->relations, $this->queries, $this->declarations, $this->output, $this->diagnostics);
    }
}
