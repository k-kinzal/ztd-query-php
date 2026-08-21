<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

use Stringable;

/**
 * The shape of a set of related fixtures, independent of any syntax.
 *
 * A plan can be written as a DBML relation string, built up through the
 * methods below, or subclassed to give a named plan a home. All three describe
 * the same value, and `from()` and `toString()` convert between the string and
 * the object without losing anything but formatting.
 *
 * Every method returns a new instance.
 */
class FixturePlan implements Stringable
{
    /**
     * @param list<Relation> $relations
     * @param list<string> $tables Every table the plan names, in first-mentioned order
     */
    final public function __construct(
        public readonly array $relations = [],
        public readonly array $tables = [],
    ) {
    }

    /**
     * Read a plan from the DBML relation syntax.
     *
     * @throws PlanSyntaxException
     */
    public static function from(string|self $plan): static
    {
        if ($plan instanceof self) {
            return new static($plan->relations, $plan->tables);
        }

        $parsed = (new PlanParser())->parse($plan);

        return new static($parsed->relations, $parsed->tables);
    }

    /**
     * Build the plan this class stands for.
     *
     * A named plan lives in a subclass that overrides definition():
     *
     *     final class OrderWithDetails extends FixturePlan
     *     {
     *         protected static function definition(): string
     *         {
     *             return 'order.id < order_detail.order_id';
     *         }
     *     }
     *
     *     OrderWithDetails::define();
     */
    public static function define(): static
    {
        return static::from(static::definition());
    }

    /**
     * The plan a subclass stands for, as a DBML relation string or a built plan.
     */
    protected static function definition(): string|self
    {
        throw PlanUndefinedException::forClass(static::class);
    }

    /**
     * Start a plan from the table its fixtures are rooted at.
     */
    public static function table(string $table): static
    {
        return new static([], [$table]);
    }

    /**
     * Add a relation, naming both ends and the operator between them.
     */
    public function withRelation(Relation $relation): static
    {
        return new static(
            [...$this->relations, $relation],
            $this->mergeTables($relation->tables())
        );
    }

    /**
     * Add a one-to-many relation, written `parent.column < child.column`.
     */
    public function withOneToMany(string|ColumnRef $parent, string|ColumnRef $child): static
    {
        return $this->withRelation(new Relation(
            $this->toRef($parent),
            RelationKind::OneToMany,
            $this->toRef($child)
        ));
    }

    /**
     * Add a many-to-one relation, written `child.column > parent.column`.
     */
    public function withManyToOne(string|ColumnRef $child, string|ColumnRef $parent, bool $optional = false): static
    {
        return $this->withRelation(new Relation(
            $this->toRef($child),
            RelationKind::ManyToOne,
            $this->toRef($parent),
            false,
            $optional
        ));
    }

    /**
     * Add a one-to-one relation, written `parent.column - child.column`.
     */
    public function withOneToOne(string|ColumnRef $parent, string|ColumnRef $child): static
    {
        return $this->withRelation(new Relation(
            $this->toRef($parent),
            RelationKind::OneToOne,
            $this->toRef($child)
        ));
    }

    /**
     * Name a table that takes part without being related to anything yet.
     */
    public function withTable(string $table): static
    {
        return new static($this->relations, $this->mergeTables([$table]));
    }

    /**
     * The table the fixtures are rooted at, which is the first one named.
     */
    public function rootTable(): ?string
    {
        return $this->tables[0] ?? null;
    }

    /**
     * Relations whose parent end is the given table.
     *
     * @return list<Relation>
     */
    public function relationsFrom(string $table): array
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
     * @param list<string> $tables
     * @return list<string>
     */
    private function mergeTables(array $tables): array
    {
        return array_values(array_unique([...$this->tables, ...$tables]));
    }

    private function toRef(string|ColumnRef $reference): ColumnRef
    {
        if ($reference instanceof ColumnRef) {
            return $reference;
        }

        $separator = strpos($reference, '.');
        if ($separator === false) {
            throw PlanSyntaxException::unexpected($reference, strlen($reference), "'.' after the table name");
        }

        return new ColumnRef(
            substr($reference, 0, $separator),
            [substr($reference, $separator + 1)]
        );
    }
}
