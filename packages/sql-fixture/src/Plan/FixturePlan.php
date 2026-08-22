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

    private static function assertTableName(string $part): string
    {
        $table = trim($part);

        if (preg_match('/^(?:`[^`]+`|"[^"]+"|[A-Za-z_][A-Za-z0-9_$]*)$/', $table) !== 1) {
            throw PlanSyntaxException::notATableName($part);
        }

        return trim($table, '`"');
    }
}
