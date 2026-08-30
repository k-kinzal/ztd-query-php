<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

use SqlFixture\Plan\Syntax\PlanParser;
use SqlFixture\Plan\Syntax\PlanPrinter;
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
    /**
     * @var list<Relation|string>
     */
    private readonly array $parts;

    /**
     * @var list<Relation>
     */
    public readonly array $relations;

    /**
     * @var list<string> Every table named, in first-mentioned order
     */
    public readonly array $tables;

    /**
     * @var list<string> Every table, ordered so a parent always precedes its children
     */
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

            $tables[] = TableName::of($part);
        }

        $this->parts = array_values($parts);
        $this->relations = $relations;
        $this->tables = array_values(array_unique($tables));

        $integrity = new PlanIntegrity();
        $integrity->assertColumnsBoundOnce($relations);
        $integrity->assertNoUnboundedSelfReference($relations);
        $this->generationOrder = (new GenerationOrder())->of($this->tables, $relations);
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

    /**
     * Answers a plan carrying one more relation.
     *
     * @param Relation $relation Relation to add
     *
     * @return self A new plan; this one is unchanged
     *
     * @throws PlanStructureException When the relation binds a column already bound, or cannot be ordered
     */
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

    /**
     * Writes the plan in the syntax it can be read back from.
     *
     * @return string The plan
     */
    public function toString(): string
    {
        return (new PlanPrinter())->print($this);
    }

    /**
     * Writes the plan in the syntax it can be read back from.
     *
     * @return string The plan
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Answers every table joined to this one by relations, however the arrows point.
     *
     * A plan may describe several groups of tables that have nothing to do
     * with each other, and each group is generated on its own.
     *
     * @param string $table Table to start from
     *
     * @return list<string> That table and everything reachable from it
     */
    public function connectedTo(string $table): array
    {
        $found = [$table];
        for ($index = 0; $index < count($found); $index++) {
            foreach ($this->relations as $relation) {
                $ends = [$relation->left->table, $relation->right->table];
                foreach ([$ends, array_reverse($ends)] as [$from, $to]) {
                    if ($from === $found[$index] && !in_array($to, $found, true)) {
                        $found[] = $to;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * Answers the columns of a table that some relation reads off it.
     *
     * A row has to keep those columns even where the caller asked for nothing,
     * because the rows on the other end of the relation are built from them.
     *
     * @param string $table Table to answer for
     *
     * @return list<string> Column names, each named once
     */
    public function columnsReferencedOn(string $table): array
    {
        $columns = [];
        foreach ($this->dependentsOf($table) as $relation) {
            foreach ($relation->parent()->columns as $column) {
                if (!in_array($column, $columns, true)) {
                    $columns[] = $column;
                }
            }
        }

        return $columns;
    }
}
