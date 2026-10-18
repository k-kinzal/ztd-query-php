<?php

declare(strict_types=1);

namespace SqlSemantics\Construction;

use Closure;
use SplObjectStorage;
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
use SqlSemantics\Statement\Fact\Warning;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Reference\Table\TableResolution;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\Field;
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
     * @var list<array{Operand, ScalarFact}>
     */
    private array $scalars = [];

    /**
     * @var list<array{Node, RelationFact}>
     */
    private array $relations = [];

    /**
     * @var array<int, array{QualifiedName, Relation}> Names bound to input occurrences
     */
    private array $references = [];

    /**
     * @var list<array{Query, QueryFact}>
     */
    private array $queries = [];

    /**
     * @var list<Table>
     */
    private array $declarations = [];

    private readonly Conditions $conditions;

    private readonly Warnings $warnings;

    /**
     * @var array<string, \SqlSemantics\Statement\Type\TypeFact> Variable entries introduced while resolving this statement, keyed by platform-normalized name
     */
    public array $introducedVariables = [];

    /**
     * @var SplObjectStorage<Query, null> Query blocks whose rows are tested for existence
     */
    public readonly SplObjectStorage $existenceQueries;

    private ?Node $reading = null;

    private ?QueryFact $output = null;

    private ?Environment $base = null;

    private readonly InstructionScope $instructions;

    /**
     * @var array<int, array{list<Field>, bool}> The columns a statement writes the rows of a query into, and whether an empty row writes the defaults, by the object id of the query
     */
    private array $targets = [];

    /**
     * @param AnalysisContext $context The fixed declaration context every part is derived against
     * @param \SqlSemantics\Statement\Source\SourceMap $sources Original spelling notices and optional occurrence ranges
     * @param list<Operand> $parameters Parameter occurrences in written order, supplied by the structural renderer
     */
    public function __construct(public AnalysisContext $context, public readonly \SqlSemantics\Statement\Source\SourceMap $sources = new \SqlSemantics\Statement\Source\SourceMap(), private readonly array $parameters = [])
    {
        $this->warnings = new Warnings($sources);
        $this->conditions = new Conditions();
        $this->instructions = new InstructionScope();
        $this->existenceQueries = new SplObjectStorage();
    }

    /**
     * Answers a parameter's written position, independent of the order facts are derived.
     */
    public function parameterPosition(Operand $parameter): ?int
    {
        $position = array_search($parameter, $this->parameters, true);

        return $position === false ? null : $position;
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
        $this->statement($node);
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
        $outer = $this->reading;
        $this->reading = $node;
        $node->deriveStatement($this);
        $this->reading = $outer;
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
        $this->statement($node);
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
        $this->statement($node);
        [$this->output, $this->declarations, $this->context, $this->base] = [$output, $declarations, $context, $base];
    }

    /**
     * Derives a statement of a stored program in its environment, like inspected().
     *
     * The statement is stored for later: while it is derived, inProgram() answers true, so rules
     * that the server applies only when a statement runs can leave it alone.
     */
    public function program(Statement $node, Environment $environment): void
    {
        $this->stored($node, fn () => $this->inspected($node, $environment));
    }

    /**
     * Derives a stored language member with explicit instruction ownership.
     *
     * @param Closure(): void $derive Derives this member in its dialect's program scope
     * @param-immediately-invoked-callable $derive
     */
    public function stored(Node $node, Closure $derive): void
    {
        $this->instructions->within($node, $derive);
    }

    /**
     * Answers whether the statement being derived belongs to a stored program rather than running now.
     */
    public function inProgram(): bool
    {
        return $this->instructions->current() !== null;
    }

    /**
     * Records that the statement writes the rows of a query into the given columns, as an INSERT writes the rows of its source.
     *
     * The query is derived later as any other; written() lets its rules
     * accept what the database accepts only in rows that are written, such
     * as a value that stands for the default of its column.
     *
     * @param list<Field> $columns The written columns in row order
     * @param bool $defaults Whether an empty row writes the defaults of every column
     */
    public function writes(Query $node, array $columns, bool $defaults): void
    {
        $this->targets[spl_object_id($node)] = [$columns, $defaults];
    }

    /**
     * Answers the columns the statement writes the rows of a query into and whether an empty row writes the defaults, or null when it does not write them.
     *
     * @return array{list<Field>, bool}|null
     */
    public function written(Query $node): ?array
    {
        return $this->targets[spl_object_id($node)] ?? null;
    }

    /**
     * Derives and records the facts of a scalar expression at its position.
     */
    public function scalar(Operand $node, Environment $environment): ScalarFact
    {
        $outer = $this->reading;
        $this->reading = $node;
        $fact = $node->deriveScalar($this, $environment);
        $this->reading = $outer;
        $this->scalars[] = [$node, $fact];
        if ($fact->resolution instanceof Diagnostic) {
            $this->conditions->report($fact->resolution);
        }

        return $fact;
    }

    /**
     * Derives and records the facts of a relation occurrence.
     */
    public function relation(Relation $node, Environment $environment): RelationFact
    {
        $outer = $this->reading;
        $this->reading = $node;
        $fact = $node->deriveRelation($this, $environment);
        $this->reading = $outer;

        return $this->target($node, $fact);
    }

    /**
     * Records the facts of a table use that is not an input relation, such as a write target.
     */
    public function target(Node $node, RelationFact $fact): RelationFact
    {
        $this->relations[] = [$node, $fact];
        if ($fact->table instanceof Diagnostic) {
            $this->conditions->report($fact->table);
        }

        return $fact;
    }

    /**
     * Records the input occurrence a relation name denotes, preserving its identity for consumers.
     */
    public function bindReference(QualifiedName $name, Relation $relation): void
    {
        $this->references[spl_object_id($name)] = [$name, $relation];
    }

    /**
     * Derives and records the output of a query used at a position.
     */
    public function query(Query $node, Environment $outer): QueryFact
    {
        $previous = $this->reading;
        $this->reading = $node;
        $fact = $this->conditions->query(fn (): QueryFact => $node->deriveQuery($this, $outer));
        $this->reading = $previous;
        $this->queries[] = [$node, $fact];

        return $fact;
    }

    /**
     * Derives the parts of a definition whose problems surface only where the definition is used.
     *
     * Facts are recorded immediately. Diagnostics wait until table() first resolves
     * a use of the definition; an unused definition reports none of them.
     *
     * @template T
     *
     * @param Closure(): T $derive Derives the parts of the definition
     * @param Closure(Warning): bool|null $warnings Optional warning selection for an unused semantic role
     * @return T
     */
    public function deferred(Node $definition, Closure $derive, ?Closure $warnings = null): mixed
    {
        return $warnings === null ? $this->conditions->deferred($definition, $derive)
            : $this->warnings->filtered(fn () => $this->conditions->deferred($definition, $derive), $warnings);
    }

    /**
     * Resolves a relation name: the nearest common table of the environment, then the context.
     *
     * Resolving a name to a common table reports the diagnostics its query
     * withheld (deferred()), once.
     */
    public function table(QualifiedName $name, Environment $environment): TableResolution
    {
        if ($name->schema === null) {
            $common = $environment->commonTable($name->name);
            if ($common !== null) {
                $this->conditions->release($common->definition);

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
        $this->conditions->report($diagnostic);
    }

    /**
     * Records a condition the statement raises without failing.
     *
     * @param Node|null $at The occurrence whose input boundary orders this warning, or the occurrence currently being derived
     * @param bool $after Whether the warning follows that occurrence's input, rather than preceding it
     */
    public function warn(Warning $warning, ?Node $at = null, bool $after = true): void
    {
        $this->warnings->append($warning, $at ?? $this->reading, $after, $this->instructions->current());
    }

    /**
     * Merges input spelling notices with warnings whose semantic derivation records a source boundary.
     *
     * Unlocated warnings retain their relative emission order after located warnings. Without
     * spelling notices the established derivation order is retained unchanged.
     *
     * @return list<Warning>
     */
    public function readingWarnings(): array
    {
        return $this->warnings->all();
    }

    /**
     * Orders the located warnings of this statement by input position after its dependencies are derived.
     */
    public function orderWarnings(Node $scope): void
    {
        $this->warnings->order($scope);
    }

    /**
     * Freezes the recorded facts.
     */
    public function facts(): Facts
    {
        return new Facts($this->scalars, $this->relations, $this->queries, $this->declarations, $this->output, $this->conditions->all(), $this->readingWarnings(), $this->warnings->instructions(), array_values($this->references));
    }
}
