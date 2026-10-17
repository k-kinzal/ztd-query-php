<?php

declare(strict_types=1);

namespace SqlSemantics\Construction;

use Closure;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Node;

/**
 * Records query-owned conditions and withholds unused definition conditions.
 *
 * This mutable recorder exists only during derivation. Published facts retain
 * immutable diagnostic values, including each query's own dependency conditions.
 *
 * @visibility SqlSemantics
 */
final class Conditions
{
    /** @var list<Diagnostic> */
    private array $diagnostics = [];

    /** @var array<int, list<Diagnostic>> */
    private array $withheld = [];

    /**
     * Records a semantic condition in derivation order.
     */
    public function report(Diagnostic $diagnostic): void
    {
        $this->diagnostics[] = $diagnostic;
    }

    /**
     * Associates conditions emitted during one query with its immutable facts.
     *
     * @param Closure(): QueryFact $derive The query derivation
     */
    public function query(Closure $derive): QueryFact
    {
        $before = count($this->diagnostics);
        $fact = $derive();
        $conditions = array_slice($this->diagnostics, $before);
        return $fact->diagnostics === $conditions ? $fact : new QueryFact($fact->projection, $fact->names, $fact->aggregates, $conditions);
    }

    /**
     * Withholds the conditions of a definition until its first reference.
     *
     * @template T
     * @param Closure(): T $derive The definition derivation
     * @return T
     */
    public function deferred(Node $definition, Closure $derive): mixed
    {
        $before = count($this->diagnostics);
        $result = $derive();
        $this->withheld[spl_object_id($definition)] = array_splice($this->diagnostics, $before);
        return $result;
    }

    /**
     * Reports a referenced definition's conditions exactly once.
     */
    public function release(Node $definition): void
    {
        $id = spl_object_id($definition);
        array_push($this->diagnostics, ...($this->withheld[$id] ?? []));
        unset($this->withheld[$id]);
    }

    /**
     * @return list<Diagnostic> The reached conditions in derivation order
     */
    public function all(): array
    {
        return $this->diagnostics;
    }
}
