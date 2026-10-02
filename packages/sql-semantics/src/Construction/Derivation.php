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

    /**
     * @param AnalysisContext $context The fixed declaration context every part is derived against
     */
    public function __construct(public readonly AnalysisContext $context)
    {
    }

    /**
     * Answers the environment of a statement position that sees no relation.
     */
    public function environment(): Environment
    {
        return new Environment($this->context);
    }

    /**
     * Derives a statement nested in or equal to the root.
     */
    public function statement(Statement $node): void
    {
        $node->deriveStatement($this);
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
