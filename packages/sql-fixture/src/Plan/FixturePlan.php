<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

use Stringable;

/**
 * The parsed form of a fixture plan: which tables take part, and how their
 * columns line up.
 *
 * This type is the plan. The DBML relation string is one way to write it down
 * and `from()` reads it; `toString()` writes it back. Building it up method by
 * method, or declaring one as a class, produce the same value by other routes:
 *
 *     FixturePlan::from('order.id < order_detail.order_id');
 *
 *     (new FixturePlan())->withOneToMany('order.id', 'order_detail.order_id');
 *
 *     final class OrderWithDetails extends FixturePlan
 *     {
 *         public function __construct()
 *         {
 *             parent::__construct(
 *                 Relation::oneToMany('order.id', 'order_detail.order_id'),
 *                 Relation::manyToOne('order.customer_id', 'customer.id'),
 *             );
 *         }
 *     }
 *
 * A subclass names a plan without adding a kind of plan, so the builders below
 * return a plain FixturePlan rather than the subclass: once a declared plan is
 * altered it is no longer the plan that class stands for.
 */
class FixturePlan implements Stringable
{
    /** @var list<Relation|string> */
    private readonly array $parts;

    /** @var list<Relation> */
    public readonly array $relations;

    /** @var list<string> Every table named, in first-mentioned order */
    public readonly array $tables;

    /** @var list<string> Every table, ordered so a parent always precedes its children */
    public readonly array $generationOrder;

    /**
     * @param Relation|string ...$parts Relations, and the names of tables that stand alone
     * @throws PlanSyntaxException If a string names anything but a plain table
     */
    public function __construct(Relation|string ...$parts)
    {
        $relations = [];
        $tables = [];

        foreach ($parts as $part) {
            if ($part instanceof Relation) {
                $relations[] = $part;
                $tables = [...$tables, ...$part->tables()];
                continue;
            }

            $tables[] = self::assertTableName($part);
        }

        $this->parts = array_values($parts);
        $this->relations = $relations;
        $this->tables = array_values(array_unique($tables));

        $this->rejectColumnsBoundTwice($relations);
        $this->rejectUnboundedSelfReferences($relations);
        $this->generationOrder = $this->sortByDependency($this->tables, $relations);
    }

    /**
     * Read a plan written in the DBML relation syntax.
     *
     * @throws PlanSyntaxException
     */
    public static function from(string|self $plan): self
    {
        if ($plan instanceof self) {
            return new self(...$plan->parts);
        }

        return (new PlanParser())->parse($plan);
    }

    /**
     * Start a plan from the table its fixtures are rooted at.
     */
    public static function table(string $table): self
    {
        return new self($table);
    }

    public function withRelation(Relation $relation): self
    {
        return new self(...[...$this->parts, $relation]);
    }

    /**
     * Add `parent.column < child.column`.
     */
    public function withOneToMany(
        string|ColumnRef $parent,
        string|ColumnRef $child,
        bool $childOptional = false,
    ): self {
        return $this->withRelation(Relation::oneToMany($parent, $child, $childOptional));
    }

    /**
     * Add `child.column > parent.column`.
     */
    public function withManyToOne(
        string|ColumnRef $child,
        string|ColumnRef $parent,
        bool $parentOptional = false,
    ): self {
        return $this->withRelation(Relation::manyToOne($child, $parent, $parentOptional));
    }

    /**
     * Add `parent.column - child.column`.
     */
    public function withOneToOne(
        string|ColumnRef $parent,
        string|ColumnRef $child,
        bool $childOptional = false,
    ): self {
        return $this->withRelation(Relation::oneToOne($parent, $child, $childOptional));
    }

    /**
     * Name a table that takes part without being related to anything.
     */
    public function withTable(string $table): self
    {
        return new self(...[...$this->parts, $table]);
    }

    /**
     * The table the fixtures are about, which is the first one named.
     *
     * This is where the plan reads from, not where generation starts: written
     * `order_detail.order_id > order.id` the subject is order_detail, but
     * order has to exist first. Use generationOrder for that.
     */
    public function subjectTable(): ?string
    {
        return $this->tables[0] ?? null;
    }

    /**
     * Tables nothing else has to be generated before.
     *
     * @return list<string>
     */
    public function roots(): array
    {
        $roots = [];

        foreach ($this->generationOrder as $table) {
            if ($this->dependenciesOf($table) === []) {
                $roots[] = $table;
            }
        }

        return $roots;
    }

    /**
     * Relations that must be satisfied before this table can be generated,
     * that is, those in which it is the child.
     *
     * @return list<Relation>
     */
    public function dependenciesOf(string $table): array
    {
        return array_values(array_filter(
            $this->relations,
            static fn (Relation $relation): bool => $relation->child()->table === $table
        ));
    }

    /**
     * Relations hanging off this table, that is, those in which it is the parent.
     *
     * @return list<Relation>
     */
    public function dependentsOf(string $table): array
    {
        return array_values(array_filter(
            $this->relations,
            static fn (Relation $relation): bool => $relation->parent()->table === $table
        ));
    }

    public function toString(): string
    {
        return (new PlanPrinter())->print($this);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * A column set references one parent, so binding it twice is a mistake
     * whether the two relations agree or not.
     *
     * @param list<Relation> $relations
     */
    private function rejectColumnsBoundTwice(array $relations): void
    {
        $seen = [];

        foreach ($relations as $relation) {
            $child = $relation->child();
            $key = $child->toString();

            if (isset($seen[$key])) {
                throw PlanStructureException::columnsBoundTwice($child, $seen[$key], $relation->parent());
            }

            $seen[$key] = $relation->parent();
        }
    }

    /**
     * A table that requires a row of itself can never finish.
     *
     * @param list<Relation> $relations
     */
    private function rejectUnboundedSelfReferences(array $relations): void
    {
        foreach ($relations as $relation) {
            $isSelfReference = $relation->parent()->table === $relation->child()->table;

            if ($isSelfReference && $relation->minimumChildRows() > 0) {
                throw PlanStructureException::unboundedSelfReference(
                    $relation->parent()->table,
                    (new PlanPrinter())->printRelation($relation)
                );
            }
        }
    }

    /**
     * Order the tables so every parent comes before its children.
     *
     * Self references are left out of the ordering: a table cannot precede
     * itself, and an optional one terminates on its own.
     *
     * @param list<string> $tables
     * @param list<Relation> $relations
     * @return list<string>
     */
    private function sortByDependency(array $tables, array $relations): array
    {
        $pending = $tables;
        $ordered = [];

        while ($pending !== []) {
            $ready = [];
            $waiting = [];

            foreach ($pending as $table) {
                if ($this->waitsForAny($table, $relations, $pending)) {
                    $waiting[] = $table;
                    continue;
                }

                $ready[] = $table;
            }

            if ($ready === []) {
                throw PlanStructureException::cycle($pending);
            }

            $ordered = [...$ordered, ...$ready];
            $pending = $waiting;
        }

        return $ordered;
    }

    /**
     * @param list<Relation> $relations
     * @param list<string> $pending
     */
    private function waitsForAny(string $table, array $relations, array $pending): bool
    {
        foreach ($relations as $relation) {
            $parent = $relation->parent()->table;

            if ($relation->child()->table !== $table || $parent === $table) {
                continue;
            }

            if (in_array($parent, $pending, true)) {
                return true;
            }
        }

        return false;
    }

    private static function assertTableName(string $part): string
    {
        $table = trim($part);

        if (preg_match('/^(?:`[^`]+`|"[^"]+"|[A-Za-z_][A-Za-z0-9_$]*)$/', $table) !== 1) {
            throw PlanSyntaxException::notATableName($part);
        }

        return trim($table, '`"');
    }
}
