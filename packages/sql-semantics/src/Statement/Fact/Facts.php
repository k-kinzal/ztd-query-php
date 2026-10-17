<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Fact;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;

/**
 * Every context-dependent fact an operation derived, keyed by the statement node it is about.
 *
 * Facts are established when the operation is constructed and never filled
 * in later. A node of another operation has no fact here.
 *
 * @visibility public
 * @example Reading the fact of a nested expression
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 + NULL');
 *     $query->facts->scalar($query->field(0)->expression->right)->type instanceof \SqlSemantics\Statement\Type\NullOnly // => true
 */
final class Facts
{
    use Snapshot;

    /**
     * @var array<int, array{Operand, ScalarFact}>
     */
    private readonly array $scalars;

    /**
     * @var array<int, array{Node, RelationFact}>
     */
    private readonly array $relations;

    /**
     * @var array<int, array{Query, QueryFact}>
     */
    private readonly array $queries;

    /**
     * @var array<int, array{QualifiedName, Relation}> Resolved names of input occurrences, such as multiple-table DELETE targets
     */
    private readonly array $references;

    /**
     * @var list<Table> The relation declarations the statement provides to a context
     */
    public readonly array $declarations;

    /**
     * @var list<Diagnostic> The semantic problems of the statement, in derivation order
     */
    public readonly array $diagnostics;

    /**
     * @var list<Warning> The conditions the statement raises without failing, in the order it raises them
     */
    public readonly array $warnings;

    /**
     * @var list<array{Warning, Node}> The stored instruction owning each nested notice
     */
    private readonly array $warningInstructions;

    /**
     * @param list<array{Operand, ScalarFact}> $scalars The fact of each scalar expression
     * @param list<array{Node, RelationFact}> $relations The fact of each relation occurrence and table use
     * @param list<array{Query, QueryFact}> $queries The fact of each query
     * @param list<Table> $declarations The relation declarations the statement provides
     * @param QueryFact|null $output The rows the statement returns, when it returns rows
     * @param list<Diagnostic> $diagnostics The semantic problems of the statement
     * @param list<Warning> $warnings The conditions the statement raises without failing
     * @param list<array{Warning, Node}> $warningInstructions Explicit instruction ownership, independent of source locations
     * @param list<array{QualifiedName, Relation}> $references Names bound to particular input occurrences
     */
    public function __construct(array $scalars, array $relations, array $queries, array $declarations, public readonly ?QueryFact $output, array $diagnostics, array $warnings = [], array $warningInstructions = [], array $references = [])
    {
        $this->scalars = $this->index($scalars);
        $this->relations = $this->index($relations);
        $this->queries = $this->index($queries);
        $this->references = $this->index(Check::pairs($references, QualifiedName::class, Relation::class, 'Relation references pair names with input occurrences.'));
        $this->declarations = Check::listOf($declarations, Table::class, 'Provided declarations are tables.');
        $this->diagnostics = Check::listOf($diagnostics, Diagnostic::class, 'Diagnostics are diagnostic values.');
        $this->warnings = Check::listOf($warnings, Warning::class, 'Warnings are warning values.');
        $this->warningInstructions = Check::pairs($warningInstructions, Warning::class, Node::class, 'Notice instructions are ordered pairs of warnings and statements.');
        $seen = [];
        foreach ($this->warningInstructions as [$warning]) {
            Check::input(in_array($warning, $this->warnings, true) && !isset($seen[spl_object_id($warning)]), 'Each notice instruction identifies a distinct recorded warning.');
            $seen[spl_object_id($warning)] = true;
        }
    }

    /**
     * Answers the stored instruction that owns a notice, or null for the outer request.
     */
    public function warningInstruction(Warning $warning): ?Node
    {
        foreach ($this->warningInstructions as [$candidate, $instruction]) {
            if ($candidate === $warning) {
                return $instruction;
            }
        }

        return null;
    }

    /**
     * Keys node-fact pairs by node identity, refusing a node that occurs twice.
     *
     * @template TNode of object
     * @template TFact of object
     * @param list<array{TNode, TFact}> $pairs
     * @return array<int, array{TNode, TFact}>
     */
    public function index(array $pairs): array
    {
        $indexed = [];
        foreach ($pairs as $pair) {
            $id = spl_object_id($pair[0]);
            Check::input(!isset($indexed[$id]), 'A statement node occurs at one position only.');
            $indexed[$id] = $pair;
        }

        return $indexed;
    }

    /**
     * Answers the facts of a scalar expression of this operation.
     */
    public function scalar(Operand $node): ScalarFact
    {
        $entry = $this->scalars[spl_object_id($node)] ?? null;
        Check::input($entry !== null && $entry[0] === $node, 'The expression is not part of this operation.');

        return $entry[1];
    }

    /**
     * Answers the facts of a relation occurrence or table use of this operation.
     */
    public function relation(Node $node): RelationFact
    {
        $entry = $this->relations[spl_object_id($node)] ?? null;
        Check::input($entry !== null && $entry[0] === $node, 'The relation is not part of this operation.');

        return $entry[1];
    }

    /**
     * Answers the input occurrence a name was bound to, or null when no binding was established.
     *
     * Consumers use this identity instead of repeating name resolution against their own scopes.
     */
    public function referencedRelation(QualifiedName $name): ?Relation
    {
        return $this->references[spl_object_id($name)][1] ?? null;
    }

    /**
     * Answers the facts of a query of this operation.
     */
    public function query(Query $node): QueryFact
    {
        $entry = $this->queries[spl_object_id($node)] ?? null;
        Check::input($entry !== null && $entry[0] === $node, 'The query is not part of this operation.');

        return $entry[1];
    }

    /**
     * Tells whether a node has a fact of any kind in this operation.
     */
    public function covers(Node $node): bool
    {
        $id = spl_object_id($node);

        return (($this->scalars[$id][0] ?? null) === $node) || (($this->relations[$id][0] ?? null) === $node) || (($this->queries[$id][0] ?? null) === $node);
    }
}
