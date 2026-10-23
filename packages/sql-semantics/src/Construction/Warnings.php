<?php

declare(strict_types=1);

namespace SqlSemantics\Construction;

use Closure;
use SqlSemantics\Statement\Fact\Warning;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Source\SourceMap;

/**
 * Collects derivation warnings with the input boundaries that order parsing notices.
 *
 * A statement can order its own located warnings after deriving dependencies out of input
 * order. Warnings outside that statement and warnings without a location keep their places.
 *
 * @visibility SqlSemantics
 */
final class Warnings
{
    /**
     * @var list<array{Warning, int|null, Node|null, Node|null}> Warnings, input boundaries, enclosing stored instructions and semantic subjects
     */
    private array $entries = [];

    /**
     * @var list<Closure(Warning): bool> Filters of the current derivation scopes
     */
    private array $filters = [];

    /**
     * @param SourceMap $sources The original input locations and spelling notices
     */
    public function __construct(public readonly SourceMap $sources)
    {
    }

    /**
     * Records a warning before or after its occurrence, when a location is available.
     */
    public function append(Warning $warning, ?Node $at, bool $after = true, ?Node $instruction = null): void
    {
        foreach ($this->filters as $keep) {
            if (!$keep($warning)) {
                return;
            }
        }
        $origin = $at === null ? null : $this->sources->of($at);
        $this->entries[] = [$warning, $origin === null ? null : $origin->offset + ($after ? $origin->length : 0), $instruction, $at];
    }

    /**
     * Records only the warnings belonging to a selected semantic role.
     * Input spelling notices are independent and remain unchanged.
     *
     * @template T
     * @param Closure(): T $derive The nested derivation
     * @param Closure(Warning): bool $keep The warnings that belong to this role
     * @return T
     */
    public function filtered(Closure $derive, Closure $keep): mixed
    {
        $this->filters[] = $keep;
        try {
            return $derive();
        } finally {
            array_pop($this->filters);
        }
    }

    /**
     * Keeps instruction ownership independent of input locations and warning order.
     *
     * @return list<array{Warning, Node}>
     */
    public function instructions(): array
    {
        $instructions = [];
        foreach ($this->entries as [$warning, , $instruction]) {
            if ($instruction !== null) {
                $instructions[] = [$warning, $instruction];
            }
        }

        return $instructions;
    }

    /**
     * Retains semantic subjects independently of source locations.
     *
     * @return list<array{Warning, Node}>
     */
    public function subjects(): array
    {
        $subjects = [];
        foreach ($this->entries as [$warning, , , $subject]) {
            if ($subject !== null) {
                $subjects[] = [$warning, $subject];
            }
        }
        return $subjects;
    }

    /**
     * Orders the located warnings within a statement while preserving all other positions.
     * A directly constructed statement with no source location retains derivation order.
     */
    public function order(Node $scope): void
    {
        $origin = $this->sources->of($scope);
        if ($origin === null) {
            return;
        }
        $indices = [];
        $ordered = [];
        foreach ($this->entries as $index => $entry) {
            $offset = $entry[1];
            if ($offset !== null && $offset >= $origin->offset && $offset <= $origin->offset + $origin->length) {
                $indices[] = $index;
                $ordered[] = $entry;
            }
        }
        usort($ordered, static fn (array $left, array $right): int => $left[1] <=> $right[1]);
        foreach ($indices as $position => $index) {
            $this->entries[$index] = $ordered[$position];
        }
    }

    /**
     * Merges original spelling notices at their input boundaries, when any were recorded.
     * Without spelling notices, the recorded derivation or scoped ordering is retained.
     *
     * @return list<Warning>
     */
    public function all(): array
    {
        if ($this->sources->notices === []) {
            return array_column($this->entries, 0);
        }
        $ordered = [];
        foreach ($this->sources->notices as $notice) {
            $ordered[] = [$notice->warning, $notice->offset];
        }
        foreach ($this->entries as [$warning, $offset]) {
            $ordered[] = [$warning, $offset ?? PHP_INT_MAX];
        }
        usort($ordered, static fn (array $left, array $right): int => $left[1] <=> $right[1]);

        return array_column($ordered, 0);
    }
}
