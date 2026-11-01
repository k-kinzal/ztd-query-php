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
    /**
 * @var list<Diagnostic>
 */
    private array $diagnostics = [];

    /**
 * @var array<int, list<Diagnostic>>
 */
    private array $withheld = [];

    /**
 * Start of the current definition's condition scope.
 */
    private int $boundary = 0;

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
        $outerBoundary = $this->boundary;
        $this->boundary = $before;
        try {
            $result = $derive();
            $this->withheld[spl_object_id($definition)] = array_splice($this->diagnostics, $before);
        } finally {
            $this->boundary = $outerBoundary;
        }
        return $result;
    }

    /**
     * Reports a referenced definition's conditions once in the current scope.
     * A later unused definition must not consume conditions needed by the body.
     * Shared dependency conditions keep their identity and are not duplicated.
     */
    public function release(Node $definition): void
    {
        foreach ($this->withheld[spl_object_id($definition)] ?? [] as $condition) {
            if (!in_array($condition, array_slice($this->diagnostics, $this->boundary), true)) {
                $this->diagnostics[] = $condition;
            }
        }
    }

    /**
     * @return list<Diagnostic> The reached conditions in derivation order
     */
    public function all(): array
    {
        return $this->diagnostics;
    }
}
