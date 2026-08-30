<?php

declare(strict_types=1);

namespace SqlFixture\Plan;

use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * One relation as written, with the roles the operator implies.
 *
 * The sides are kept in the order they were written so that a plan prints back
 * the way it was given. Which end is the parent comes from the operator, not
 * from the order, so `a.id < b.a_id` and `b.a_id > a.id` describe the same
 * shape.
 *
 * A `?` next to an end marks that end optional, as it does in DBML: the
 * referencing column may be null, so the row on the other end need not exist.
 *
 * @phpstan-import-type FixtureRow from TypeMapperInterface
 */
final class Relation
{
    /**
     * Binds one relation as it was written.
     *
     * @param ColumnRef $left End written on the left
     * @param RelationKind $kind Operator between them, which decides which end is the parent
     * @param ColumnRef $right End written on the right
     * @param bool $leftOptional Whether a `?` was written next to the left end
     * @param bool $rightOptional Whether a `?` was written next to the right end
     *
     * @throws PlanSyntaxException When the two ends name different numbers of columns
     */
    public function __construct(
        public readonly ColumnRef $left,
        public readonly RelationKind $kind,
        public readonly ColumnRef $right,
        public readonly bool $leftOptional = false,
        public readonly bool $rightOptional = false,
    ) {
        if (count($left->columns) !== count($right->columns)) {
            throw PlanSyntaxException::compositeArityMismatch($left, $right);
        }
    }

    /**
     * `parent.column < child.column`: one parent row, several child rows.
     */
    public static function oneToMany(
        string|ColumnRef $parent,
        string|ColumnRef $child,
        bool $childOptional = false,
    ): self {
        return new self(
            ColumnRef::from($parent),
            RelationKind::OneToMany,
            ColumnRef::from($child),
            false,
            $childOptional
        );
    }

    /**
     * `child.column > parent.column`: the same shape written from the child.
     */
    public static function manyToOne(
        string|ColumnRef $child,
        string|ColumnRef $parent,
        bool $parentOptional = false,
    ): self {
        return new self(
            ColumnRef::from($child),
            RelationKind::ManyToOne,
            ColumnRef::from($parent),
            false,
            $parentOptional
        );
    }

    /**
     * `parent.column - child.column`: one row on each side.
     */
    public static function oneToOne(
        string|ColumnRef $parent,
        string|ColumnRef $child,
        bool $childOptional = false,
    ): self {
        return new self(
            ColumnRef::from($parent),
            RelationKind::OneToOne,
            ColumnRef::from($child),
            false,
            $childOptional
        );
    }

    /**
     * The fewest child rows to generate when none are given.
     *
     * A relation written without `?` says the child must be there, so an
     * unspecified child is still generated. Marking it optional is what makes
     * "none at all" a possible outcome.
     */
    public function minimumChildRows(): int
    {
        return $this->childIsOptional() ? 0 : 1;
    }

    /**
     * The most child rows the relation allows, or null where it is unbounded.
     */
    public function maximumChildRows(): ?int
    {
        return $this->childIsCollection() ? null : 1;
    }

    /**
     * The end holding a single row, generated before the other.
     */
    public function parent(): ColumnRef
    {
        return $this->at($this->kind->parentSide());
    }

    /**
     * The end referencing the parent, which may hold several rows.
     */
    public function child(): ColumnRef
    {
        return $this->at($this->kind->childSide());
    }

    /**
     * Whether the parent row may be absent, leaving the reference null.
     */
    public function parentIsOptional(): bool
    {
        return $this->isOptionalAt($this->kind->parentSide());
    }

    /**
     * Whether the child end was marked optional.
     *
     * This says nothing the data does not already say, since the number of
     * child rows comes from the values supplied for that table.
     */
    public function childIsOptional(): bool
    {
        return $this->isOptionalAt($this->kind->childSide());
    }

    /**
     * Reports whether the child end may hold more than one row.
     *
     * @return bool True when the operator makes the child a collection
     */
    public function childIsCollection(): bool
    {
        return $this->kind->childIsCollection();
    }

    /**
     * Map each child column onto the parent column it references.
     *
     * @return array<string, string> Child column => parent column
     */
    public function columnMap(): array
    {
        $childColumns = $this->child()->columns;
        $parentColumns = $this->parent()->columns;

        $map = [];
        foreach ($childColumns as $index => $column) {
            $map[$column] = $parentColumns[$index];
        }

        return $map;
    }

    /**
     * @return list<string> The tables this relation touches, left end first
     */
    public function tables(): array
    {
        if ($this->left->table === $this->right->table) {
            return [$this->left->table];
        }

        return [$this->left->table, $this->right->table];
    }

    /**
     * Answers the end written on one side.
     *
     * Which side holds the parent depends on the operator, so the sides are
     * asked for by name rather than by position.
     *
     * @param RelationSide $side Side to answer for
     *
     * @return ColumnRef The end written there
     */
    public function at(RelationSide $side): ColumnRef
    {
        return $side === RelationSide::Left ? $this->left : $this->right;
    }

    /**
     * Reports whether one side was marked optional.
     *
     * @param RelationSide $side Side to answer for
     *
     * @return bool True when a `?` was written next to that end
     */
    public function isOptionalAt(RelationSide $side): bool
    {
        return $side === RelationSide::Left ? $this->leftOptional : $this->rightOptional;
    }

    /**
     * Reads the linking columns off a parent row, as the child spells them.
     *
     * @param FixtureRow $parentRow Row on the parent end
     *
     * @return FixtureRow Child column => the value it must carry
     *
     * @throws PlanSchemaException When the parent row does not carry a column the relation reads
     */
    public function project(array $parentRow): array
    {
        $values = [];
        foreach ($this->columnMap() as $childColumn => $parentColumn) {
            if (!array_key_exists($parentColumn, $parentRow)) {
                throw PlanSchemaException::missingValue($childColumn, $this->parent(), $parentColumn);
            }
            $values[$childColumn] = $parentRow[$parentColumn];
        }

        return $values;
    }

    /**
     * Reports whether a caller has already said what the child row references.
     *
     * Where every linking column was given, generating a parent to fill them
     * would contradict what the caller asked for.
     *
     * @param FixtureRow $overrides Values the caller fixed on the child row
     *
     * @return bool True when every linking column was given
     */
    public function isSatisfiedBy(array $overrides): bool
    {
        foreach (array_keys($this->columnMap()) as $childColumn) {
            if (!array_key_exists($childColumn, $overrides)) {
                return false;
            }
        }

        return true;
    }
}
